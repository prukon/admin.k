<?php

declare(strict_types=1);

namespace App\Services\PaymentNotifications;

use App\Models\User;

/**
 * Один адрес письма об оплате: почта родителя, иначе почта ученика.
 * Совпадающие адреса дают одно письмо, потому что выбирается только почта родителя.
 */
final class PaymentNotificationRecipient
{
    public function emailFor(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('parentProfile');

        $parentEmail = trim((string) ($user->parentProfile?->email ?? ''));
        if ($this->isDeliverable($parentEmail)) {
            return $parentEmail;
        }

        $studentEmail = trim((string) ($user->email ?? ''));
        if ($this->isDeliverable($studentEmail)) {
            return $studentEmail;
        }

        return null;
    }

    private function isDeliverable(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
