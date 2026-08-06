<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\ScheduleJournalMonthService;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Доменные сценарии месячного гибкого (установка цен) в журнале.
 * Контракты/доступ/non-AJAX — {@see ScheduleJournalFlexibleContractsFeatureTest}.
 */
final class ScheduleJournalFlexiblePlacementFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_monthly_flexible_with_full_period_is_not_laid_out_until_utss(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 3);

        $this->assertSame('2026-09-01', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2026-09-30', $ulp->ends_at?->format('Y-m-d'));
        $this->assertFalse($ulp->isLaidOutInSchedule());
        $this->assertTrue($ulp->isMonthlyFlexibleFromSettingPrices());
        $this->assertSame(3, $ulp->calendarSlotsRemaining());

        $byUser = app(ScheduleJournalMonthService::class)->flexibleAssignableByUserForBillingMonth(
            (int) $this->partner->id,
            [(int) $student->id],
            '2026-09-01',
            'all',
        );
        $this->assertArrayHasKey((int) $student->id, $byUser);
        $this->assertCount(1, $byUser[(int) $student->id]);
        $this->assertSame(3, $byUser[(int) $student->id][0]['slots_remaining']);
    }

    public function test_journal_index_shows_flexible_hint_and_empty_cell_affordance(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $packageName = (string) $ulp->lessonPackage?->name;

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee('>2/2<', false)
            ->assertSee('Остаток занятий в текущем месяце по абонементу &quot;'.$packageName.'&quot;', false)
            ->assertSee('data-flexible="1"', false)
            ->assertSee('Гибкий абонемент: поставить занятие', false)
            ->assertSee('flexiblePlaceModal', false)
            ->assertSee('btn-add-flexible-lesson', false);
    }

    public function test_journal_index_shows_icon_hint_when_multiple_flexible_without_team_filter(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $ulpA = $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-09-01', lessons: 10);
        $ulpB = $this->makeMonthlyFlexibleAssignment($student, (int) $teamB->id, '2026-09-01', lessons: 8);
        $nameA = (string) $ulpA->lessonPackage?->name;
        $nameB = (string) $ulpB->lessonPackage?->name;

        $all = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => 'all']));
        $all->assertOk()
            ->assertSee('journal-flexible-hint--multi', false)
            ->assertDontSee('journal-flexible-hint--ratio', false)
            ->assertSee('10/10 остаток занятий в текущем месяце по абонементу &quot;'.$nameA.'&quot;', false)
            ->assertSee('8/8 остаток занятий в текущем месяце по абонементу &quot;'.$nameB.'&quot;', false);

        $filtered = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $teamA->id]));
        $filtered->assertOk()
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee('>10/10<', false)
            ->assertSee('Остаток занятий в текущем месяце по абонементу &quot;'.$nameA.'&quot;', false)
            ->assertDontSee('journal-flexible-hint--multi', false);
    }

    public function test_journal_index_renders_flexible_modal_status_trainer_and_hint_data_attrs(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 4);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->assertNotNull($scheduledId);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('flexiblePlaceModal', false)
            ->assertSee('name="flexible_lesson_occurrence_status_id"', false)
            ->assertSee('id="flexible-status-'.$scheduledId.'"', false)
            ->assertSee('id="flexible-trainer-wrap"', false)
            ->assertSee('id="flexible-trainer-profile-id"', false)
            ->assertSee('id="flexible-comment"', false)
            ->assertSee('id="flexible-status-error"', false)
            ->assertSee('data-flexible-ulp-id="'.$ulp->id.'"', false)
            ->assertSee('data-slots-remaining="4"', false)
            ->assertSee('data-lessons-total="4"', false)
            ->assertSee('>4/4<', false);
    }

    public function test_place_flexible_on_empty_day_creates_utss_with_scheduled_status(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.slots_remaining', 1);

        $ulp->refresh();
        $this->assertTrue($ulp->isLaidOutInSchedule());
        $this->assertSame(1, $ulp->userTeamScheduleSlots()->count());
        $this->assertSame('2026-09-01', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2026-09-30', $ulp->ends_at?->format('Y-m-d'));

        $utss = $ulp->userTeamScheduleSlots()->first();
        $this->assertNotNull($utss);
        $this->assertSame('2026-09-10', Carbon::parse($utss->starts_at)->format('Y-m-d'));
        $this->assertSame('2026-09-30', Carbon::parse($utss->ends_at)->format('Y-m-d'));

        $scheduledId = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->where('code', LessonOccurrenceStatus::CODE_SCHEDULED)
            ->value('id');
        $this->assertNotNull($scheduledId);
        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => $scheduledId,
        ]);
    }

    public function test_place_second_flexible_lesson_same_day_uses_another_slot(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 3);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-15',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders())->assertOk();

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-15',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('result.slots_remaining', 1);

        $slots = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->whereDate('starts_at', '2026-09-15')
            ->get();
        $this->assertCount(2, $slots);
        $this->assertNotSame(
            (int) $slots[0]->team_schedule_slot_id,
            (int) $slots[1]->team_schedule_slot_id
        );
    }

    public function test_place_rejects_date_outside_billing_month(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-20',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_date']);

        $this->assertSame(0, $ulp->userTeamScheduleSlots()->count());
    }

    public function test_place_rejects_when_limit_reached(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-05',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders())->assertOk();

        $response = $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-06',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders());
        $response->assertStatus(422);
        $errors = $response->json('errors.user_lesson_package_id') ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('лимит', mb_strtolower((string) $errors[0]));
    }

    public function test_paid_month_does_not_block_flexible_placement(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $ulp->update(['is_paid' => true, 'is_manual_paid' => true]);

        DB::table('users_prices')->insert([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => '2026-09-01',
            'price' => 5000,
            'is_paid' => 1,
            'is_manual_paid' => 1,
            'lesson_package_id' => $ulp->lesson_package_id,
            'user_lesson_package_id' => $ulp->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-12',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_flexible_context_filters_by_billing_month_and_resolves_team(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 2);

        $this->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-08&context_team_id='.$team->id, $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('can_place', true)
            ->assertJsonPath('team_id', (int) $team->id)
            ->assertJsonPath('assignment.id', (int) $ulp->id)
            ->assertJsonCount(1, 'assignments');
    }

    public function test_flexible_context_excludes_classic_flexible_without_billing_month(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(4, 60)->create([
            'name' => 'Классика гибкий',
            'is_active' => true,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'billing_month' => null,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount' => '1000.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-08', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('can_place', false)
            ->assertJsonCount(0, 'assignments');
    }

    public function test_place_rejects_wrong_team(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $otherTeam = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id, (int) $otherTeam->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        $response = $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $otherTeam->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ], $this->ajaxHeaders());
        $response->assertStatus(422);
        $errors = $response->json('errors.team_id') ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('групп', mb_strtolower((string) $errors[0]));
    }

    public function test_guest_cannot_place_flexible(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        auth()->logout();

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ])->assertUnauthorized();
    }
}
