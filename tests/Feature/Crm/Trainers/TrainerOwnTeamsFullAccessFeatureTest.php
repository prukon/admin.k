<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

/**
 * Полный смоук затронутых endpoint’ов при groups.own: не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TrainerOwnTeamsFullAccessFeatureTest extends TrainerOwnTeamsScopeTestCase
{
    public function test_trainer_with_rights_gets_non_empty_success_on_scoped_endpoints(): void
    {
        [$ownTeam, $otherTeam, $ownStudent] = $this->seedTwoTeamsAndStudents();
        $ungrouped = $this->makeStudent('FullNone_'.uniqid('', true), []);
        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.view',
            'schedule.view',
            'messages.view',
            'dashboard.view',
            'groups.own',
        ], $ownTeam);

        foreach ([
            ['GET', '/admin/teams'],
            ['GET', '/admin/teams/data'],
            ['GET', '/admin/users'],
            ['GET', '/admin/users/data?draw=1&start=0&length=10'],
            ['GET', '/schedule'],
            ['GET', route('schedule.index', ['team' => 'all'])],
            ['GET', route('schedule.index', ['team' => 'none'])],
            ['GET', route('schedule.index', ['team' => $ownTeam->id])],
            ['GET', '/chat'],
            ['GET', route('chat.api.users')],
            ['GET', route('chat.api.users', ['team_id' => 'none'])],
            ['GET', '/cabinet'],
            ['GET', route('getTeamDetails', ['teamName' => 'all'])],
            ['GET', route('getTeamDetails', ['teamName' => 'withoutTeam'])],
            ['GET', route('getUserDetails', ['userId' => $ownStudent->id])],
            ['GET', route('getUserDetails', ['userId' => $ungrouped->id])],
        ] as $item) {
            $response = $this->get($item[1]);
            $this->assertSame(200, $response->getStatusCode(), $item[1]);
            $this->assertNotSame('', trim($response->getContent()), $item[1]);
        }

        $this->getJson(route('admin.team.edit', $ownTeam->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', $ownTeam->id);

        $this->getJson(route('admin.user.edit', $ownStudent->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('user.id', $ownStudent->id);

        $this->getJson(route('admin.team.edit', $otherTeam->id), $this->ajaxHeaders())->assertForbidden();
        $this->deleteJson(route('admin.team.delete', $otherTeam), [], $this->ajaxHeaders())->assertForbidden();
        $this->assertDatabaseHas('teams', [
            'id' => $otherTeam->id,
            'deleted_at' => null,
        ]);
    }

    public function test_ajax_create_own_team_then_it_appears_in_teams_data(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);

        $payload = $this->teamStorePayload();
        $created = $this->postJson(route('admin.team.store'), $payload, $this->ajaxHeaders())
            ->assertOk()
            ->json('team.id');

        $this->assertContains((int) $created, $this->teamsDataIds());
    }

    public function test_delete_own_team_succeeds_and_foreign_stays_forbidden(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);

        $this->deleteJson(route('admin.team.delete', $ownTeam), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message']);
        $this->assertSoftDeleted('teams', ['id' => $ownTeam->id]);

        $this->deleteJson(route('admin.team.delete', $otherTeam), [], $this->ajaxHeaders())->assertForbidden();
        $this->assertDatabaseHas('teams', [
            'id' => $otherTeam->id,
            'deleted_at' => null,
        ]);
    }

    public function test_patch_own_team_json_updates_title(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);
        $title = 'Renamed_'.uniqid('', true);

        $this->patchJson('/admin/team/'.$ownTeam->id, [
            'title' => $title,
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Группа успешно обновлена');

        $this->assertSame($title, $ownTeam->fresh()->title);
    }
}
