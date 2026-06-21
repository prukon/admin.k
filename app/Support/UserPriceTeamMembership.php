<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class UserPriceTeamMembership
{
    /**
     * Группа партнёра, в которой состоит ученик.
     */
    public static function studentBelongsToTeam(User $user, int $teamId, int $partnerId): bool
    {
        if ($teamId <= 0) {
            return false;
        }

        return Team::query()
            ->whereKey($teamId)
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->whereHas('students', fn (Builder $q) => $q->where('users.id', $user->id))
            ->exists();
    }

    /**
     * Первая группа ученика в pivot (order_by, title) — для fallback без явного team_id.
     *
     * @return int|null
     */
    public static function primaryTeamIdForStudent(User $user, int $partnerId): ?int
    {
        if ($user->relationLoaded('teams')) {
            $team = $user->teams
                ->first(fn ($t) => (int) $t->partner_id === $partnerId);

            return $team ? (int) $team->id : null;
        }

        $id = $user->teams()
            ->where('teams.partner_id', $partnerId)
            ->whereNull('teams.deleted_at')
            ->orderBy('teams.order_by')
            ->orderBy('teams.title')
            ->value('teams.id');

        return $id ? (int) $id : null;
    }
}
