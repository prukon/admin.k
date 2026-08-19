<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatThread;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeamGroupChatService
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly ChatSupportIdentity $support,
    ) {
    }

    public function ensureThreadForTeam(Team $team): ?ChatThread
    {
        if (! $this->isReady()) {
            return null;
        }

        $teamId = (int) $team->id;
        if ($teamId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($team, $teamId) {
            $locked = Team::query()->whereKey($teamId)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            $thread = ChatThread::query()->where('team_id', $teamId)->first();
            if ($thread) {
                return $thread;
            }

            $payload = [
                'subject' => $this->titleFromTeam($locked),
                'team_id' => $teamId,
            ];
            if (Schema::hasColumn('threads', 'is_group')) {
                $payload['is_group'] = true;
            }

            $thread = ChatThread::query()->create($payload);
            $this->chat->addUsersToGroupThread($thread, $this->seedMemberIds($locked));

            return $thread;
        });
    }

    public function addUserToTeamChat(Team $team, User $user): void
    {
        if (! $this->userCanJoinChats($user)) {
            return;
        }

        $thread = $this->ensureThreadForTeam($team);
        if (! $thread) {
            return;
        }

        $this->chat->addUsersToGroupThread($thread, [(int) $user->id]);
    }

    public function backfillMissing(): int
    {
        if (! $this->isReady()) {
            return 0;
        }

        $created = 0;
        Team::query()
            ->orderBy('id')
            ->each(function (Team $team) use (&$created): void {
                $existed = ChatThread::query()->where('team_id', (int) $team->id)->exists();
                $thread = $this->ensureThreadForTeam($team);
                if ($thread && ! $existed) {
                    $created++;
                }
            });

        return $created;
    }

    public function syncUserAfterSave(User $user): void
    {
        if (! $this->isReady()) {
            return;
        }

        $enabledNow = $this->userIsEnabled($user);
        $enabledTouched = $user->wasRecentlyCreated || $user->wasChanged('is_enabled');
        $roleTouched = $user->wasRecentlyCreated || $user->wasChanged('role_id');

        if ($enabledTouched && ! $enabledNow) {
            $wasSupport = $this->support->isSupportUser($user);
            $this->chat->removeUserFromAllThreads((int) $user->id);
            if ($wasSupport) {
                $this->ensureCanonicalSupportInTeamChats();
            }

            return;
        }

        if (! $enabledNow) {
            return;
        }

        if (! $enabledTouched && ! $roleTouched) {
            return;
        }

        $this->addEnabledUserToEligibleTeamChats($user);
    }

    public function addEnabledUserToEligibleTeamChats(User $user): void
    {
        if (! $this->userCanJoinChats($user)) {
            return;
        }

        $user->load('role');
        $roleName = (string) ($user->role?->name ?? '');

        if ($roleName === 'superadmin') {
            if ($this->support->isCanonicalUserId((int) $user->id)) {
                $this->addUserToTeamChats($user, null);
            }

            return;
        }

        if ($roleName === 'admin') {
            $partnerId = (int) ($user->partner_id ?? 0);
            if ($partnerId > 0) {
                $this->addUserToTeamChats($user, $partnerId);
            }

            return;
        }

        foreach ($this->eligibleTeamIdsForUser($user, $roleName) as $teamId) {
            $team = Team::query()->whereKey($teamId)->first();
            if ($team) {
                $this->addUserToTeamChat($team, $user);
            }
        }
    }

    private function addUserToTeamChats(User $user, ?int $partnerId): void
    {
        $query = ChatThread::query()->whereNotNull('team_id');
        if ($partnerId !== null) {
            $teamIds = Team::query()
                ->where('partner_id', $partnerId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($teamIds === []) {
                return;
            }
            $query->whereIn('team_id', $teamIds);
        }

        $query->orderBy('id')->each(function (ChatThread $thread) use ($user): void {
            $this->chat->addUsersToGroupThread($thread, [(int) $user->id]);
        });
    }

    /**
     * @return list<int>
     */
    private function eligibleTeamIdsForUser(User $user, string $roleName): array
    {
        $userId = (int) $user->id;
        $partnerId = (int) ($user->partner_id ?? 0);

        if ($roleName === 'trainer') {
            $profileId = TrainerProfile::query()
                ->where('user_id', $userId)
                ->value('id');
            if (! $profileId) {
                return [];
            }

            return DB::table('team_trainer')
                ->where('trainer_profile_id', (int) $profileId)
                ->when($partnerId > 0, fn ($q) => $q->where('partner_id', $partnerId))
                ->pluck('team_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if ($roleName !== 'user' || $partnerId <= 0) {
            return [];
        }

        return DB::table('team_user')
            ->where('user_id', $userId)
            ->where('partner_id', $partnerId)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function seedMemberIds(Team $team): array
    {
        $ids = array_merge(
            $this->enabledAdminIds((int) $team->partner_id),
            $this->enabledSuperadminIds(),
            $this->enabledTrainerUserIds((int) $team->id),
            $this->enabledStudentIds((int) $team->id, (int) $team->partner_id),
        );

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0
        )));
    }

    /**
     * @return list<int>
     */
    private function enabledAdminIds(int $partnerId): array
    {
        $adminRoleId = $this->adminRoleId();
        if ($adminRoleId === null || $partnerId <= 0) {
            return [];
        }

        return User::query()
            ->where('partner_id', $partnerId)
            ->where('role_id', $adminRoleId)
            ->where('is_enabled', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function enabledSuperadminIds(): array
    {
        $id = $this->support->canonicalUserId();

        return $id !== null ? [$id] : [];
    }

    private function ensureCanonicalSupportInTeamChats(): void
    {
        $canonical = $this->support->canonicalUser();
        if (! $canonical || ! $this->userCanJoinChats($canonical)) {
            return;
        }

        $this->addUserToTeamChats($canonical, null);
    }

    /**
     * @return list<int>
     */
    private function enabledTrainerUserIds(int $teamId): array
    {
        return DB::table('team_trainer')
            ->join('trainer_profiles', 'trainer_profiles.id', '=', 'team_trainer.trainer_profile_id')
            ->join('users', 'users.id', '=', 'trainer_profiles.user_id')
            ->where('team_trainer.team_id', $teamId)
            ->where('users.is_enabled', 1)
            ->whereNull('users.deleted_at')
            ->whereNull('trainer_profiles.deleted_at')
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function enabledStudentIds(int $teamId, int $partnerId): array
    {
        return DB::table('team_user')
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $teamId)
            ->where('team_user.partner_id', $partnerId)
            ->where('users.is_enabled', 1)
            ->whereNull('users.deleted_at')
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function titleFromTeam(Team $team): string
    {
        $title = trim((string) ($team->title ?? ''));
        if ($title === '') {
            $title = 'Группа';
        }

        return mb_substr($title, 0, 100);
    }

    private function userCanJoinChats(User $user): bool
    {
        return $this->userIsEnabled($user) && $user->deleted_at === null;
    }

    private function userIsEnabled(User $user): bool
    {
        return (int) $user->is_enabled === 1;
    }

    private function adminRoleId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $resolved = (int) Role::query()->where('name', 'admin')->value('id');
            $id = $resolved > 0 ? $resolved : 0;
        }

        return $id > 0 ? $id : null;
    }

    private function isReady(): bool
    {
        return Schema::hasTable('threads') && Schema::hasColumn('threads', 'team_id');
    }
}
