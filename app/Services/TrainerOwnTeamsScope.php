<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrainerProfile;
use App\Models\User;
use App\Support\UserTeamQuery;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Право groups.own: тренер видит только группы из team_trainer.
 * Superadmin проходит can(), но без профиля тренера ограничение не включается.
 */
final class TrainerOwnTeamsScope
{
    public const PERMISSION = 'groups.own';

    /** @var array<string, int[]|null> */
    private array $allowedCache = [];

    public function isRestricted(?User $user): bool
    {
        if ($user === null || ! $user->can(self::PERMISSION)) {
            return false;
        }

        return $this->trainerProfileId($user) !== null;
    }

    public function trainerProfileId(?User $user): ?int
    {
        if ($user === null || (int) $user->id <= 0) {
            return null;
        }

        $profile = $user->relationLoaded('trainerProfile')
            ? $user->trainerProfile
            : TrainerProfile::query()->where('user_id', (int) $user->id)->first();

        if (! $profile) {
            return null;
        }

        $id = (int) $profile->id;

        return $id > 0 ? $id : null;
    }

    /**
     * @return int[]|null null — без ограничения
     */
    public function allowedTeamIds(?User $user, int $partnerId): ?array
    {
        if ($partnerId <= 0 || ! $this->isRestricted($user)) {
            return null;
        }

        $key = (int) $user->id.':'.$partnerId;
        if (array_key_exists($key, $this->allowedCache)) {
            return $this->allowedCache[$key];
        }

        $profileId = $this->trainerProfileId($user);
        if ($profileId === null) {
            $this->allowedCache[$key] = [];

            return [];
        }

        $ids = DB::table('team_trainer')
            ->where('partner_id', $partnerId)
            ->where('trainer_profile_id', $profileId)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->allowedCache[$key] = $ids;

        return $ids;
    }

    public function allowsTeamId(?User $user, int $partnerId, int $teamId): bool
    {
        if ($teamId <= 0) {
            return false;
        }

        $allowed = $this->allowedTeamIds($user, $partnerId);
        if ($allowed === null) {
            return true;
        }

        return in_array($teamId, $allowed, true);
    }

    /**
     * Карточка ученика в консоли: свои группы; без группы — не прячем.
     */
    public function allowsStudent(?User $actor, int $partnerId, User $student, bool $allowWithoutTeam = true): bool
    {
        $allowed = $this->allowedTeamIds($actor, $partnerId);
        if ($allowed === null) {
            return true;
        }

        $teamIds = DB::table('team_user')
            ->where('user_id', (int) $student->id)
            ->where('partner_id', $partnerId)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($teamIds === []) {
            return $allowWithoutTeam;
        }

        foreach ($teamIds as $id) {
            if (in_array($id, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  EloquentBuilder|\Illuminate\Database\Eloquent\Relations\Relation|QueryBuilder  $query
     */
    public function restrictTeamsQuery($query, ?User $user, int $partnerId): void
    {
        $allowed = $this->allowedTeamIds($user, $partnerId);
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('teams.id', $allowed);
    }

    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public function restrictStudentsQuery($query, ?User $user, int $partnerId, string $usersAlias = 'users'): void
    {
        $allowed = $this->allowedTeamIds($user, $partnerId);
        if ($allowed === null) {
            return;
        }

        UserTeamQuery::applyStudentInAnyTeamExists($query, $partnerId, $allowed, $usersAlias);
    }

    /**
     * Чат без фильтра группы: сотрудники школы + ученики своих групп.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public function restrictChatContactsQuery($query, ?User $user, int $partnerId, string $usersAlias = 'users'): void
    {
        $allowed = $this->allowedTeamIds($user, $partnerId);
        if ($allowed === null) {
            return;
        }

        $query->where(function ($outer) use ($partnerId, $allowed, $usersAlias) {
            $outer->where(function ($staff) use ($usersAlias) {
                $staff->whereHas('role', function ($role) {
                    $role->where('name', '!=', 'user');
                })->orWhere(function ($noRole) use ($usersAlias) {
                    $noRole->whereNull($usersAlias.'.role_id');
                });
            })->orWhere(function ($students) use ($partnerId, $allowed, $usersAlias) {
                $students->whereHas('role', function ($role) {
                    $role->where('name', 'user');
                });
                UserTeamQuery::applyStudentInAnyTeamExists($students, $partnerId, $allowed, $usersAlias);
            });
        });
    }

    public function visibleTeamTitlesLabel(User $student, ?User $actor, int $partnerId): string
    {
        if ($partnerId <= 0 || (int) $student->id <= 0) {
            return '';
        }

        $query = DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->where('team_user.user_id', (int) $student->id)
            ->where('team_user.partner_id', $partnerId)
            ->where('teams.partner_id', $partnerId)
            ->whereNull('teams.deleted_at');

        $allowed = $this->allowedTeamIds($actor, $partnerId);
        if ($allowed !== null) {
            if ($allowed === []) {
                return '';
            }
            $query->whereIn('teams.id', $allowed);
        }

        return $query
            ->orderBy('teams.title')
            ->pluck('teams.title')
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * @param  int[]  $existingIds
     * @param  int[]  $submittedIds
     * @return int[]
     */
    public function mergeSubmittedStudentTeamIds(
        ?User $actor,
        int $partnerId,
        array $existingIds,
        array $submittedIds,
    ): array {
        $submitted = $this->normalizeIds($submittedIds);
        $allowed = $this->allowedTeamIds($actor, $partnerId);
        if ($allowed === null) {
            return $submitted;
        }

        $existing = $this->normalizeIds($existingIds);
        $hidden = array_values(array_filter(
            $existing,
            fn (int $id) => ! in_array($id, $allowed, true)
        ));
        $ownSubmitted = array_values(array_filter(
            $submitted,
            fn (int $id) => in_array($id, $allowed, true)
        ));

        return array_values(array_unique(array_merge($hidden, $ownSubmitted)));
    }

    /**
     * @param  int[]  $ids
     * @return int[]
     */
    public function visibleTeamIds(array $ids, ?User $actor, int $partnerId): array
    {
        $ids = $this->normalizeIds($ids);
        $allowed = $this->allowedTeamIds($actor, $partnerId);
        if ($allowed === null) {
            return $ids;
        }

        return array_values(array_filter(
            $ids,
            fn (int $id) => in_array($id, $allowed, true)
        ));
    }

    /**
     * @param  int[]  $ids
     * @return int[]
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0
        )));
    }
}
