<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\LessonOccurrenceStatus;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Табы периода на «Платежи по группам»: current / previous / all.
 */
final class LtvTeamsReportPeriodTabsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'Europe/Moscow'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_page_renders_period_tabs_for_current_and_previous_month(): void
    {
        $this->asAdmin();
        $html = $this->get(route('reports.ltv.teams'))->assertOk()->getContent();

        $this->assertStringContainsString('id="ltv-teams-period-switch"', $html);
        $this->assertStringContainsString('id="ltv-teams-period-btn-current"', $html);
        $this->assertStringContainsString('id="ltv-teams-period-btn-previous"', $html);
        $this->assertStringContainsString('id="ltv-teams-period-btn-all"', $html);
        $this->assertStringContainsString('>Сентябрь</button>', $html);
        $this->assertStringContainsString('>Август</button>', $html);
        $this->assertStringContainsString('>Все время</button>', $html);
        $this->assertStringContainsString('data-error-for="period"', $html);
        $this->assertStringContainsString("KidsCrmDataTable.create('#ltv-teams-table'", $html);
        $this->assertStringContainsString("\$('.js-ltv-teams-period-btn').on('click'", $html);
        $this->assertStringContainsString('period: currentPeriod', $html);
        $this->assertStringContainsString('function ltvTeamsSyncPeriodInUrl()', $html);
        $this->assertMatchesRegularExpression(
            '/js-ltv-teams-period-btn\s+active[^>]*id="ltv-teams-period-btn-current"/',
            $html
        );
    }

    public function test_period_all_query_marks_all_tab_active(): void
    {
        $this->asAdmin();
        $html = $this->get(route('reports.ltv.teams', ['period' => 'all']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/js-ltv-teams-period-btn\s+active[^>]*id="ltv-teams-period-btn-all"/',
            $html
        );
    }

    public function test_invalid_period_ajax_returns_422_and_non_ajax_redirects(): void
    {
        $this->asAdmin();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.teams.data', ['draw' => 1, 'period' => 'nope']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period']);

        $this->from(route('reports.ltv.teams'))
            ->get(route('reports.ltv.teams', ['period' => 'nope']))
            ->assertRedirect(route('reports.ltv.teams'))
            ->assertSessionHasErrors(['period']);
    }

    public function test_default_current_excludes_previous_month_and_future_days(): void
    {
        $this->asAdmin();
        $this->seedPeriodPayments();

        $rows = $this->ltvTeamsRows([]);
        $this->assertCount(1, $rows);
        $this->assertSame('Группа-период', $rows[0]['team_title']);
        $this->assertSame(1, (int) $rows[0]['payment_count']);
        $this->assertEquals(1000.0, (float) $rows[0]['total_price']);

        $this->get(route('reports.ltv.teams.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(1000, 0, '', ' '),
                'total_raw' => 1000.0,
            ]);
    }

    public function test_previous_period_is_full_previous_calendar_month(): void
    {
        $this->asAdmin();
        $this->seedPeriodPayments();

        $rows = $this->ltvTeamsRows(['period' => 'previous']);
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]['payment_count']);
        $this->assertEquals(800.0, (float) $rows[0]['total_price']);

        $this->get(route('reports.ltv.teams.total', ['period' => 'previous']))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(800, 0, '', ' '),
                'total_raw' => 800.0,
            ]);
    }

    public function test_all_period_includes_every_operation_date(): void
    {
        $this->asAdmin();
        $this->seedPeriodPayments();

        $rows = $this->ltvTeamsRows(['period' => 'all']);
        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows[0]['payment_count']);
        $this->assertEquals(2500.0, (float) $rows[0]['total_price']);

        $this->get(route('reports.ltv.teams.total', ['period' => 'all']))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(2500, 0, '', ' '),
                'total_raw' => 2500.0,
            ]);
    }

    public function test_date_filters_intersect_with_period_tab(): void
    {
        $this->asAdmin();
        $this->seedPeriodPayments();

        $rows = $this->ltvTeamsRows([
            'period' => 'current',
            'operation_date_from' => '2026-09-10',
        ]);
        $this->assertSame([], $rows);

        $rows = $this->ltvTeamsRows([
            'period' => 'current',
            'operation_date_to' => '2026-09-01',
        ]);
        $this->assertSame([], $rows);

        $rows = $this->ltvTeamsRows([
            'period' => 'all',
            'operation_date_from' => '2026-08-01',
            'operation_date_to' => '2026-08-31',
        ]);
        $this->assertCount(1, $rows);
        $this->assertEquals(800.0, (float) $rows[0]['total_price']);
    }

    public function test_nested_payments_and_index_total_follow_period(): void
    {
        $this->asAdmin();
        $team = $this->seedPeriodPayments();

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.teams.payments', [
                'team' => $team->id,
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'period' => 'previous',
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($json['meta_payments_count'] ?? 0));
        $this->assertEquals(800.0, (float) ($json['meta_sum_total'] ?? 0));

        $html = $this->get(route('reports.ltv.teams'))->assertOk()->getContent();
        $this->assertStringContainsString('>1 000</span>', $html);
    }

    public function test_avg_attendance_follows_tab_month_not_payment_month_filter(): void
    {
        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $visitedId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-таб-посещаемость',
        ]);
        $slot = TeamScheduleSlot::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
        ]);

        $students = [];
        for ($i = 0; $i < 4; $i++) {
            $student = User::factory()->create([
                'partner_id' => $this->partner->id,
                'lastname' => 'Таб'.$i,
                'name' => 'Ученик',
                'is_enabled' => 1,
            ]);
            app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
            $students[] = $student;
        }

        Payment::factory()->create([
            'user_id' => $students[0]->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Группа-таб-посещаемость',
            'summ_cents' => 10000,
            'operation_date' => '2026-09-05 10:00:00',
            'payment_month' => '2026-08-01',
        ]);
        Payment::factory()->create([
            'user_id' => $students[0]->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Группа-таб-посещаемость',
            'summ_cents' => 10000,
            'operation_date' => '2026-08-15 10:00:00',
            'payment_month' => '2026-08-01',
        ]);

        foreach (['2026-09-01', '2026-09-08', '2026-09-15'] as $date) {
            $this->markVisitedOnSlot($students[0]->id, (int) $slot->id, $date, $visitedId);
            $this->markVisitedOnSlot($students[1]->id, (int) $slot->id, $date, $visitedId);
        }
        foreach (['2026-08-04', '2026-08-11', '2026-08-18', '2026-08-25'] as $date) {
            foreach ($students as $student) {
                $this->markVisitedOnSlot($student->id, (int) $slot->id, $date, $visitedId);
            }
        }

        $current = $this->ltvTeamsRowByTitle('Группа-таб-посещаемость', [
            'period' => 'current',
            'payment_month' => '2026-08',
        ]);
        $this->assertNotEmpty($current);
        $this->assertSame(2, (int) $current['avg_attendance']);

        $previous = $this->ltvTeamsRowByTitle('Группа-таб-посещаемость', ['period' => 'previous']);
        $this->assertNotEmpty($previous);
        $this->assertSame(4, (int) $previous['avg_attendance']);

        $all = $this->ltvTeamsRowByTitle('Группа-таб-посещаемость', ['period' => 'all']);
        $this->assertNotEmpty($all);
        $this->assertSame(3, (int) $all['avg_attendance']);

        $allAugust = $this->ltvTeamsRowByTitle('Группа-таб-посещаемость', [
            'period' => 'all',
            'payment_month' => '2026-08',
        ]);
        $this->assertNotEmpty($allAugust);
        $this->assertSame(4, (int) $allAugust['avg_attendance']);
    }

    private function seedPeriodPayments(): Team
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-период',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Периодов',
            'name' => 'Иван',
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Группа-период',
            'summ_cents' => 100000,
            'operation_date' => '2026-09-05 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Группа-период',
            'summ_cents' => 80000,
            'operation_date' => '2026-08-15 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Группа-период',
            'summ_cents' => 70000,
            'operation_date' => '2026-09-20 10:00:00',
        ]);

        return $team;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ltvTeamsRowByTitle(string $title, array $extra = []): array
    {
        foreach ($this->ltvTeamsRows($extra) as $row) {
            if ((string) ($row['team_title'] ?? '') === $title) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return list<array<string, mixed>>
     */
    private function ltvTeamsRows(array $extra): array
    {
        $query = array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ], $extra);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.teams.data', $query))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    private function markVisitedOnSlot(int $userId, int $slotId, string $date, int $statusId): void
    {
        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $userId,
            'team_schedule_slot_id' => $slotId,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $statusId,
            'created_by' => $this->user->id,
        ]);
    }
}
