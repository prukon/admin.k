<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\ScheduleUser;
use App\Models\Team;
use App\Models\User;

final class ScheduleCellTrainerFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_cell_context_returns_team_default_trainer_for_visited_without_saved_row(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-05-10',
        ]))
            ->assertOk()
            ->assertJsonPath('team_default_trainer_profile_id', $trainer->id)
            ->assertJsonPath('trainer_profile_id_for_select', null)
            ->assertJsonPath('visited_status_id', $this->visitedStatusId);
    }

    public function test_cell_context_returns_trainers_list_for_partner(): void
    {
        [$student, , $trainerA] = $this->makeStudentTeamAndTrainer('Тренер А');
        $trainerB = $this->makeTrainerProfile('Тренер Б');

        $response = $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-05-10',
        ]))->assertOk();

        $ids = collect($response->json('trainers'))->pluck('id')->all();
        $this->assertContains($trainerA->id, $ids);
        $this->assertContains($trainerB->id, $ids);
    }

    public function test_cell_context_without_team_has_null_team_default(): void
    {
        $student = $this->makeStudent(null);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-05-10',
        ]))
            ->assertOk()
            ->assertJsonPath('team_default_trainer_profile_id', null)
            ->assertJsonPath('team_id', null);
    }

    public function test_cell_context_returns_404_for_foreign_partner_student(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => (int) \App\Models\Role::query()->where('name', 'user')->value('id'),
        ]);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $foreignStudent->id,
            'date' => '2026-05-10',
        ]))->assertNotFound();
    }

    public function test_cell_context_validation_requires_user_and_date(): void
    {
        $this->getJson('/schedule/cell-context')->assertStatus(422);
        $this->getJson('/schedule/cell-context?user_id=1')->assertStatus(422);
    }

    public function test_update_visited_saves_trainer_and_without_trainer(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-11';

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'description' => '',
            'trainer_profile_id' => $trainer->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('schedule_users', [
            'user_id' => $student->id,
            'date' => $date . ' 00:00:00',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => $date,
        ]))
            ->assertOk()
            ->assertJsonPath('trainer_profile_id_for_select', (string) $trainer->id)
            ->assertJsonPath('current_status_id', $this->visitedStatusId);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'description' => '',
            'trainer_profile_id' => '',
        ])->assertOk();

        $this->assertDatabaseHas('schedule_users', [
            'user_id' => $student->id,
            'date' => $date . ' 00:00:00',
            'trainer_profile_id' => null,
        ]);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => $date,
        ]))
            ->assertOk()
            ->assertJsonPath('trainer_profile_id_for_select', '');
    }

    public function test_update_accepts_none_and_zero_as_without_trainer(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-13';

        foreach (['none', '0', ''] as $rawValue) {
            $this->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $rawValue,
            ])->assertOk();
        }

        $this->assertDatabaseHas('schedule_users', [
            'user_id' => $student->id,
            'date' => $date . ' 00:00:00',
            'trainer_profile_id' => null,
        ]);
    }

    public function test_update_rejects_trainer_from_foreign_partner(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();
        $foreignTrainer = $this->makeTrainerProfile('Чужой тренер', $this->foreignPartner->id);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => '2026-05-14',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $foreignTrainer->id,
        ])->assertStatus(422);
    }

    public function test_update_returns_404_for_foreign_partner_student(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => (int) \App\Models\Role::query()->where('name', 'user')->value('id'),
        ]);

        $this->postJson(route('schedule.update'), [
            'user_id' => $foreignStudent->id,
            'date' => '2026-05-14',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertNotFound();
    }

    public function test_non_visited_status_clears_trainer_profile_id(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-12';

        $otherStatus = $this->createCustomOccurrenceStatus('Болезнь');

        ScheduleUser::query()->create([
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $otherStatus->id,
            'description' => '',
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->assertDatabaseHas('schedule_users', [
            'user_id' => $student->id,
            'date' => $date . ' 00:00:00',
            'lesson_occurrence_status_id' => $otherStatus->id,
            'trainer_profile_id' => null,
        ]);
    }

    public function test_update_visited_saves_description(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-16';

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'description' => 'Был на тренировке',
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->assertDatabaseHas('schedule_users', [
            'user_id' => $student->id,
            'date' => $date . ' 00:00:00',
            'description' => 'Был на тренировке',
        ]);
    }

    public function test_team_default_uses_first_linked_trainer(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $first = $this->makeTrainerProfile('Первый');
        $second = $this->makeTrainerProfile('Второй');

        foreach ([$first, $second] as $profile) {
            \Illuminate\Support\Facades\DB::table('team_trainer')->insert([
                'partner_id' => $this->partner->id,
                'team_id' => $team->id,
                'trainer_profile_id' => $profile->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $student = $this->makeStudent($team->id);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-05-17',
        ]))
            ->assertOk()
            ->assertJsonPath('team_default_trainer_profile_id', $first->id);
    }
}
