<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\Team;

/**
 * AJAX-контракт groups.own: JSON 200/422, errors по полям, merge, auto-attach.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TrainerOwnTeamsAjaxContractFeatureTest extends TrainerOwnTeamsScopeTestCase
{
    public function test_ajax_users_filter_rejects_foreign_team_under_team_id(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['users.view', 'groups.own'], $ownTeam);

        $this->assertFieldError(
            $this->getJson('/admin/users/data?draw=1&start=0&length=10&team_id='.$otherTeam->id),
            'team_id'
        );
    }

    public function test_ajax_journal_rejects_foreign_team_under_team(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['schedule.view', 'groups.own'], $ownTeam);

        $this->assertFieldError(
            $this->getJson(route('schedule.index', ['team' => $otherTeam->id])),
            'team'
        );
    }

    public function test_ajax_chat_rejects_foreign_team_under_team_id(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['messages.view', 'groups.own'], $ownTeam);

        $this->assertFieldError(
            $this->getJson(route('chat.api.users', ['team_id' => $otherTeam->id])),
            'team_id'
        );
    }

    public function test_ajax_store_student_with_foreign_team_fails_under_team_ids(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.own',
        ], $ownTeam);

        $payload = $this->studentStorePayload(['team_ids' => [(int) $otherTeam->id]]);
        $this->assertFieldError(
            $this->postJson(route('admin.user.store'), $payload, $this->ajaxHeaders()),
            'team_ids.0'
        );
        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname' => $payload['lastname'],
        ]);
    }

    public function test_ajax_store_student_with_own_team_returns_user_json(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.own',
        ], $ownTeam);

        $payload = $this->studentStorePayload([
            'team_ids' => [(int) $ownTeam->id],
        ]);

        $response = $this->postJson(route('admin.user.store'), $payload, $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Клиент создан успешно');
        $this->assertNotEmpty($response->json('user.id'));
        $this->assertDatabaseHas('team_user', [
            'user_id' => (int) $response->json('user.id'),
            'team_id' => $ownTeam->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_ajax_patch_foreign_team_ids_fails_and_keeps_hidden_group(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OwnM_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OthM_'.uniqid('', true),
        ]);
        $student = $this->makeStudent('BothM_'.uniqid('', true), [
            (int) $ownTeam->id,
            (int) $otherTeam->id,
        ]);
        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.own',
        ], $ownTeam);

        $this->assertFieldError(
            $this->patchJson(route('admin.user.update', $student->id), [
                'team_ids' => [(int) $otherTeam->id],
            ], $this->ajaxHeaders()),
            'team_ids.0'
        );

        $this->assertDatabaseHas('team_user', [
            'user_id' => $student->id,
            'team_id' => $otherTeam->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_ajax_empty_team_ids_merge_keeps_hidden_groups(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OwnE_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OthE_'.uniqid('', true),
        ]);
        $student = $this->makeStudent('EmptyM_'.uniqid('', true), [
            (int) $ownTeam->id,
            (int) $otherTeam->id,
        ]);
        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.own',
        ], $ownTeam);

        $this->patchJson(route('admin.user.update', $student->id), [
            'team_ids' => [],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Клиент успешно обновлён');

        $this->assertDatabaseMissing('team_user', [
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

    public function test_ajax_students_without_group_filter_still_lists_ungrouped(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $ungrouped = $this->makeStudent('NoGrp_'.uniqid('', true), []);
        $this->makeRestrictedTrainer(['users.view', 'groups.own'], $ownTeam);

        $ids = collect($this->getJson('/admin/users/data?draw=1&start=0&length=100&team_id=none')
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $ungrouped->id, $ids);
    }

    public function test_ajax_create_team_attaches_trainer_and_returns_team_json(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        [, $profile] = $this->makeRestrictedTrainer([
            'groups.view',
            'groups.own',
        ], $ownTeam);

        $payload = $this->teamStorePayload();
        $response = $this->postJson(route('admin.team.store'), $payload, $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Группа создана успешно');

        $newId = (int) $response->json('team.id');
        $this->assertGreaterThan(0, $newId);
        $this->assertSame($payload['title'], $response->json('team.title'));
        $this->assertDatabaseHas('team_trainer', [
            'team_id' => $newId,
            'trainer_profile_id' => $profile->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_ajax_edit_student_returns_only_visible_team_ids(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OwnEd_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OthEd_'.uniqid('', true),
        ]);
        $student = $this->makeStudent('Edit_'.uniqid('', true), [
            (int) $ownTeam->id,
            (int) $otherTeam->id,
        ]);
        $this->makeRestrictedTrainer(['users.view', 'groups.own'], $ownTeam);

        $edit = $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders())
            ->assertOk()
            ->json('user');

        $this->assertEqualsCanonicalizing([(int) $ownTeam->id], array_map('intval', $edit['team_ids'] ?? []));
        $this->assertArrayHasKey('id', $edit);
    }

    public function test_ajax_journal_sync_rejects_foreign_team_and_empty_keeps_hidden(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OwnJ_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OthJ_'.uniqid('', true),
        ]);
        $student = $this->makeStudent('Jrn_'.uniqid('', true), [
            (int) $ownTeam->id,
            (int) $otherTeam->id,
        ]);
        $this->makeRestrictedTrainer(['schedule.view', 'groups.own'], $ownTeam);

        $this->assertFieldError(
            $this->postJson(route('user.sync.teams', $student), [
                'team_ids' => [(int) $otherTeam->id],
            ], $this->ajaxHeaders()),
            'team_ids.0'
        );

        $this->postJson(route('user.sync.teams', $student), [
            'team_ids' => [],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('team_user', [
            'user_id' => $student->id,
            'team_id' => $otherTeam->id,
            'partner_id' => $this->partner->id,
        ]);
        $this->assertDatabaseMissing('team_user', [
            'user_id' => $student->id,
            'team_id' => $ownTeam->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_ajax_cabinet_without_team_lists_ungrouped_and_rejects_foreign_team(): void
    {
        [$ownTeam, $otherTeam, $ownStudent] = $this->seedTwoTeamsAndStudents();
        $ungrouped = $this->makeStudent('CabNone_'.uniqid('', true), []);
        $this->makeRestrictedTrainer([
            'dashboard.view',
            'users.view',
            'groups.own',
        ], $ownTeam);

        $none = $this->getJson(route('getTeamDetails', ['teamName' => 'withoutTeam']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();
        $noneIds = collect($none['usersTeam'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $ungrouped->id, $noneIds);
        $this->assertNotContains((int) $ownStudent->id, $noneIds);

        $this->getJson(route('getTeamDetails', [
            'teamName' => $otherTeam->title,
            'teamId' => $otherTeam->id,
        ]))->assertOk()->assertJsonPath('success', false);

        $this->getJson(route('getUserDetails', ['userId' => $ungrouped->id]))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_ajax_chat_none_filter_keeps_staff_and_ungrouped_not_foreign_student(): void
    {
        [$ownTeam, , , $otherStudent] = $this->seedTwoTeamsAndStudents();
        $ungrouped = $this->makeStudent('ChatNone_'.uniqid('', true), []);
        $staffAdmin = $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Peer',
            'lastname' => 'ChatNoneAd_'.uniqid('', true),
            'is_enabled' => 1,
        ]);
        $this->makeRestrictedTrainer(['messages.view', 'groups.own'], $ownTeam);

        $ids = collect($this->getJson(route('chat.api.users', ['team_id' => 'none']))->assertOk()->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $staffAdmin->id, $ids);
        $this->assertContains((int) $ungrouped->id, $ids);
        $this->assertNotContains((int) $otherStudent->id, $ids);
    }

    public function test_ajax_payments_team_search_does_not_return_foreign_group(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'reports.view',
            'groups.own',
        ], $ownTeam);

        $ids = collect($this->getJson(route('reports.payments.teams.search', ['q' => '']))
            ->assertOk()
            ->json('results'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $ownTeam->id, $ids);
        $this->assertNotContains((int) $otherTeam->id, $ids);
    }

    public function test_ajax_store_team_validation_returns_title_error(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);

        $this->postJson(route('admin.team.store'), [
            'title' => '',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }
}
