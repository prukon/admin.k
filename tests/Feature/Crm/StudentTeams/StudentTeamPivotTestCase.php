<?php

namespace Tests\Feature\Crm\StudentTeams;

use App\Models\Role;
use App\Models\LessonOccurrenceStatus;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

abstract class StudentTeamPivotTestCase extends CrmTestCase
{
    protected function grantPermissionForUser(User $user, string $permissionName, ?int $partnerId = null): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partnerId ?? (int) $user->partner_id,
            'role_id'       => (int) $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    protected function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * @param  list<Team>  $teams
     */
    protected function makeStudentWithTeams(array $teams, array $attributes = []): User
    {
        $firstTeam = $teams[0] ?? null;

        $user = User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
            'team_id'    => $firstTeam?->id,
        ], $attributes));

        $teamIds = collect($teams)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        app(TeamUserSyncService::class)->syncTeamsForStudent($user, $teamIds);

        return $user->fresh(['teams']);
    }

    protected function seedPartnerOccurrenceStatuses(?int $partnerId = null): void
    {
        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId ?? (int) $this->partner->id);
    }
}
