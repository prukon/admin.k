<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\UserPrice;
use App\Support\Payments\PaymentOutSumNormalizer;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Сумма месячного абонемента для оплаты берётся из users_prices (не из POST).
 */
final class UserPriceMonthlyFeePaymentResolver
{
    /**
     * @return array{out_sum: string, month_first_day: string}
     */
    public function resolveOrAbort(int $userId, int $partnerId, string $formatedPaymentDate): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formatedPaymentDate)) {
            throw new UnprocessableEntityHttpException('Некорректный период оплаты.');
        }

        try {
            $monthFirst = Carbon::parse($formatedPaymentDate)->startOfMonth()->format('Y-m-d');
        } catch (\Throwable) {
            throw new UnprocessableEntityHttpException('Некорректный период оплаты.');
        }

        $userBelongs = User::query()
            ->where('id', $userId)
            ->where('partner_id', $partnerId)
            ->exists();

        if (!$userBelongs) {
            throw new AccessDeniedHttpException('Нет доступа к оплате за выбранный период.');
        }

        $row = UserPrice::query()
            ->where('user_id', $userId)
            ->whereDate('new_month', $monthFirst)
            ->first();

        if (!$row) {
            throw new AccessDeniedHttpException('Нет начисления за выбранный период. Обратитесь в школу.');
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
        ];
    }
}
