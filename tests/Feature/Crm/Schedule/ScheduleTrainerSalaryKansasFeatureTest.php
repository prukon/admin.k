<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\TrainerSalaryKansasGroupBaseline;
use App\Models\TrainerSalarySnapshot;
use App\Models\UserLessonOccurrenceStatusEvent;

/**
 * Схема Канзас: формула, общие поля школы, слоты в один день, слепок.
 */
final class ScheduleTrainerSalaryKansasFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
    }

    public function test_data_uses_kansas_scheme_and_counts_slot_date_not_calendar_day(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас слоты');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа Канзас']);
        $student = $this->makeStudent($team->id);
        $date = '2026-05-06';

        $slotOne = $this->resolveOrCreateTeamScheduleSlot((int) $this->partner->id, (int) $team->id, $date);
        $slotTwo = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => $slotOne->weekday,
            'time_start' => '21:17:00',
            'time_end' => '22:17:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        $this->createVisitedOnSlot($student->id, $trainer->id, $date, (int) $slotOne->id);
        $this->createVisitedOnSlot($student->id, $trainer->id, $date, (int) $slotTwo->id);

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas');

        $row = collect($response->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['trainings_count']);
        $this->assertCount(1, $row['groups']);
        $this->assertSame(2, $row['groups'][0]['trainings_count']);
        $this->assertSame('1', $row['groups'][0]['fact_avg_students']);
        $this->assertStringContainsString('Группа Канзас', $response->json('table_html'));
        $this->assertStringContainsString('premium_increment', $response->json('month_settings_html'));
    }

    public function test_formula_and_shared_inputs_match_scaled_worked_example(): void
    {
        [$trainer, $teamA, $teamB] = $this->seedKansasTwoGroupVisits(2, 14, 25);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();

        $this->setTrainerTypeRates($trainer, 1000, 800);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('reload_table', true);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $teamA->id,
            'base_avg_students' => 16,
        ])->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $teamB->id,
            'base_avg_students' => 18,
        ])->assertOk();

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertNotNull($row);
        $this->assertSame('1000.00', $row['rate_per_training']);
        $this->assertSame('800.00', $row['base_premium']);
        $this->assertSame('8200.00', $row['total']);
        $this->assertSame(4, $row['trainings_count']);

        $groups = collect($row['groups'])->keyBy('team_title');
        $this->assertSame('14', $groups['Группа A']['fact_avg_students']);
        $this->assertSame('14', $groups['Группа A']['fact_avg_students_int']);
        $this->assertSame('16', $groups['Группа A']['base_avg_students']);
        $this->assertSame('16', $groups['Группа A']['base_avg_students_int']);
        $this->assertSame('-2', $groups['Группа A']['diff_students']);
        $this->assertSame('-2', $groups['Группа A']['diff_students_int']);
        $this->assertSame('600.00', $groups['Группа A']['premium']);
        $this->assertSame('1600.00', $groups['Группа A']['pay_per_training']);
        $this->assertSame('3200.00', $groups['Группа A']['group_total']);

        $this->assertSame('25', $groups['Группа B']['fact_avg_students']);
        $this->assertSame('7', $groups['Группа B']['diff_students']);
        $this->assertSame('1500.00', $groups['Группа B']['premium']);
        $this->assertSame('5000.00', $groups['Группа B']['group_total']);
    }

    public function test_group_baseline_is_shared_across_trainers(): void
    {
        $trainerA = $this->makeTrainerProfile('Канзас А');
        $trainerB = $this->makeTrainerProfile('Канзас Б');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Общая группа']);
        $student = $this->makeStudent($team->id);

        $this->createScheduleStatusEntry(
            $student->id,
            (int) $this->visitedStatusId,
            '2026-05-07',
            null,
            [$trainerA->id, $trainerB->id],
        );

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainerA), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 16,
        ])->assertOk();

        $rows = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->json('rows')
        );

        $rowA = $rows->firstWhere('trainer_profile_id', $trainerA->id);
        $rowB = $rows->firstWhere('trainer_profile_id', $trainerB->id);
        $this->assertSame('16', $rowA['groups'][0]['base_avg_students']);
        $this->assertSame('16', $rowA['groups'][0]['base_avg_students_int']);
        $this->assertSame('16', $rowB['groups'][0]['base_avg_students']);
        $this->assertSame('16', $rowB['groups'][0]['base_avg_students_int']);

        $baseline = TrainerSalaryKansasGroupBaseline::query()
            ->whereHas('period', fn ($q) => $q->where('partner_id', $this->partner->id)->where('year', 2026)->where('month', 5))
            ->where('team_id', $team->id)
            ->first();
        $this->assertNotNull($baseline);
        $this->assertSame(160, (int) $baseline->base_avg_students_tenths);
    }

    public function test_premium_floor_and_snapshot_freezes_groups(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас пол');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа пол']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-08');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->setTrainerTypeRates($trainer, 1000, 100);
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 1000,
        ])->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => 20,
        ])->assertOk();

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('0.00', $row['groups'][0]['premium']);
        $this->assertSame('1000.00', $row['groups'][0]['pay_per_training']);
        $this->assertSame('1000.00', $row['total']);

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('snapshot.scheme_code', 'kansas');

        $snapshot = TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('kansas', (string) $snapshot->scheme_code);
        $this->assertSame(100000, (int) $snapshot->total_cents);

        $this->assertDatabaseHas('trainer_salary_kansas_snapshot_groups', [
            'trainer_salary_snapshot_id' => $snapshot->id,
            'team_id' => $team->id,
            'premium_cents' => 0,
            'group_total_cents' => 100000,
        ]);

        $html = $this->get(route('schedule.trainer-salary-sheets.snapshot.show', $snapshot))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Группа пол', $html);
        $this->assertStringContainsString('Канзас пол', $html);
    }

    public function test_foreign_partner_trainer_not_accessible(): void
    {
        $foreignTrainer = $this->makeTrainerProfile('Чужой канзас', $this->foreignPartner->id);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $foreignTrainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 1,
        ])->assertNotFound();
    }

    public function test_invalid_base_avg_returns_field_error(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас валидация');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа вал']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-09');

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => '16.5',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_avg_students']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'team_id' => $team->id,
            'base_avg_students' => '16.55',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_avg_students']);
    }

    public function test_disabled_student_visit_is_not_counted(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас disabled');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа disabled']);
        $student = $this->makeStudent($team->id);
        $student->update(['is_enabled' => 0]);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-10');

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertNotNull($row);
        $this->assertSame(0, $row['trainings_count']);
        $this->assertSame([], $row['groups']);
    }

    public function test_status_other_than_visited_is_not_counted(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас не посетил');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа статус']);
        $student = $this->makeStudent($team->id);
        $other = $this->createCustomOccurrenceStatus('Не был');
        $this->createScheduleStatusEntry($student->id, (int) $other->id, '2026-05-11', $trainer->id);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame(0, $row['trainings_count']);
        $this->assertSame([], $row['groups']);
    }

    public function test_visited_without_trainer_pivot_is_not_counted(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас без pivot');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа pivot']);
        $student = $this->makeStudent($team->id);
        $this->createScheduleStatusEntry($student->id, (int) $this->visitedStatusId, '2026-05-12', null);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame(0, $row['trainings_count']);
    }

    public function test_later_non_visited_status_overrides_visited_on_same_slot_date(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас latest');
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа latest']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-13');
        $other = $this->createCustomOccurrenceStatus('Отмена');
        $this->createScheduleStatusEntry($student->id, (int) $other->id, '2026-05-13', $trainer->id);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame(0, $row['trainings_count']);
        $this->assertSame([], $row['groups']);
    }

    /**
     * @return array{0: \App\Models\TrainerProfile, 1: Team, 2: Team}
     */
    private function seedKansasTwoGroupVisits(int $trainings, int $studentsA, int $studentsB): array
    {
        $trainer = $this->makeTrainerProfile('Канзас формула');
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа B']);

        $usersA = [];
        for ($i = 0; $i < $studentsA; $i++) {
            $usersA[] = $this->makeStudent($teamA->id);
        }
        $usersB = [];
        for ($i = 0; $i < $studentsB; $i++) {
            $usersB[] = $this->makeStudent($teamB->id);
        }

        for ($d = 1; $d <= $trainings; $d++) {
            $date = sprintf('2026-05-%02d', $d);
            foreach ($usersA as $student) {
                $this->createVisitedScheduleEntry($student->id, $trainer->id, $date);
            }
            foreach ($usersB as $student) {
                $this->createVisitedScheduleEntry($student->id, $trainer->id, $date);
            }
        }

        return [$trainer, $teamA, $teamB];
    }

    private function createVisitedOnSlot(int $userId, int $trainerProfileId, string $date, int $slotId): UserLessonOccurrenceStatusEvent
    {
        $event = UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $userId,
            'team_schedule_slot_id' => $slotId,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainerProfileId,
            'created_by' => $this->user->id,
        ]);
        $event->trainerProfiles()->sync([$trainerProfileId]);

        return $event;
    }
}
