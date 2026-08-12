<?php

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный доступ / отказ к POST /cabinet/teams/attach и видимости UI.
 *
 * @see /docs/documentation/dashboard-cabinet.html#cabinet-attach-team
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class CabinetTeamAttachFullAccessFeatureTest extends CrmTestCase
{
    private TeamUserSyncService $sync;

    private User $student;

    private Team $currentTeam;

    private Team $candidateTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = app(TeamUserSyncService::class);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        [$this->student, $this->currentTeam, $this->candidateTeam] = $this->seedEligibleStudent();
    }

    public function test_guest_cannot_attach_team_or_open_cabinet_ui(): void
    {
        Auth::logout();

        $postJson = $this->postJson(route('cabinet.teams.attach'), [
            'team_id' => $this->candidateTeam->id,
        ]);
        $this->assertContains($postJson->getStatusCode(), [401, 302, 403, 419]);
        $this->assertNotSame(200, $postJson->getStatusCode());
        $this->assertNotSame(500, $postJson->getStatusCode());

        $postWeb = $this->from(route('login'))->post(route('cabinet.teams.attach'), [
            'team_id' => $this->candidateTeam->id,
        ]);
        $this->assertContains($postWeb->getStatusCode(), [401, 302, 403, 419]);
        $this->assertNotSame(200, $postWeb->getStatusCode());
        $this->assertNotSame(500, $postWeb->getStatusCode());

        $this->get(route('dashboard'))->assertRedirect();

        $this->assertFalse(
            DB::table('team_user')
                ->where('user_id', $this->student->id)
                ->where('team_id', $this->candidateTeam->id)
                ->exists()
        );
    }

    public function test_student_without_permission_gets_403_on_attach_and_does_not_see_button(): void
    {
        $this->actingAs($this->student);

        $this->postJson(route('cabinet.teams.attach'), [
            'team_id' => $this->candidateTeam->id,
        ])->assertForbidden();

        $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), [
                'team_id' => $this->candidateTeam->id,
            ])
            ->assertForbidden();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('cabinet-attach-team-trigger', $html);
        $this->assertStringNotContainsString('cabinetAttachTeamModal', $html);

        $this->assertFalse(
            DB::table('team_user')
                ->where('user_id', $this->student->id)
                ->where('team_id', $this->candidateTeam->id)
                ->exists()
        );
    }

    public function test_student_with_permission_can_attach_via_ajax_and_sees_button_on_cabinet(): void
    {
        $this->grantPermission($this->student, 'account.user.team.update');
        $this->actingAs($this->student);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('cabinet-attach-team-trigger', $html);
        $this->assertStringContainsString('Группа:', $html);
        $this->assertStringContainsString('изменить', $html);
        $this->assertStringContainsString('cabinetAttachTeamModal', $html);
        $this->assertStringContainsString((string) $this->candidateTeam->id, $html);

        $this->postJson(route('cabinet.teams.attach'), [
            'team_id' => $this->candidateTeam->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Группа добавлена.')
            ->assertJsonStructure(['success', 'message', 'teams_label']);

        $this->assertTrue(
            DB::table('team_user')
                ->where('user_id', $this->student->id)
                ->where('team_id', $this->candidateTeam->id)
                ->exists()
        );
    }

    public function test_attach_button_is_visible_on_non_cabinet_page_when_eligible(): void
    {
        $this->grantPermission($this->student, 'account.user.team.update');
        $this->grantPermission($this->student, 'myPayments.view');
        $this->actingAs($this->student);

        $html = $this->get(route('showUserPayments'))->assertOk()->getContent();

        $this->assertStringContainsString('cabinet-attach-team-trigger', $html);
        $this->assertStringContainsString('изменить', $html);
        $this->assertStringContainsString('cabinetAttachTeamModal', $html);
        $this->assertStringContainsString('/cabinet/teams/attach', $html);
    }

    public function test_admin_with_permission_does_not_see_button_and_cannot_attach(): void
    {
        $admin = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => 1,
        ]);
        $this->grantPermission($admin, 'account.user.team.update');
        $this->grantPermission($admin, 'dashboard.view');

        $this->actingAs($admin);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('cabinetAttachTeamModal', $html);

        $this->postJson(route('cabinet.teams.attach'), [
            'team_id' => $this->candidateTeam->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_endpoints_never_return_500_for_common_cases(): void
    {
        $this->grantPermission($this->student, 'account.user.team.update');
        $this->actingAs($this->student);

        $cases = [
            ['method' => 'POST', 'url' => route('cabinet.teams.attach'), 'data' => ['team_id' => $this->candidateTeam->id], 'json' => true],
            ['method' => 'POST', 'url' => route('cabinet.teams.attach'), 'data' => [], 'json' => true],
            ['method' => 'POST', 'url' => route('cabinet.teams.attach'), 'data' => ['team_id' => 0], 'json' => true],
            ['method' => 'POST', 'url' => route('cabinet.teams.attach'), 'data' => ['team_id' => $this->candidateTeam->id], 'json' => false],
            ['method' => 'GET', 'url' => route('dashboard'), 'data' => [], 'json' => false],
        ];

        foreach ($cases as $case) {
            if ($case['json']) {
                $response = $this->json($case['method'], $case['url'], $case['data']);
            } else {
                $response = $this->call($case['method'], $case['url'], $case['data']);
            }

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                "{$case['method']} {$case['url']} не должен отдавать 500"
            );
            $this->assertNotSame('', trim((string) $response->getContent()), 'Ответ не должен быть пустым');
        }
    }

    /**
     * @return array{0: User, 1: Team, 2: Team}
     */
    private function seedEligibleStudent(): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Полный',
            'name' => 'Доступ',
            'is_enabled' => 1,
        ]);

        $location = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Объект FullAccess',
        ]);
        $current = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'FA-Current',
            'is_enabled' => 1,
        ]);
        $candidate = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'FA-Candidate',
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
