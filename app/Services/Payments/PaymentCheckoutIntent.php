<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Подписанный контекст страницы оплаты.
 * Init читает вид платежа только отсюда, не из произвольных полей формы.
 */
final class PaymentCheckoutIntent
{
    public const KIND_MONTHLY = 'monthly_fee';

    public const KIND_LESSON = 'lesson_package';

    public const KIND_CUSTOM = 'custom_payment';

    public const KIND_CLUB = 'club_fee';

    public const VERSION = 1;

    public function __construct(
        public readonly string $kind,
        public readonly int $partnerId,
        public readonly int $actorUserId,
        public readonly int $studentUserId,
        public readonly ?string $month,
        public readonly ?int $teamId,
        public readonly ?int $userLessonPackageId,
        public readonly ?int $customPaymentId,
        public readonly int $expiresAt,
    ) {
    }

    public function requestPaymentKind(): string
    {
        return match ($this->kind) {
            self::KIND_CUSTOM => 'custom_payment',
            self::KIND_LESSON => 'lesson_package',
            default => '',
        };
    }

    public function isMonthly(): bool
    {
        return $this->kind === self::KIND_MONTHLY;
    }
}
