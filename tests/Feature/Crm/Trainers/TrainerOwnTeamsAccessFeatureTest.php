<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к scoped-разделам при groups.own: гость, без прав, без права, с правом, без привязок.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TrainerOwnTeamsAccessFeatureTest extends TrainerOwnTeamsScopeTestCase
{
    public function test_guest_is_redirected_from_html_pages_and_not_given_empty_ok(): void
    {
        Auth::logout();

        foreach ([
            '/admin/teams',
            '/admin/users',
            '/schedule',
            '/chat',
            '/cabinet',
        ] as $url) {
            $response = $this->get($url);
            $this->assertDeniedNotEmptyOk($response, 'GET '.$url);
            $response->assertRedirect();
        }
        $this->assertGuest();
    }

    public function test_guest_json_requests_are_denied_and_not_server_error(): void
    {
        Auth::logout();
        [$ownTeam] = $this->seedTwoTeamsAndStudents();

        foreach ([
            ['GET', '/admin/teams/data'],
            ['GET', '/admin/users/data?draw=1&start=0&length=10'],
            ['GET', route('admin.team.edit', $ownTeam->id)],
            ['POST', route('admin.team.store'), $this->teamStorePayload()],
            ['PATCH', '/admin/team/'.$ownTeam->id, ['title' => $ownTeam->title, 'is_enabled' => 1]],
            ['DELETE', route('admin.team.delete', $ownTeam)],
            ['GET', route('chat.api.users')],
            ['GET', route('getTeamDetails', ['teamName' => 'all'])],
            ['GET', route('getUserDetails', ['userId' => 1])],
            ['GET', route('schedule.index', ['team' => $ownTeam->id])],
        ] as $item) {
            $response = $this->json($item[0], $item[1], $item[2] ?? []);
            $this->assertDeniedNotEmptyOk($response, 'гость JSON '.$item[0].' '.$item[1]);
        }
        $this->assertGuest();
    }

    public function test_trainer_without_view_permissions_gets_403_on_web_and_json(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        [$trainer] = $this->makeRestrictedTrainer(['groups.own'], $ownTeam);
        $this->revokeFromRole((int) $trainer->role_id, 'messages.view');
        $this->revokeFromRole((int) $trainer->role_id, 'dashboard.view');
        $this->actingAs($trainer->fresh());

        $this->get('/admin/teams')->assertForbidden();
        $this->getJson('/admin/teams/data')->assertForbidden();
        $this->postJson(route('admin.team.store'), $this->teamStorePayload(), $this->ajaxHeaders())->assertForbidden();
        $this->patchJson('/admin/team/'.$ownTeam->id, [
            'title' => $ownTeam->title,
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertForbidden();
        $this->deleteJson(route('admin.team.delete', $ownTeam), [], $this->ajaxHeaders())->assertForbidden();

        $this->get('/admin/users')->assertForbidden();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();
        $this->postJson(route('admin.user.store'), $this->studentStorePayload(), $this->ajaxHeaders())->assertForbidden();

        $this->get('/schedule')->assertForbidden();
        $this->getJson(route('schedule.index'))->assertForbidden();
        $this->get('/chat')->assertForbidden();
        $this->getJson(route('chat.api.users'))->assertForbidden();
        $this->get('/cabinet')->assertForbidden();
        $this->getJson(route('getTeamDetails', ['teamName' => 'all']))->assertForbidden();
    }

    public function test_trainer_with_view_but_without_groups_own_still_opens_pages(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
            'messages.view',
            'dashboard.view',
        ], $ownTeam);

        foreach (['/admin/teams', '/admin/users', '/schedule', '/chat', '/cabinet'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson('/admin/teams/data')->assertOk();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson(route('chat.api.users'))->assertOk();
        $this->getJson(route('getTeamDetails', ['teamName' => 'all']))->assertOk();
    }

    public function test_trainer_with_groups_own_and_view_opens_scoped_pages(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
            'messages.view',
            'dashboard.view',
            'groups.own',
        ], $ownTeam);

        foreach (['/admin/teams', '/admin/users', '/schedule', '/chat', '/cabinet'] as $url) {
            $page = $this->get($url)->assertOk();
            $this->assertNotSame('', trim($page->getContent()), $url);
        }
    }

    public function test_trainer_with_groups_own_and_no_linked_teams_sees_empty_lists_not_all_school_teams(): void
    {
        [, $otherTeam, , $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
            'messages.view',
            'dashboard.view',
            'groups.own',
        ], null);

        $this->assertSame([], $this->teamsDataIds(), 'Без team_trainer список групп должен быть пустым');
        $this->assertSame([], $this->usersDataIds(), 'Без своих групп список учеников должен быть пустым');

        $users = $this->get('/admin/users')->assertOk();
        $users->assertDontSee($otherTeam->title, false);

        $journal = $this->get('/schedule')->assertOk();
        $journal->assertDontSee($otherStudent->lastname, false);
        $journal->assertDontSee($otherTeam->title, false);

        $chat = $this->get('/chat')->assertOk();
        $chat->assertDontSee($otherTeam->title, false);

        $cabinet = $this->get('/cabinet')->assertOk();
        $cabinet->assertDontSee($otherTeam->title, false);
        $cabinet->assertDontSee($otherStudent->lastname, false);
    }

    public function test_foreign_partner_team_is_rejected_and_does_not_leak(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'ForeignGrp_'.uniqid('', true),
        ]);
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
            'messages.view',
            'groups.own',
        ], $ownTeam);

        $this->assertNotContains((int) $foreignTeam->id, $this->teamsDataIds());

        $this->getJson('/admin/users/data?draw=1&start=0&length=10&team_id='.$foreignTeam->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $edit = $this->getJson(route('admin.team.edit', $foreignTeam->id));
        $this->assertContains($edit->getStatusCode(), [403, 404], 'чужая организация: карточка группы');
        $this->getJson(route('chat.api.users', ['team_id' => $foreignTeam->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_trainer_without_partner_is_logged_out_from_teams(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        [$trainer] = $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);
        $trainer->partner_id = null;
        $trainer->save();

        $this->actingAs($trainer);
        $this->withSession([
            'current_partner' => null,
            '2fa:passed' => true,
        ]);

        $response = $this->get('/admin/teams');
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'Ваша организация недоступна.']);
    }

    public function test_wrong_http_methods_are_not_server_error_or_empty_ok(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
            'messages.view',
            'dashboard.view',
            'groups.own',
        ], $ownTeam);

        foreach ([
            ['PUT', '/admin/teams'],
            ['DELETE', '/admin/teams'],
            ['PATCH', '/admin/teams/data'],
            ['POST', '/admin/teams/data'],
            ['GET', route('admin.team.store')],
            ['PUT', '/admin/users'],
            ['DELETE', '/schedule'],
            ['POST', route('chat.api.users')],
            ['PUT', route('getTeamDetails')],
            ['DELETE', route('getUserDetails')],
        ] as $item) {
            $response = $this->call($item[0], $item[1]);
            $this->assertNotSame(500, $response->getStatusCode(), $item[0].' '.$item[1]);
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame('', trim($response->getContent()), $item[0].' '.$item[1]);
            }
            $this->assertContains(
                $response->getStatusCode(),
                [200, 302, 401, 403, 404, 405, 419, 422],
                $item[0].' '.$item[1]
            );
        }
    }
}
