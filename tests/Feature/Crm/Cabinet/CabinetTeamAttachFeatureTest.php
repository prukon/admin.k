<?php

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Location;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use App\Services\Users\CabinetTeamAttachService;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * ЛК: добавление группы через сайдбар (account.user.team.update).
 */
final class CabinetTeamAttachFeatureTest extends CrmTestCase
{
    private TeamUserSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = app(TeamUserSyncService::class);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_new_partner_roles_do_not_receive_account_user_team_update(): void
    {
        $partner = $this->partner;

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $roleId = (int) Role::query()->where('name', $roleName)->value('id');
            $has = DB::table('permission_role')
                ->where('partner_id', $partner->id)
                ->where('role_id', $roleId)
                ->where('permission_id', $this->permissionId('account.user.team.update'))
                ->exists();

            $this->assertFalse($has, "Роль {$roleName} не должна получать account.user.team.update по умолчанию");
        }
    }

    public function test_attach_without_permission_returns_403(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertForbidden();

        $this->assertFalse(
            DB::table('team_user')
                ->where('user_id', $student->id)
                ->where('team_id', $candidate->id)
                ->exists()
        );
        unset($currentTeam);
    }

    public function test_attach_adds_team_without_removing_existing(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $teamIds = $this->sync->teamIdsForStudent($student->fresh());
        $this->assertContains((int) $currentTeam->id, $teamIds);
        $this->assertContains((int) $candidate->id, $teamIds);
    }

    public function test_attach_rejects_team_from_other_location(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $otherLocation = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Другой объект',
        ]);
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $otherLocation->id,
            'title' => 'ЧужаяЛок',
            'is_enabled' => 1,
        ]);

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $foreignTeam->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->assertFalse(
            DB::table('team_user')
                ->where('user_id', $student->id)
                ->where('team_id', $foreignTeam->id)
                ->exists()
        );
        unset($currentTeam, $candidate);
    }

    public function test_attach_rejects_already_assigned_team(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $currentTeam->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        unset($candidate);
    }

    public function test_attach_rejects_foreign_partner_team(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $foreignLocation = Location::factory()->forPartner((int) $this->foreignPartner->id)->create();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'location_id' => $foreignLocation->id,
            'is_enabled' => 1,
        ]);

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $foreignTeam->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        unset($currentTeam, $candidate);
    }

    public function test_button_visible_with_permission_and_hidden_without(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('cabinet-attach-team-trigger', false)
            ->assertDontSee('cabinetAttachTeamModal', false);

        $this->grantPermission($student, 'account.user.team.update');
        $student->unsetRelation('role');

        $html = $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('cabinet-attach-team-trigger', $html);
        $this->assertStringContainsString('изменить', $html);
        $this->assertStringContainsString('cabinetAttachTeamModal', $html);
        $this->assertStringContainsString((string) $candidate->title, $html);
        unset($currentTeam);
    }

    public function test_button_hidden_when_student_has_no_location_bound_groups(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $this->grantPermission($student, 'account.user.team.update');

        $teamWithoutLocation = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
            'is_enabled' => 1,
        ]);
        $this->sync->attachTeamForStudent($student, (int) $teamWithoutLocation->id);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('cabinetAttachTeamModal', false);
    }

    public function test_family_context_attaches_to_active_sibling(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Иванов',
            'name' => 'Петя',
            'is_enabled' => 1,
        ]);
        $brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Иванов',
            'name' => 'Вася',
            'is_enabled' => 1,
        ]);

        $this->grantPermission($brother1, 'account.user.team.update');
        $this->grantPermission($brother1, 'dashboard.view');

        $location = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Семейный объект']);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'СемА',
            'is_enabled' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'СемБ',
            'is_enabled' => 1,
        ]);

        $this->sync->attachTeamForStudent($brother2, (int) $teamA->id);

        app(FamilyStudentContextService::class)->setActiveStudent($brother1, (int) $brother2->id);

        $this->actingAs($brother1)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed' => true,
                FamilyStudentContextService::SESSION_KEY => $brother2->id,
            ])
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $teamB->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertContains(
            (int) $teamB->id,
            $this->sync->teamIdsForStudent($brother2->fresh())
        );
        $this->assertNotContains(
            (int) $teamB->id,
            $this->sync->teamIdsForStudent($brother1->fresh())
        );
    }

    public function test_sidebar_context_includes_candidates_from_all_student_locations(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $this->grantPermission($student, 'account.user.team.update');

        $locA = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Объект А']);
        $locB = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Объект Б']);

        $teamA1 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locA->id,
            'title' => 'А1',
            'is_enabled' => 1,
        ]);
        $teamA2 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locA->id,
            'title' => 'А2',
            'is_enabled' => 1,
        ]);
        $teamB1 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locB->id,
            'title' => 'Б1',
            'is_enabled' => 1,
        ]);
        $teamB2 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locB->id,
            'title' => 'Б2',
            'is_enabled' => 1,
        ]);

        $this->sync->attachTeamForStudent($student, (int) $teamA1->id);
        $this->sync->attachTeamForStudent($student, (int) $teamB1->id);

        $ctx = app(CabinetTeamAttachService::class)->sidebarContext($student);
        $this->assertNotNull($ctx);
        $this->assertStringContainsString('Объект А', $ctx['locations_label']);
        $this->assertStringContainsString('Объект Б', $ctx['locations_label']);

        $availableIds = collect($ctx['available_by_location'])
            ->flatMap(fn (array $g) => collect($g['teams'])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $teamA2->id, $availableIds);
        $this->assertContains((int) $teamB2->id, $availableIds);
        $this->assertNotContains((int) $teamA1->id, $availableIds);
        $this->assertNotContains((int) $teamB1->id, $availableIds);
    }

    public function test_validation_error_when_team_id_missing(): void
    {
        [$student] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_ajax_success_returns_message_and_teams_label_contract(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $json = $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Группа добавлена.')
            ->json();

        $this->assertArrayHasKey('teams_label', $json);
        $this->assertStringContainsString((string) $currentTeam->title, (string) $json['teams_label']);
        $this->assertStringContainsString((string) $candidate->title, (string) $json['teams_label']);
    }

    public function test_ajax_rejects_disabled_team_with_team_id_error(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $candidate->is_enabled = false;
        $candidate->save();

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->assertFalse(
            DB::table('team_user')
                ->where('user_id', $student->id)
                ->where('team_id', $candidate->id)
                ->exists()
        );
        unset($currentTeam);
    }

    public function test_ajax_rejects_soft_deleted_team_with_team_id_error(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');
        $candidateId = (int) $candidate->id;
        $candidate->delete();

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidateId])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        unset($currentTeam);
    }

    public function test_student_without_eligible_groups_gets_field_error_on_attach(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $this->grantPermission($student, 'account.user.team.update');
        $this->grantPermission($student, 'dashboard.view');

        $orphanTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
            'is_enabled' => 1,
        ]);

        $response = $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $orphanTeam->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $message = (string) ($response->json('errors.team_id.0') ?? '');
        $this->assertNotSame('', $message);
    }

    /**
     * @return array{0: User, 1: Team, 2: Team}
     */
    private function makeStudentWithLocationTeams(): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Тестов',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $location = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тестовый объект',
        ]);

        $current = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'ТекГруппа',
            'is_enabled' => 1,
        ]);
        $candidate = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'НовГруппа',
            'is_enabled' => 1,
        ]);

        $this->sync->attachTeamForStudent($student, (int) $current->id);

        $this->grantPermission($student, 'dashboard.view');

        return [$student, $current, $candidate];
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
