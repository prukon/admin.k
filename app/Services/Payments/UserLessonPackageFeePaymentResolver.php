<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\User;
use App\Models\UserLessonPackage;
use App\Support\Payments\PaymentOutSumNormalizer;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Сумма оплаты назначенного абонемента — только из {@see UserLessonPackage::fee_amount}.
 * При старте оплаты (Robokassa, T‑Bank, страница /payment) значение из POST (outSum) не используется —
 * всегда вызывайте этот резолвер заново.
 */
final class UserLessonPackageFeePaymentResolver
{
    /**
     * @return array{ulp: UserLessonPackage, out_sum: string, payment_label: string}
     */
    public function resolveOrAbort(int $userId, int $partnerId, int $ulpId): array
    {
        /** @var UserLessonPackage|null $ulp */
        $ulp = UserLessonPackage::query()
            ->with(['lessonPackage:id,name', 'user:id,partner_id'])
            ->whereKey($ulpId)
            ->first();

        if (! $ulp || ! $ulp->user || (int) $ulp->user->partner_id !== $partnerId || (int) $ulp->user_id !== $userId) {
            throw new HttpException(404, 'Назначение абонемента не найдено');
        }

        if ($ulp->effective_is_paid) {
            throw new HttpException(422, 'Абонемент уже оплачен');
        }

        $raw = $ulp->fee_amount;
        if ($raw === null) {
            throw new HttpException(422, 'Для назначения не задана сумма к оплате');
        }

        $normalized = PaymentOutSumNormalizer::normalize((string) $raw);
        if ($normalized === null || (float) $normalized <= 0) {
            throw new HttpException(422, 'Некорректная сумма абонемента для оплаты');
        }

        $name = $ulp->lessonPackage?->name ?? 'Абонемент';

        return [
            'ulp' => $ulp,
            'out_sum' => $normalized,
            'payment_label' => 'Абонемент: '.$name.' №'.(int) $ulp->id,
        ];
    }
}
