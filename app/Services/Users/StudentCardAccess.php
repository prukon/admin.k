<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\PartnerContext;
use App\Services\TrainerOwnTeamsScope;

/**
 * Карточка пользователя текущего партнёра.
 * Ученик дополнительно ограничен groups.own. Платежи, договоры и семья — только role=user.
 */
final class StudentCardAccess
{
    public function __construct(
        private readonly PartnerContext $partnerContext,
        private readonly TrainerOwnTeamsScope $ownTeams,
    ) {
    }

    public function findCardUser(User $user): ?User
    {
        $partnerId = (int) ($this->partnerContext->partnerId() ?? 0);
        if ($partnerId < 1) {
            return null;
        }

        $person = User::query()
            ->with('role')
            ->where('partner_id', $partnerId)
            ->whereKey($user->getKey())
            ->first();

        if ($person === null) {
            return null;
        }

        if ($person->role?->name === 'user'
            && ! $this->ownTeams->allowsStudent($this->partnerContext->user(), $partnerId, $person, false)
        ) {
            return null;
        }

        return $person;
    }

    public function findStudent(User $user): ?User
    {
        $person = $this->findCardUser($user);
        if ($person === null || $person->role?->name !== 'user') {
            return null;
        }

        return $person;
    }
}
