<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\User;

/**
 * Кто инициировал оплату (учётка входа) и за кого списывается начисление (активный ученик).
 */
final class FamilyPaymentPayer
{
    public function __construct(
        public readonly User $actor,
        public readonly User $student,
    ) {
    }

    public function actorId(): int
    {
        return (int) $this->actor->id;
    }

    public function studentId(): int
    {
        return (int) $this->student->id;
    }
}
