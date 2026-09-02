<?php

namespace App\Services\Payments;

use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\Postpay\PostpayMonth;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Support\UserPriceTeamMembership;
use App\Support\Money;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Сумма месячного абонемента для оплаты берётся из users_prices.price_cents (не из POST).
 */
final class UserPriceMonthlyFeePaymentResolver
{
    public function __construct(
        private readonly PostpayUsersPriceSync $postpaySync,
    ) {
    }

    /**
     * @return array{amount_cents: int, out_sum: string, month_first_day: string, team_id: int}
     */
    public function resolveOrAbort(int $userId, int $partnerId, string $formatedPaymentDate, ?int $teamId = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formatedPaymentDate)) {
            throw new UnprocessableEntityHttpException('Некорректный период оплаты.');
        }

        try {
            $monthFirst = Carbon::parse($formatedPaymentDate)->startOfMonth()->format('Y-m-d');
        } catch (\Throwable) {
            throw new UnprocessableEntityHttpException('Некорректный период оплаты.');
        }

        $user = User::query()
            ->where('id', $userId)
            ->where('partner_id', $partnerId)
            ->first();

        if (!$user) {
            throw new AccessDeniedHttpException('Нет доступа к оплате за выбранный период.');
        }

        $resolvedTeamId = $this->resolveTeamId($user, $partnerId, $monthFirst, $teamId);

        $row = UserPrice::query()
            ->where('user_id', $userId)
            ->where('team_id', $resolvedTeamId)
            ->whereDate('new_month', $monthFirst)
            ->with('lessonPackage')
            ->first();

        if (!$row) {
            throw new AccessDeniedHttpException('Нет начисления за выбранный период. Обратитесь в школу.');
        }

        if ($row->lessonPackage && $row->lessonPackage->isPostpay()) {
            $this->postpaySync->syncRow($row);
            $row->refresh();

            if (! PostpayMonth::isPayAvailableNow($monthFirst)) {
                $label = PostpayMonth::payAvailableFromLabel($monthFirst);
                throw new UnprocessableEntityHttpException('Оплата будет доступна с '.$label);
            }
        }

        if ($row->effective_is_paid) {
            throw new UnprocessableEntityHttpException('Этот период уже оплачен.');
        }

        $amountCents = (int) ($row->price_cents ?? 0);
        if ($amountCents <= 0) {
            throw new UnprocessableEntityHttpException('Неверная цена: к оплате должна быть сумма больше нуля.');
        }

        return [
            'amount_cents' => $amountCents,
            'out_sum' => Money::fromCents($amountCents),
            'month_first_day' => $monthFirst,
            'team_id' => $resolvedTeamId,
        ];
    }

    /**
     * Публичная оплата по конкретной строке users_prices (ссылка из email).
     * Членство в группе сейчас не требуется: долг привязан к строке начисления.
     *
     * @return array{user_price: UserPrice, amount_cents: int, out_sum: string, month_first_day: string, team_id: int}
     */
    public function resolvePublicPayForPartner(int $partnerId, UserPrice $userPrice): array
    {
        $userPrice->loadMissing(['user', 'lessonPackage', 'team']);

        $user = $userPrice->user;
        if (! $user || (int) $user->partner_id !== $partnerId) {
            throw new HttpException(404, 'Начисление не найдено');
        }

        $teamId = (int) $userPrice->team_id;
        if ($teamId <= 0) {
            throw new UnprocessableEntityHttpException('Для начисления не указана группа. Обратитесь в школу.');
        }

        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey($teamId)
            ->first();
        if (! $team) {
            throw new UnprocessableEntityHttpException('Группа начисления не найдена. Обратитесь в школу.');
        }

        try {
            $monthFirst = Carbon::parse((string) $userPrice->new_month)->startOfMonth()->format('Y-m-d');
        } catch (\Throwable) {
            throw new UnprocessableEntityHttpException('Некорректный период оплаты.');
        }

        if ($userPrice->lessonPackage && $userPrice->lessonPackage->isPostpay()) {
            $this->postpaySync->syncRow($userPrice);
            $userPrice->refresh();
            $userPrice->loadMissing(['user', 'lessonPackage', 'team']);

            if (! PostpayMonth::isPayAvailableNow($monthFirst)) {
                $label = PostpayMonth::payAvailableFromLabel($monthFirst);
                throw new UnprocessableEntityHttpException('Оплата будет доступна с '.$label);
            }
        }

        if ($userPrice->effective_is_paid) {
            throw new UnprocessableEntityHttpException('Этот период уже оплачен.');
        }

        $amountCents = (int) ($userPrice->price_cents ?? 0);
        if ($amountCents <= 0) {
            throw new UnprocessableEntityHttpException('Неверная цена: к оплате должна быть сумма больше нуля.');
        }

        return [
            'user_price' => $userPrice,
            'amount_cents' => $amountCents,
            'out_sum' => Money::fromCents($amountCents),
            'month_first_day' => $monthFirst,
            'team_id' => $teamId,
        ];
    }

    private function resolveTeamId(User $user, int $partnerId, string $monthFirst, ?int $teamId): int
    {
        if ($teamId !== null && $teamId > 0) {
            if (! UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
                throw new AccessDeniedHttpException('Ученик не состоит в указанной группе.');
            }

            return $teamId;
        }

        $rows = UserPrice::query()
            ->where('user_id', $user->id)
            ->whereDate('new_month', $monthFirst)
            ->where('price_cents', '>', 0)
            ->get(['team_id']);

        if ($rows->count() === 1) {
            return (int) $rows->first()->team_id;
        }

        if ($rows->count() > 1) {
            throw new UnprocessableEntityHttpException('Укажите группу для оплаты за этот месяц.');
        }

        $primaryTeamId = UserPriceTeamMembership::primaryTeamIdForStudent($user, $partnerId);
        if ($primaryTeamId === null) {
            throw new AccessDeniedHttpException('Нет начисления за выбранный период. Обратитесь в школу.');
        }

        return $primaryTeamId;
    }
}
