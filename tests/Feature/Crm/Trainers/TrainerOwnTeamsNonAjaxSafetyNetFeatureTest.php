<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\Team;

/**
 * Backend safety-net: non-AJAX POST/PATCH/GET не пустой 200, запись создана/обновлена.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TrainerOwnTeamsNonAjaxSafetyNetFeatureTest extends TrainerOwnTeamsScopeTestCase
{
    public function test_native_post_creates_team_redirects_and_attaches_trainer(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        [, $profile] = $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);
        $payload = $this->teamStorePayload();

        $response = $this->from(route('admin.team.index'))
            ->post(route('admin.team.store'), $payload);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Create группы без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.team.index'));

        $team = Team::query()->where('title', $payload['title'])->first();
        $this->assertNotNull($team);
        $this->assertDatabaseHas('team_trainer', [
            'team_id' => $team->id,
            'trainer_profile_id' => $profile->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_native_post_team_validation_redirects_back_with_title_error(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);

        $this->from(route('admin.team.index'))
            ->post(route('admin.team.store'), ['title' => '', 'is_enabled' => 1])
            ->assertStatus(302)
            ->assertSessionHasErrors(['title']);
    }

    public function test_native_post_creates_student_in_own_group_and_redirects(): void
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

        $response = $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), $payload);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('admin.user1'));

        $student = \App\Models\User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', $payload['lastname'])
            ->first();
        $this->assertNotNull($student);
        $this->assertDatabaseHas('team_user', [
            'user_id' => $student->id,
            'team_id' => $ownTeam->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_native_post_student_with_foreign_team_redirects_back_with_field_error(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.own',
        ], $ownTeam);
        $payload = $this->studentStorePayload([
            'team_ids' => [(int) $otherTeam->id],
        ]);

        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), $payload)
            ->assertStatus(302)
            ->assertSessionHasErrors(['team_ids.0']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname' => $payload['lastname'],
        ]);
    }

    public function test_native_patch_user_teams_redirects_and_merges_hidden_groups(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OwnN_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OthN_'.uniqid('', true),
        ]);
        $student = $this->makeStudent('NatM_'.uniqid('', true), [
            (int) $ownTeam->id,
            (int) $otherTeam->id,
        ]);
        $this->makeRestrictedTrainer([
            'users.view',
            'users.group.update',
            'groups.own',
        ], $ownTeam);

        $response = $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student->id), [
                'team_ids' => [(int) $ownTeam->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'PATCH без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.user1'));

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

    public function test_native_journal_foreign_team_redirects_with_team_error_not_other_students(): void
    {
        [$ownTeam, $otherTeam, , $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['schedule.view', 'groups.own'], $ownTeam);

        $response = $this->from(route('schedule.index'))
            ->get(route('schedule.index', ['team' => $otherTeam->id]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Чужая группа журнала не должна открывать чужих учеников');
        $response->assertRedirect();
        $response->assertSessionHasErrors(['team']);
        $this->assertStringNotContainsString(
            $otherStudent->lastname,
            (string) $response->getContent()
        );
    }

    public function test_native_users_data_foreign_team_is_not_empty_ok_with_other_students(): void
    {
        [$ownTeam, $otherTeam, , $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['users.view', 'groups.own'], $ownTeam);

        $response = $this->from(route('admin.user1'))
            ->get('/admin/users/data?draw=1&start=0&length=50&team_id='.$otherTeam->id);

        $this->assertNotSame(500, $response->getStatusCode());
        if ($response->getStatusCode() === 200) {
            $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->assertNotContains((int) $otherStudent->id, $ids);
        } else {
            $this->assertContains($response->getStatusCode(), [302, 422]);
            if ($response->getStatusCode() === 302) {
                $response->assertSessionHasErrors(['team_id']);
            }
        }
    }

    public function test_native_chat_users_foreign_team_is_json_422_not_empty_ok(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['messages.view', 'groups.own'], $ownTeam);

        $response = $this->get(route('chat.api.users', ['team_id' => $otherTeam->id]));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 422]);
    }

    public function test_native_cabinet_details_stay_json_and_reject_foreign_student(): void
    {
        [$ownTeam, , $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'dashboard.view',
            'users.view',
            'groups.own',
        ], $ownTeam);

        $own = $this->get(route('getUserDetails', ['userId' => $ownStudent->id]));
        $this->assertNotSame(500, $own->getStatusCode());
        $own->assertOk()->assertJsonPath('success', true);

        $foreign = $this->get(route('getUserDetails', ['userId' => $otherStudent->id]));
        $this->assertNotSame(500, $foreign->getStatusCode());
        $foreign->assertOk()->assertJsonPath('success', false);
        $this->assertNotSame('', trim($foreign->getContent()));
    }

    public function test_native_journal_sync_teams_redirects_and_merges(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OwnS_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OthS_'.uniqid('', true),
        ]);
        $student = $this->makeStudent('SyncN_'.uniqid('', true), [
            (int) $ownTeam->id,
            (int) $otherTeam->id,
        ]);
        $this->makeRestrictedTrainer(['schedule.view', 'groups.own'], $ownTeam);

        $response = $this->from(route('schedule.index'))
            ->post(route('user.sync.teams', $student), [
                'team_ids' => [(int) $ownTeam->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('schedule.index'));

        $this->assertDatabaseHas('team_user', [
            'user_id' => $student->id,
            'team_id' => $otherTeam->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_native_delete_own_team_returns_json_message_not_empty_ok_without_body(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['groups.view', 'groups.own'], $ownTeam);

        $response = $this->delete(route('admin.team.delete', $ownTeam));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertNotSame('', (string) $response->json('message'));
        $this->assertSoftDeleted('teams', ['id' => $ownTeam->id]);
    }
}
