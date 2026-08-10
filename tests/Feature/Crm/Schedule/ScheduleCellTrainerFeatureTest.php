<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;

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
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-11';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'comment' => '',
            'trainer_profile_id' => $trainer->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => $date,
        ]))
            ->assertOk()
            ->assertJsonPath('trainer_profile_id_for_select', (string) $trainer->id)
            ->assertJsonPath('trainer_profile_ids_for_select', [(int) $trainer->id])
            ->assertJsonPath('current_status_id', $this->visitedStatusId);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'comment' => '',
            'trainer_profile_id' => '',
        ])->assertOk();

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
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
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-13';
        $utss = $this->createTrialUtss($student, $team, $date);

        foreach (['none', '0', ''] as $rawValue) {
            $this->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $rawValue,
            ])->assertOk();
        }

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'trainer_profile_id' => null,
        ]);
    }

    public function test_update_rejects_trainer_from_foreign_partner(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();
        $foreignTrainer = $this->makeTrainerProfile('Чужой тренер', $this->foreignPartner->id);
        $date = '2026-05-14';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $foreignTrainer->id,
        ])->assertStatus(422);
    }

    public function test_update_returns_404_for_foreign_partner_student(): void
    {
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => (int) \App\Models\Role::query()->where('name', 'user')->value('id'),
            'team_id' => $foreignTeam->id,
        ]);
        $date = '2026-05-14';
        $utss = $this->createTrialUtss($foreignStudent, $foreignTeam, $date);

        $this->postJson(route('schedule.update'), [
            'user_id' => $foreignStudent->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertNotFound();
    }

    public function test_non_visited_status_clears_trainer_profile_id(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-12';
        $utss = $this->createTrialUtss($student, $team, $date);

        $otherStatus = $this->createCustomOccurrenceStatus('Болезнь');

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'comment' => '',
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $otherStatus->id,
            'comment' => '',
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $otherStatus->id,
            'trainer_profile_id' => null,
        ]);
    }

    public function test_update_visited_saves_description(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-16';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'comment' => 'Был на тренировке',
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'comment' => 'Был на тренировке',
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

    public function test_update_visited_saves_multiple_trainers_for_salary_credit(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer('Тренер А');
        $trainerB = $this->makeTrainerProfile('Тренер Б');
        $date = '2026-05-18';
        $utss = $this->createTrialUtss($student, $team, $date);

        $response = $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_ids' => [$trainerA->id, $trainerB->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.trainer_profile_ids', [(int) $trainerA->id, (int) $trainerB->id]);

        $trainerName = (string) $response->json('result.trainer_name');
        $this->assertStringContainsString('Тренер А', $trainerName);
        $this->assertStringContainsString('Тренер Б', $trainerName);
        $this->assertStringContainsString(', ', $trainerName);

        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $utss->team_schedule_slot_id)
            ->whereDate('occurrence_date', $date)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame((int) $trainerA->id, (int) $event->trainer_profile_id);
        $this->assertEqualsCanonicalizing(
            [(int) $trainerA->id, (int) $trainerB->id],
            $event->trainerProfiles()->pluck('trainer_profiles.id')->map(fn ($id) => (int) $id)->all()
        );

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => $date,
            'utss_id' => $utss->id,
        ]))
            ->assertOk()
            ->assertJsonPath('trainer_profile_ids_for_select', [(int) $trainerA->id, (int) $trainerB->id])
            ->assertJsonPath('selected.trainer_name', $trainerName);
    }
}
