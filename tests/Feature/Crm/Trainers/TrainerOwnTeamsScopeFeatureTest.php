<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\Team;
use App\Models\TrainerProfile;
use Illuminate\Support\Facades\Auth;

/**
 * Право groups.own: тренер видит только свои группы и учеников этих групп.
 */
final class TrainerOwnTeamsScopeFeatureTest extends TrainerOwnTeamsScopeTestCase
{

    public function test_guest_is_redirected_from_scoped_pages(): void
    {
        Auth::logout();

        foreach (['/admin/teams', '/admin/users', '/schedule', '/chat'] as $url) {
            $response = $this->get($url);
            $this->assertNotSame(500, $response->getStatusCode(), $url);
            $response->assertRedirect();
        }
        $this->assertGuest();
    }

    public function test_trainer_without_view_permissions_gets_403(): void
    {
        $this->makeRestrictedTrainer([]);

        $this->get('/admin/teams')->assertForbidden();
        $this->getJson('/admin/teams/data')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();
        $this->get('/schedule')->assertForbidden();
    }

    public function test_trainer_without_groups_own_sees_all_partner_teams_and_students(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
        ], $ownTeam);

        $teamIds = collect($this->getJson('/admin/teams/data')->assertOk()->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertContains((int) $ownTeam->id, $teamIds);
        $this->assertContains((int) $otherTeam->id, $teamIds);

        $userIds = $this->usersDataIds();
        $this->assertContains((int) $ownStudent->id, $userIds);
        $this->assertContains((int) $otherStudent->id, $userIds);

        $this->getJson('/admin/users/data?draw=1&start=0&length=50&team_id='.$otherTeam->id)
            ->assertOk();

        $journal = $this->get('/schedule')->assertOk();
        $journal->assertSee($ownStudent->lastname, false);
        $journal->assertSee($otherStudent->lastname, false);
    }

    public function test_trainer_with_groups_own_sees_only_own_teams_and_students(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
            'groups.own',
        ], $ownTeam);

        $this->get('/admin/teams')->assertOk();
        $this->get('/admin/users')->assertOk()->assertDontSee($otherTeam->title, false);
        $this->get('/schedule')->assertOk();

        $teamIds = collect($this->getJson('/admin/teams/data')->assertOk()->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertContains((int) $ownTeam->id, $teamIds);
        $this->assertNotContains((int) $otherTeam->id, $teamIds);

        $userIds = $this->usersDataIds();
        $this->assertContains((int) $ownStudent->id, $userIds);
        $this->assertNotContains((int) $otherStudent->id, $userIds);

        $this->getJson('/admin/users/data?draw=1&start=0&length=50&team_id='.$otherTeam->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->getJson(route('schedule.index', ['team' => $otherTeam->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team']);

        $journal = $this->get('/schedule')->assertOk();
        $journal->assertSee($ownStudent->lastname, false);
        $journal->assertDontSee($otherStudent->lastname, false);

        $this->getJson(route('admin.team.edit', $otherTeam->id))->assertForbidden();
        $this->patchJson('/admin/team/'.$otherTeam->id, [
            'title' => $otherTeam->title,
            'is_enabled' => 1,
        ])->assertForbidden();
    }

    public function test_student_in_two_teams_shows_only_own_group_label_and_merge_keeps_hidden(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OwnGroup_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OtherGroup_'.uniqid('', true),
        ]);
        $student = $this->makeStudent('BothGrp_'.uniqid('', true), [
            (int) $ownTeam->id,
            (int) $otherTeam->id,
        ]);

        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.own',
        ], $ownTeam);

        $row = collect($this->getJson('/admin/users/data?draw=1&start=0&length=100')->assertOk()->json('data'))
            ->firstWhere('id', $student->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString($ownTeam->title, (string) $row['teams']);
        $this->assertStringNotContainsString($otherTeam->title, (string) $row['teams']);

        $edit = $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->json('user');
        $this->assertEqualsCanonicalizing([(int) $ownTeam->id], array_map('intval', $edit['team_ids'] ?? []));

        $this->patchJson(route('admin.user.update', $student->id), [
            'team_ids' => [(int) $ownTeam->id],
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertDatabaseHas('team_user', [
            'user_id' => $student->id,
            'team_id' => $ownTeam->id,
            'partner_id' => $this->partner->id,
        ]);
        $this->assertDatabaseHas('team_user', [
            'user_id' => $student->id,
            'team_id' => $otherTeam->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_chat_without_team_filter_keeps_staff_and_hides_foreign_student(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $staffAdmin = $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Peer',
            'lastname' => 'ChatAd_'.uniqid('', true),
            'is_enabled' => 1,
        ]);
        $otherTrainer = $this->createUserWithRole('trainer', $this->partner, [
            'name' => 'Peer',
            'lastname' => 'ChatTr_'.uniqid('', true),
            'is_enabled' => 1,
        ]);
        TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $otherTrainer->id,
        ]);

        [$actor] = $this->makeRestrictedTrainer([
            'messages.view',
            'groups.own',
        ], $ownTeam);

        $page = $this->get(route('chat.index'))->assertOk();
        $page->assertSee($ownTeam->title, false);
        $page->assertDontSee($otherTeam->title, false);

        $ids = collect($this->getJson(route('chat.api.users'))->assertOk()->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $staffAdmin->id, $ids);
        $this->assertContains((int) $otherTrainer->id, $ids);
        $this->assertContains((int) $ownStudent->id, $ids);
        $this->assertNotContains((int) $otherStudent->id, $ids);
        $this->assertNotContains((int) $actor->id, $ids);

        $this->getJson(route('chat.api.users', ['team_id' => $otherTeam->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_admin_without_trainer_profile_is_not_restricted_by_groups_own(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();

        $this->grantToRole((int) $this->user->role_id, 'groups.own');
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $teamIds = collect($this->getJson('/admin/teams/data')->assertOk()->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertContains((int) $ownTeam->id, $teamIds);
        $this->assertContains((int) $otherTeam->id, $teamIds);

        $userIds = $this->usersDataIds();
        $this->assertContains((int) $ownStudent->id, $userIds);
        $this->assertContains((int) $otherStudent->id, $userIds);
    }

    public function test_groups_own_is_not_in_trainer_base_permissions(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertFalse($trainer->can('groups.own'));
        $this->assertDatabaseMissing('permission_role', [
            'partner_id' => $this->partner->id,
            'role_id' => $trainer->role_id,
            'permission_id' => $this->permissionId('groups.own'),
        ]);
    }

    public function test_cabinet_all_groups_lists_only_own_students_and_rejects_foreign_details(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'dashboard.view',
            'users.view',
            'groups.own',
        ], $ownTeam);

        $json = $this->getJson(route('getTeamDetails', ['teamName' => 'all']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $ids = collect($json['usersTeam'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $ownStudent->id, $ids);
        $this->assertNotContains((int) $otherStudent->id, $ids);

        $this->getJson(route('getTeamDetails', [
            'teamName' => $otherTeam->title,
            'teamId' => $otherTeam->id,
        ]))->assertOk()->assertJsonPath('success', false);

        $this->getJson(route('getUserDetails', ['userId' => $otherStudent->id]))
            ->assertOk()
            ->assertJsonPath('success', false);

        $this->getJson(route('getUserDetails', ['userId' => $ownStudent->id]))
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
