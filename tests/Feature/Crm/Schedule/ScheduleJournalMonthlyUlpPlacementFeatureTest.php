<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Services\Schedule\ScheduleJournalMonthService;

/**
 * Месячный ULP из установки цен: ends_at заранее, placeable без starts_at,
 * дата начала обязательна и только внутри billing_month.
 */
final class ScheduleJournalMonthlyUlpPlacementFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_monthly_ulp_with_ends_at_only_is_still_placeable(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-09-01');

        $this->assertNull($ulp->starts_at);
        $this->assertSame('2026-09-30', $ulp->ends_at?->format('Y-m-d'));
        $this->assertTrue($ulp->isJournalPlaceable());
        $this->assertFalse($ulp->isLaidOutInSchedule());

        $row = collect(app(ScheduleJournalMonthService::class)
            ->fixedAssignmentsForUser((int) $this->partner->id, (int) $student->id))
            ->firstWhere('id', (int) $ulp->id);

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['placeable']);
        $this->assertTrue((bool) $row['from_setting_prices']);
        $this->assertSame('2026-09-01', $row['billing_month']);
        $this->assertSame('2026-09-30', $row['ends_at']);
        $this->assertNull($row['starts_at']);
    }

    public function test_place_rejects_start_date_outside_billing_month(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1, 3, 5]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-09-01');

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-06',
            'weekdays' => [1, 3, 5],
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);

        $ulp->refresh();
        $this->assertNull($ulp->starts_at);
        $this->assertSame(0, $ulp->userTeamScheduleSlots()->count());
    }

    public function test_place_monthly_ulp_sets_starts_at_and_keeps_month_end(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1, 3, 5]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-09-07',
            'weekdays' => [1, 3, 5],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $ulp->refresh();
        $this->assertSame('2026-09-07', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2026-09-30', $ulp->ends_at?->format('Y-m-d'));
        $this->assertGreaterThan(0, $ulp->userTeamScheduleSlots()->count());
        $this->assertFalse($ulp->isJournalPlaceable());
    }

    public function test_abonement_context_exposes_monthly_fields(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-09-01');

        $response = $this->getJson(
            route('schedule.abonement.context', $student),
            $this->ajaxHeaders()
        )->assertOk();

        $assignment = collect($response->json('assignments'))->firstWhere('id', (int) $ulp->id);
        $this->assertNotNull($assignment);
        $this->assertTrue((bool) $assignment['from_setting_prices']);
        $this->assertSame('2026-09-01', $assignment['billing_month']);
        $this->assertSame('2026-09-30', $assignment['ends_at']);
        $this->assertTrue((bool) $assignment['placeable']);
        $this->assertSame((int) $team->id, (int) ($assignment['team_id'] ?? 0));
        $response->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $team->id);
    }
}
