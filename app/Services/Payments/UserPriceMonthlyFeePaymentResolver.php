<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\UserPrice;
use App\Services\Postpay\PostpayMonth;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Support\UserPriceTeamMembership;
use App\Support\Payments\PaymentOutSumNormalizer;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Сумма месячного абонемента для оплаты берётся из users_prices (не из POST).
 */
final class UserPriceMonthlyFeePaymentResolver
{
    public function __construct(
        private readonly PostpayUsersPriceSync $postpaySync,
    ) {
    }

    /**
     * @return array{out_sum: string, month_first_day: string, team_id: int}
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

        $raw = $row->price;
        if ($raw === null || $raw === '') {
            throw new UnprocessableEntityHttpException('Неверная цена: сумма не задана.');
        }

        $normalized = PaymentOutSumNormalizer::normalize(trim(str_replace(',', '.', (string) $raw)));
        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('Неверная цена: не удаётся определить сумму к оплате.');
        }

        if ((float) $normalized <= 0) {
            throw new UnprocessableEntityHttpException('Неверная цена: к оплате должна быть сумма больше нуля.');
        }

        return [
            'out_sum' => $normalized,
            'month_first_day' => $monthFirst,
            'team_id' => $resolvedTeamId,
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
            ->where('price', '>', 0)
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
