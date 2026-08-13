<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\DB;

trait FamilyStudentPaymentFixtures
{
    private ParentProfile $sharedParent;

    private User $brother1;

    private User $brother2;

    private function createSiblingStudents(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $this->sharedParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванова',
            'firstname' => 'Мария',
            'email' => 'mama@family.test',
        ]);

        $this->brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'parent_id' => $this->sharedParent->id,
            'lastname' => 'Иванов',
            'name' => 'Петя',
            'is_enabled' => true,
        ]);

        $this->brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'parent_id' => $this->sharedParent->id,
            'lastname' => 'Иванов',
            'name' => 'Вася',
            'is_enabled' => true,
        ]);
    }

    private function makeTeam(string $title): Team
    {
        return Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => $title,
        ]);
    }

    private function attachStudent(User $student, Team $team): void
    {
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
    }

    private function switchTo(User $student): void
    {
        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $student->id,
        ])->assertRedirect();

        $this->assertSame($student->id, session(FamilyStudentContextService::SESSION_KEY));
    }

    /**
     * @return array<string, int|string>
     */
    private function monthlyPayload(int $teamId, string $month): array
    {
        return [
            'paymentDate' => 'Сентябрь 2026',
            'formatedPaymentDate' => $month,
            'team_id' => $teamId,
            'outSum' => '1.00',
        ];
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
