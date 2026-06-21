<?php

namespace Tests\Feature\Crm\StudentTeams;

use App\Models\Payment;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pivot team_user: поведение во всех затронутых разделах CRM.
 */
final class StudentTeamPivotFeatureTest extends StudentTeamPivotTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();
    }

    public function test_admin_users_data_teams_column_lists_all_groups_comma_separated(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Col-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Col-B']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB], [
            'name'     => 'Колонка',
            'lastname' => 'Группы',
        ]);

        $json = $this->getJson('/admin/users/data?id=' . $student->id . '&draw=1')
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $student->id);
        $this->assertNotNull($row);

        $teamsLabel = html_entity_decode((string) ($row['teams'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Col-A', $teamsLabel);
        $this->assertStringContainsString('Col-B', $teamsLabel);
    }

    public function test_store_rejects_foreign_partner_team_ids(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $this->postJson('/admin/users', [
            'name'       => 'Чужая',
            'lastname'   => 'Группа',
            'email'      => 'foreign-team-' . uniqid('', true) . '@example.com',
            'role_id'    => $role->id,
            'team_ids'   => [$foreignTeam->id],
            'birthday'   => '2015-01-01',
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0']);
    }

    public function test_sync_teams_rejects_foreign_partner_team_ids(): void
    {
        $this->seedGlobalScheduleStatuses();

        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->grantPermissionForUser($actor, 'schedule.view');
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $student = $this->makeStudentWithTeams([$team]);

        $this->postJson(route('user.sync.teams', $student), [
            'team_ids' => [$foreignTeam->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0']);
    }

    public function test_schedule_index_shows_comma_separated_teams_under_student_name(): void
    {
        $this->withoutVite();
        $this->seedGlobalScheduleStatuses();

        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->grantPermissionForUser($actor, 'schedule.view');
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Журнал-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Журнал-B']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB], [
            'name'     => 'Журнал',
            'lastname' => 'Ученик',
        ]);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '06']))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertSee('Журнал-A', false)
            ->assertSee('Журнал-B', false);
    }

    public function test_user_schedule_info_returns_all_team_titles(): void
    {
        $this->seedGlobalScheduleStatuses();

        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->grantPermissionForUser($actor, 'schedule.view');
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Sched-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Sched-B']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB]);

        $response = $this->getJson(route('user.schedule.info', $student))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEqualsCanonicalizing(
            [$teamA->id, $teamB->id],
            $response->json('user.team_ids')
        );

        $titles = (string) $response->json('user.team_titles');
        $this->assertStringContainsString('Sched-A', $titles);
        $this->assertStringContainsString('Sched-B', $titles);
    }

    public function test_setting_prices_users_tab_lists_student_with_multiple_teams(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Price-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Price-B']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB], [
            'name'     => 'Цена',
            'lastname' => 'Ученик',
        ]);

        $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->assertSee('Price-A', false)
            ->assertSee('Price-B', false);
    }

    public function test_my_group_data_returns_teams_select_and_filters_peers_by_team_id(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'My-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'My-B']);

        $student = $this->makeStudentWithTeams([$teamA, $teamB], ['name' => 'ЯУченик']);
        $peerA = $this->makeStudentWithTeams([$teamA], ['name' => 'PeerA']);
        $peerB = $this->makeStudentWithTeams([$teamB], ['name' => 'PeerB']);

        $this->actingAs($student);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $default = $this->getJson(route('my-group.data'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'teams')
            ->json();

        $activeTeamId = (int) ($default['active_team_id'] ?? 0);
        $this->assertContains($activeTeamId, [$teamA->id, $teamB->id]);

        $peerIdsDefault = collect($default['peers'])->pluck('id')->all();
        if ($activeTeamId === $teamA->id) {
            $this->assertContains($peerA->id, $peerIdsDefault);
            $this->assertNotContains($peerB->id, $peerIdsDefault);
        } else {
            $this->assertContains($peerB->id, $peerIdsDefault);
            $this->assertNotContains($peerA->id, $peerIdsDefault);
        }

        $peerIdsTeamB = collect(
            $this->getJson(route('my-group.data', ['team_id' => $teamB->id]))->json('peers')
        )->pluck('id')->all();

        $this->assertContains($peerB->id, $peerIdsTeamB);
        $this->assertNotContains($peerA->id, $peerIdsTeamB);
    }

    public function test_account_user_edit_shows_read_only_groups_from_pivot(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LK-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LK-B']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB]);

        $this->actingAs($student);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionForUser($student, 'account.user.view');

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('Группы', false)
            ->assertSee('LK-A, LK-B', false)
            ->assertDontSee('name="team_ids[]"', false);
    }

    public function test_chat_users_api_returns_comma_separated_team_title(): void
    {
        config(['broadcasting.default' => 'null']);
        $this->grantPermissionForUser($this->user, 'messages.view');

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Chat-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Chat-B']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB], ['name' => 'ChatStudent_' . uniqid('', true)]);

        $json = $this->getJson('/chat/api/users?q=' . urlencode($student->name))
            ->assertOk()
            ->json();

        $match = collect($json)->firstWhere('id', $student->id);
        $this->assertNotNull($match);

        $teamTitle = (string) ($match['team_title'] ?? '');
        $this->assertStringContainsString('Chat-A', $teamTitle);
        $this->assertStringContainsString('Chat-B', $teamTitle);
    }

    public function test_payment_report_team_title_lists_all_groups_without_duplicate_rows(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Pay-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Pay-B']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB]);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'summ'    => 2000,
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payments.getPayments', ['filter_user_id' => $student->id]))
            ->assertOk()
            ->json();

        $matches = collect($json['data'] ?? [])->where('id', $payment->id);
        $this->assertCount(1, $matches);

        $teamTitle = html_entity_decode((string) ($matches->first()['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Pay-A', $teamTitle);
        $this->assertStringContainsString('Pay-B', $teamTitle);
    }

    public function test_dashboard_without_teams_query_excludes_students_with_pivot_groups(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudentWithTeams([$team], [
            'team_id'    => null,
            'name'       => 'БезLegacy',
            'lastname'   => 'СГруппой',
            'is_enabled' => 1,
        ]);

        DB::table('team_user')->where('user_id', $student->id)->delete();
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'user_id'    => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $json = $this->getJson(route('getTeamDetails', [
            'teamName' => 'withoutTeam',
        ]))->assertOk()->json();

        $ids = collect($json['usersTeam'] ?? [])->pluck('id')->all();
        $this->assertNotContains($student->id, $ids);
    }
}
