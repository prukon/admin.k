<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\User;
use App\Models\UserLessonPackage;
use App\Support\Money;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Сумма оплаты назначенного абонемента — только из {@see UserLessonPackage::fee_amount_cents}.
 * При старте оплаты значение из POST (outSum) не используется.
 */
final class UserLessonPackageFeePaymentResolver
{
    /**
     * @return array{ulp: UserLessonPackage, amount_cents: int, out_sum: string, payment_label: string}
     */
    public function resolveOrAbort(int $userId, int $partnerId, int $ulpId): array
    {
        return $this->resolve($partnerId, $ulpId, $userId);
    }

    /**
     * Публичная оплата по ссылке: проверка только принадлежности назначения партнёру.
     *
     * @return array{ulp: UserLessonPackage, amount_cents: int, out_sum: string, payment_label: string}
     */
    public function resolvePublicPayForPartner(int $partnerId, int $ulpId): array
    {
        return $this->resolve($partnerId, $ulpId, null);
    }

    /**
     * @return array{ulp: UserLessonPackage, amount_cents: int, out_sum: string, payment_label: string}
     */
    private function resolve(int $partnerId, int $ulpId, ?int $requireUserId): array
    {
        /** @var UserLessonPackage|null $ulp */
        $ulp = UserLessonPackage::query()
            ->with(['lessonPackage:id,name', 'user:id,partner_id'])
            ->whereKey($ulpId)
            ->first();

        if (! $ulp || ! $ulp->user || (int) $ulp->user->partner_id !== $partnerId) {
            throw new HttpException(404, 'Назначение абонемента не найдено');
        }

        if ($requireUserId !== null && (int) $ulp->user_id !== $requireUserId) {
            throw new HttpException(404, 'Назначение абонемента не найдено');
        }

        if ($ulp->effective_is_paid) {
            throw new HttpException(422, 'Абонемент уже оплачен');
        }

        if ($ulp->fee_amount_cents === null) {
            throw new HttpException(422, 'Для назначения не задана сумма к оплате');
        }

        $amountCents = (int) $ulp->fee_amount_cents;
        if ($amountCents <= 0) {
            throw new HttpException(422, 'Некорректная сумма абонемента для оплаты');
        }

        $name = $ulp->lessonPackage?->name ?? 'Абонемент';

        return [
            'ulp' => $ulp,
            'amount_cents' => $amountCents,
            'out_sum' => Money::fromCents($amountCents),
            'payment_label' => 'Абонемент: '.$name.' №'.(int) $ulp->id,
        ];
    }
}
