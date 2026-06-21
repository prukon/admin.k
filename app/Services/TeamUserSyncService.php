<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamUserSyncService
{
    /**
     * Полная синхронизация групп ученика (роль user).
     *
     * @param  int[]  $teamIds
     */
    public function syncTeamsForStudent(User $user, array $teamIds): void
    {
        $partnerId = (int) $user->partner_id;
        if ($partnerId <= 0 || ! $this->isStudentUser($user)) {
            return;
        }

        $validTeamIds = $this->resolveValidTeamIds($partnerId, $teamIds);

        DB::table('team_user')
            ->where('user_id', $user->id)
            ->where('partner_id', $partnerId)
            ->when($validTeamIds !== [], fn ($q) => $q->whereNotIn('team_id', $validTeamIds))
            ->delete();

        $now = now();

        foreach ($validTeamIds as $teamId) {
            DB::table('team_user')->updateOrInsert(
                [
                    'team_id' => $teamId,
                    'user_id' => $user->id,
                ],
                [
                    'partner_id' => $partnerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    /**
     * Назначить ученику одну группу (attach), не снимая остальные.
     */
    public function attachTeamForStudent(User $user, int $teamId): void
    {
        $partnerId = (int) $user->partner_id;
        if ($partnerId <= 0 || $teamId <= 0 || ! $this->isStudentUser($user)) {
            return;
        }

        $validTeamIds = $this->resolveValidTeamIds($partnerId, [$teamId]);
        if ($validTeamIds === []) {
            return;
        }

        $now = now();

        DB::table('team_user')->updateOrInsert(
            [
                'team_id' => $validTeamIds[0],
                'user_id' => $user->id,
            ],
            [
                'partner_id' => $partnerId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    /**
     * Снять ученика со всех групп партнёра.
     */
    public function detachAllTeamsForStudent(User $user): void
    {
        $partnerId = (int) $user->partner_id;
        if ($partnerId <= 0) {
            return;
        }

        DB::table('team_user')
            ->where('user_id', $user->id)
            ->where('partner_id', $partnerId)
            ->delete();
    }

    /**
     * Отвязать всех учеников от группы (soft delete группы и т.п.).
     */
    public function detachTeamFromAllStudents(int $teamId, int $partnerId): void
    {
        if ($teamId <= 0 || $partnerId <= 0) {
            return;
        }

        DB::table('team_user')
            ->where('team_id', $teamId)
            ->where('partner_id', $partnerId)
            ->delete();
    }

    /**
     * Подписи групп ученика для логов и списков (актуальные данные из pivot).
     */
    public function teamTitlesLabel(User $user): string
    {
        return $this->teamTitlesCollection($user)->implode(', ');
    }

    /**
     * @return Collection<int, string>
     */
    public function teamTitlesCollection(User $user): Collection
    {
        if ($user->relationLoaded('teams')) {
            return $user->teams
                ->pluck('title')
                ->map(fn ($title) => trim((string) $title))
                ->filter()
                ->sort()
                ->values();
        }

        return $user->teams()
            ->orderBy('teams.title')
            ->pluck('teams.title')
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->values();
    }

    /**
     * @return int[]
     */
    public function teamIdsForStudent(User $user): array
    {
        if ($user->relationLoaded('teams')) {
            return $user->teams
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $user->teams()
            ->pluck('teams.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * При создании через factory/seed: если задан legacy users.team_id — дублируем в pivot один раз.
     * Колонку users.team_id не обновляем при дальнейших изменениях групп.
     */
    public function syncLegacyTeamColumnToPivot(User $user): void
    {
        if (! $user->team_id || ! $user->partner_id || ! $this->isStudentUser($user)) {
            return;
        }

        $this->attachTeamForStudent($user, (int) $user->team_id);
    }

    /**
     * @param  int[]  $teamIds
     * @return int[]
     */
    private function resolveValidTeamIds(int $partnerId, array $teamIds): array
    {
        $teamIds = array_values(array_unique(array_filter(
            array_map('intval', $teamIds),
            fn (int $id) => $id > 0
        )));

        if ($teamIds === []) {
            return [];
        }

        return Team::query()
            ->where('partner_id', $partnerId)
            ->whereIn('id', $teamIds)
            ->orderBy('order_by')
            ->orderBy('title')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isStudentUser(User $user): bool
    {
        $user->loadMissing('role');

        if ($user->role?->name === 'user') {
            return true;
        }

        if (! $user->role_id) {
            return false;
        }

        return Role::query()
            ->whereKey((int) $user->role_id)
            ->where('name', 'user')
            ->exists();
    }
}
