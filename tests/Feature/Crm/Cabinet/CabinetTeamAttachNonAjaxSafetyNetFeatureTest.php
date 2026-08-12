<?php

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для POST /cabinet/teams/attach (нативный submit формы без X-Requested-With).
 *
 * UX-баг до фикса: контроллер всегда отдавал JSON 200 → «белый экран»/сырой JSON в браузере.
 * Контракт: 302 redirect назад + запись в pivot; валидация → redirect с errors[team_id].
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/dashboard-cabinet.html#cabinet-attach-team
 */
final class CabinetTeamAttachNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Натив',
            'name' => 'Сабмит',
            'is_enabled' => 1,
        ]);

        $location = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Объект NonAjax',
        ]);
        $this->currentTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'NA-Current',
            'is_enabled' => 1,
        ]);
        $this->candidateTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'NA-Candidate',
            'is_enabled' => 1,
        ]);

        $this->sync->attachTeamForStudent($this->student, (int) $this->currentTeam->id);
        $this->grantPermission($this->student, 'dashboard.view');
        $this->grantPermission($this->student, 'account.user.team.update');
        $this->actingAs($this->student);
    }

    public function test_non_ajax_attach_redirects_and_persists_membership_not_empty_json_200(): void
    {
        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), [
                'team_id' => $this->candidateTeam->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');

        $this->assertTrue(
            DB::table('team_user')
                ->where('user_id', $this->student->id)
                ->where('team_id', $this->candidateTeam->id)
                ->exists()
        );
        $this->assertTrue(
            DB::table('team_user')
                ->where('user_id', $this->student->id)
                ->where('team_id', $this->currentTeam->id)
                ->exists(),
            'Существующая группа не должна сниматься'
        );
    }

    public function test_non_ajax_validation_failure_redirects_with_team_id_field_error_not_empty_200(): void
    {
        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), []);

        $this->assertContains($response->getStatusCode(), [302, 422]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['team_id']);
        } else {
            $response->assertJsonValidationErrors(['team_id']);
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_non_ajax_rejects_team_outside_student_locations_with_field_error(): void
    {
        $otherLocation = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Чужой объект NA',
        ]);
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $otherLocation->id,
            'title' => 'NA-Foreign',
            'is_enabled' => 1,
        ]);

        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), [
                'team_id' => $foreignTeam->id,
            ]);

        $this->assertContains($response->getStatusCode(), [302, 422]);
        $this->assertNotSame(200, $response->getStatusCode());

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['team_id']);
        } else {
            $response->assertJsonValidationErrors(['team_id']);
        }

        $this->assertFalse(
            DB::table('team_user')
                ->where('user_id', $this->student->id)
                ->where('team_id', $foreignTeam->id)
                ->exists()
        );
    }

    public function test_non_ajax_forbidden_without_permission(): void
    {
        // permission_role привязан к role_id: отдельная роль без account.user.team.update.
        $actor = $this->createUserWithoutPermission('account.user.team.update', $this->partner);

        $this->actingAs($actor)
            ->from(route('login'))
            ->post(route('cabinet.teams.attach'), [
                'team_id' => $this->candidateTeam->id,
            ])
            ->assertForbidden();

        $this->assertFalse(
            DB::table('team_user')
                ->where('user_id', $actor->id)
                ->where('team_id', $this->candidateTeam->id)
                ->exists()
        );
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
