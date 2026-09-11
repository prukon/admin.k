<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\TrainerOwnTeamsScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * При groups.own разрешает только группы тренера из team_trainer.
 */
class AllowedActorTeam implements ValidationRule
{
    public function __construct(
        private readonly int $partnerId,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value === 'all' || $value === 'none') {
            return;
        }

        if (! ctype_digit((string) $value) || (int) $value <= 0) {
            return;
        }

        $scope = app(TrainerOwnTeamsScope::class);
        if (! $scope->allowsTeamId(auth()->user(), $this->partnerId, (int) $value)) {
            $fail('Выберите группу из списка.');
        }
    }
}
