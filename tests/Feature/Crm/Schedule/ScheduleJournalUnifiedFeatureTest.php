<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Единый контур журнала: user_team_schedule_slots + раскладка fixed + статусы со списанием.
 */
final class ScheduleJournalUnifiedFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
    }

    public function test_journal_index_shows_abonement_markers_without_schedule_users(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('journal-abonement-btn', false)
            ->assertSee('abonementPlaceModal', false)
            ->assertDontSee('userScheduleModal', false);
    }

    public function test_place_fixed_abonement_creates_slots_status_and_auto_team_slot(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1, 3]); // Пн, Ср

        $ulp = $this->makeFixedAssignment($student, lessons: 4, durationDays: 21);

        $start = CarbonImmutable::parse('2026-08-03'); // Monday
        $this->assertSame(1, (int) $start->format('N'));

        $response = $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => $start->toDateString(),
            'weekdays' => [1, 3],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, (int) $response->json('result.linked_count'));

        $ulp->refresh();
        $this->assertNotNull($ulp->starts_at);
        $this->assertNotNull($ulp->ends_at);

        $utssCount = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->count();
        $this->assertSame((int) $response->json('result.linked_count'), $utssCount);
        $this->assertSame(4, $utssCount);

        $this->assertTrue(
            TeamScheduleSlot::query()
                ->where('partner_id', $this->partner->id)
                ->where('team_id', $team->id)
                ->where('weekday', 1)
                ->where('is_enabled', true)
                ->exists()
        );

        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->assertNotNull($scheduledId);

        $events = UserLessonOccurrenceStatusEvent::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->where('lesson_occurrence_status_id', $scheduledId)
            ->count();
        $this->assertSame(4, $events);

        // Повторная раскладка запрещена
        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => $start->toDateString(),
            'weekdays' => [1, 3],
        ])->assertStatus(422);
    }

    public function test_journal_status_attended_consumes_lesson_remaining(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertOk();

        $ulp->refresh();
        $this->assertSame(2, (int) $ulp->lessons_remaining);

        $utss = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->orderBy('starts_at')
            ->first();
        $this->assertNotNull($utss);

        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => CarbonImmutable::parse($utss->starts_at)->toDateString(),
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_id' => null,
        ])->assertOk()->assertJsonPath('success', true);

        $ulp->refresh();
        $this->assertSame(1, (int) $ulp->lessons_remaining);
    }

    public function test_journal_shows_occurrences_from_school_calendar_slots(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        $date = '2026-08-03';
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => $date,
            'ends_at' => $date,
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->assertOk()
            ->assertSee('data-occurrence-count="1"', false)
            ->assertSee('fa-solid fa-circle', false);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => $date,
        ]))
            ->assertOk()
            ->assertJsonPath('occurrences.0.is_trial_lesson', true);
    }

    public function test_preview_does_not_persist(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
            'preview' => true,
        ])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('result.linked_count', 2);

        $ulp->refresh();
        $this->assertNull($ulp->starts_at);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function makeStudentWithTeam(): array
    {
        [$student, $team] = array_slice($this->makeStudentTeamAndTrainer(), 0, 2);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);

        return [$student, $team];
    }

    private function attachWeekdays(Team $team, array $weekdayIds): void
    {
        foreach ($weekdayIds as $weekdayId) {
            DB::table('team_weekdays')->insertOrIgnore([
                'team_id' => $team->id,
                'weekday_id' => $weekdayId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function makeFixedAssignment(User $student, int $lessons, int $durationDays): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Журнал фикс '.uniqid(),
            'schedule_type' => 'fixed',
            'duration_days' => $durationDays,
            'lessons_count' => $lessons,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }
}
