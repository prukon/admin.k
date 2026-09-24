<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Services\TeamUserSyncService;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отчёт «Платежи по группам»: группировка по payments.team_id, право reports.ltv.teams.view.
 */
final class LtvTeamsReportFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_page_requires_reports_ltv_teams_view(): void
    {
        $denied = $this->createUserWithoutPermission('reports.ltv.teams.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('reports.ltv.teams'))->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.teams.data', ['draw' => 1]))
            ->assertForbidden();
        $this->get(route('reports.ltv.teams.total'))->assertForbidden();
        $this->getJson(route('reports.ltv.teams.users.search', ['q' => '']))->assertForbidden();
        $this->getJson(route('reports.ltv.teams.teams.search', ['q' => '']))->assertForbidden();
        $this->getJson(route('reports.ltv.teams.trainers.search', ['q' => '']))->assertForbidden();
    }

    public function test_guest_cannot_open_page(): void
    {
        Auth::logout();

        $this->get(route('reports.ltv.teams'))->assertRedirect();
        $this->getJson(route('reports.ltv.teams.total'))->assertStatus(401);
        $this->getJson(route('reports.ltv.teams.users.search', ['q' => '']))->assertStatus(401);
    }

    public function test_trainer_without_reports_view_can_open_tab_and_sees_menu(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);

        $html = $this->get(route('reports.ltv.teams'))->assertOk()->getContent();
        $this->assertStringContainsString('Платежи по группам', $html);
        $this->assertStringContainsString('id="ltv-teams-table"', $html);
        $this->assertStringNotContainsString('Платежи по ученикам', $html);
        $this->assertStringContainsString('Отчеты', $html);
        $this->assertStringContainsString('/admin/reports/ltv/teams/users-search', $html);
        $this->assertStringContainsString('name="filter_team_id[]"', $html);
        $this->assertStringContainsString('id="pay-ltv-teams-filter-team"', $html);
        $this->assertStringNotContainsString('/admin/reports/payments/users-search', $html);
        $this->assertStringNotContainsString('/admin/reports/payments/teams-search', $html);

        $this->getJson(route('reports.ltv.teams.users.search', ['q' => '']))->assertOk();
        $this->getJson(route('reports.ltv.teams.teams.search', ['q' => '']))->assertOk();
        $this->getJson(route('reports.ltv.teams.trainers.search', ['q' => '']))->assertOk();
        $this->getJson(route('reports.payments.users.search', ['q' => '']))->assertForbidden();
        $this->getJson(route('reports.payments.teams.search', ['q' => '']))->assertForbidden();
        $this->getJson(route('reports.payments.trainers.search', ['q' => '']))->assertForbidden();
    }

    public function test_admin_page_shows_tab_next_to_students_ltv(): void
    {
        $this->asAdmin();
        $html = $this->get(route('reports.ltv.teams'))->assertOk()->getContent();

        $studentsPos = strpos($html, 'Платежи по ученикам');
        $teamsPos = strpos($html, 'Платежи по группам');
        $this->assertNotFalse($studentsPos);
        $this->assertNotFalse($teamsPos);
        $this->assertTrue($studentsPos < $teamsPos);
        $this->assertStringContainsString('id="ltv-teams-table"', $html);
        $this->assertStringContainsString('<th>Группа</th>', $html);
        $this->assertStringContainsString('<th>Ученики</th>', $html);
        $this->assertStringContainsString('<th>Ср. посещаемость</th>', $html);
        $this->assertStringContainsString('<th>ФИО</th>', $html);
        $this->assertStringContainsString("KidsCrmDataTable.create('#ltv-teams-table'", $html);
        $this->assertTrue(
            strpos($html, '<th>Ученики</th>') < strpos($html, '<th>Ср. посещаемость</th>')
        );
        $this->assertTrue(
            strpos($html, '<th>Ср. посещаемость</th>') < strpos($html, '<th>Сумма</th>')
        );
        $this->assertStringContainsString("order: [[4, 'desc']]", $html);
        $this->assertStringContainsString("name: 'avg_attendance'", $html);
        $this->assertStringContainsString("type: 'list'", $html);
        $this->assertStringContainsString("itemsKey: 'user_names_items'", $html);
        $this->assertStringContainsString("kids-hover-list-tooltip--two-col", $html);
        $this->assertStringContainsString('listOptions', $html);
    }

    public function test_non_ajax_data_returns_404(): void
    {
        $this->asAdmin();
        $this->get(route('reports.ltv.teams.data', ['draw' => 1]))->assertNotFound();
    }

    public function test_aggregates_one_row_per_paid_team_and_null_team_as_without_group(): void
    {
        $this->asAdmin();

        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-Альфа',
            'is_enabled' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-Бета',
            'is_enabled' => 0,
        ]);

        $studentOne = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванов',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);
        $studentTwo = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Сидорова',
            'name' => 'Анна',
            'is_enabled' => 1,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($studentOne, (int) $teamA->id);
        $sync->attachTeamForStudent($studentTwo, (int) $teamB->id);

        Payment::factory()->create([
            'user_id' => $studentOne->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа-Альфа',
            'summ_cents' => 100000,
            'operation_date' => '2026-09-01 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $studentOne->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа-Альфа',
            'summ_cents' => 50000,
            'operation_date' => '2026-09-02 11:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $studentTwo->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'Группа-Бета',
            'summ_cents' => 200000,
            'operation_date' => '2026-09-03 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $studentTwo->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'summ_cents' => 70000,
            'operation_date' => '2026-09-04 13:00:00',
        ]);

        $rows = $this->ltvTeamsRows();
        $byTitle = [];
        foreach ($rows as $row) {
            $byTitle[(string) $row['team_title']] = $row;
        }

        $this->assertArrayHasKey('Группа-Альфа', $byTitle);
        $this->assertSame(2, (int) $byTitle['Группа-Альфа']['payment_count']);
        $this->assertEquals(1500.0, (float) $byTitle['Группа-Альфа']['total_price']);
        $this->assertStringContainsString('Иванов', (string) $byTitle['Группа-Альфа']['user_names']);
        $this->assertSame(['Иванов Пётр'], $byTitle['Группа-Альфа']['user_names_items']);
        $this->assertSame((int) $teamA->id, (int) $byTitle['Группа-Альфа']['team_id']);
        $this->assertTrue(
            $byTitle['Группа-Альфа']['avg_attendance'] === null
            || $byTitle['Группа-Альфа']['avg_attendance'] === ''
        );

        $this->assertArrayHasKey('Группа-Бета', $byTitle);
        $this->assertSame(1, (int) $byTitle['Группа-Бета']['payment_count']);
        $this->assertEquals(2000.0, (float) $byTitle['Группа-Бета']['total_price']);
        $this->assertSame(['Сидорова Анна'], $byTitle['Группа-Бета']['user_names_items']);

        $this->assertArrayHasKey('Без группы', $byTitle);
        $this->assertSame(0, (int) $byTitle['Без группы']['team_id']);
        $this->assertSame(1, (int) $byTitle['Без группы']['payment_count']);
        $this->assertEquals(700.0, (float) $byTitle['Без группы']['total_price']);
        $this->assertTrue(
            $byTitle['Без группы']['avg_attendance'] === null
            || $byTitle['Без группы']['avg_attendance'] === ''
        );

        $this->get(route('reports.ltv.teams.total', ['period' => 'all']))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(4200, 0, '', ' '),
                'total_raw' => 4200.0,
            ]);
    }

    public function test_user_names_items_lists_each_paying_student(): void
    {
        $this->asAdmin();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Два-ученика',
        ]);
        $first = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Алексеев',
            'name' => 'Игорь',
            'is_enabled' => 1,
        ]);
        $second = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Борисова',
            'name' => 'Мария',
            'is_enabled' => 1,
        ]);
        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($first, (int) $team->id);
        $sync->attachTeamForStudent($second, (int) $team->id);

        Payment::factory()->create([
            'user_id' => $first->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Два-ученика',
            'summ_cents' => 10000,
            'operation_date' => '2026-09-01 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $second->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Два-ученика',
            'summ_cents' => 20000,
            'operation_date' => '2026-09-02 11:00:00',
        ]);

        $rows = $this->ltvTeamsRows(['period' => 'all']);
        $this->assertNotEmpty($rows);
        $row = $rows[0];
        $this->assertSame('Алексеев Игорь, Борисова Мария', $row['user_names']);
        $this->assertSame(['Алексеев Игорь', 'Борисова Мария'], $row['user_names_items']);
        $this->assertSame([
            ['id' => (int) $first->id, 'name' => 'Алексеев Игорь'],
            ['id' => (int) $second->id, 'name' => 'Борисова Мария'],
        ], $row['user_name_cards']);
    }

    public function test_nested_team_payments_include_user_name(): void
    {
        $this->asAdmin();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Детализация-группа',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Козлов',
            'name' => 'Илья',
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Детализация-группа',
            'summ_cents' => 123400,
            'operation_date' => '2026-09-10 15:30:00',
            'payment_month' => '2026-09-01',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.teams.payments', [
                'team' => $team->id,
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'period' => 'all',
            ]))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($json['data'] ?? []);
        $row = $json['data'][0];
        $this->assertStringContainsString('Козлов', (string) ($row['user_name'] ?? ''));
        $this->assertSame((int) $student->id, (int) ($row['user_id'] ?? 0));
        $this->assertArrayHasKey('payment_provider', $row);
        $this->assertSame(1, (int) ($json['meta_payments_count'] ?? 0));
        $this->assertEquals(1234.0, (float) ($json['meta_sum_total'] ?? 0));
    }

    public function test_datatable_search_matches_team_title_not_amount(): void
    {
        $this->asAdmin();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'УникальнаяГруппаLTV',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Поиск',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'УникальнаяГруппаLTV',
            'summ_cents' => 999900,
            'operation_date' => '2026-09-05 10:00:00',
        ]);

        $hit = $this->ltvTeamsRows(['search' => ['value' => 'УникальнаяГруппаLTV']]);
        $this->assertNotEmpty($hit);
        $this->assertSame('УникальнаяГруппаLTV', $hit[0]['team_title']);

        $miss = $this->ltvTeamsRows(['search' => ['value' => '9999']]);
        $this->assertSame([], $miss);
    }

    public function test_avg_attendance_is_mean_visited_headcount_rounded_half_up(): void
    {
        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $visitedId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $notAttendedId = (int) LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->where('code', 'not_attended')
            ->value('id');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-посещаемость',
        ]);
        $slot = TeamScheduleSlot::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'weekday' => 3,
        ]);

        $students = [];
        for ($i = 0; $i < 4; $i++) {
            $student = User::factory()->create([
                'partner_id' => $this->partner->id,
                'lastname' => 'Посещ'.$i,
                'name' => 'Ученик',
                'is_enabled' => $i === 3 ? 0 : 1,
            ]);
            app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
            $students[] = $student;
        }

        Payment::factory()->create([
            'user_id' => $students[0]->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Группа-посещаемость',
            'summ_cents' => 10000,
            'operation_date' => '2026-09-01 10:00:00',
            'payment_month' => '2026-09-01',
        ]);

        // 2 + 3 + 2 + 3 = 10 / 4 = 2.5 → 3
        $this->markVisitedOnSlot($students[0]->id, (int) $slot->id, '2026-09-01', $visitedId);
        $this->markVisitedOnSlot($students[1]->id, (int) $slot->id, '2026-09-01', $visitedId);

        $this->markVisitedOnSlot($students[0]->id, (int) $slot->id, '2026-09-08', $visitedId);
        $this->markVisitedOnSlot($students[1]->id, (int) $slot->id, '2026-09-08', $visitedId);
        $this->markVisitedOnSlot($students[2]->id, (int) $slot->id, '2026-09-08', $visitedId);

        $this->markVisitedOnSlot($students[0]->id, (int) $slot->id, '2026-09-15', $visitedId);
        $this->markVisitedOnSlot($students[1]->id, (int) $slot->id, '2026-09-15', $visitedId);

        $this->markVisitedOnSlot($students[0]->id, (int) $slot->id, '2026-09-22', $visitedId);
        $this->markVisitedOnSlot($students[1]->id, (int) $slot->id, '2026-09-22', $visitedId);
        $this->markVisitedOnSlot($students[3]->id, (int) $slot->id, '2026-09-22', $visitedId);

        $this->markVisitedOnSlot($students[0]->id, (int) $slot->id, '2026-09-29', $notAttendedId);
        $this->markVisitedOnSlot($students[1]->id, (int) $slot->id, '2026-09-29', $notAttendedId);

        $allTime = $this->ltvTeamsRowByTitle('Группа-посещаемость');
        $this->assertNotEmpty($allTime);
        $this->assertSame(3, (int) $allTime['avg_attendance']);

        $september = $this->ltvTeamsRowByTitle('Группа-посещаемость', ['payment_month' => '2026-09']);
        $this->assertNotEmpty($september);
        $this->assertSame(3, (int) $september['avg_attendance']);
    }

    public function test_avg_attendance_payment_month_limits_journal_period(): void
    {
        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $visitedId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-месяц-посещаемость',
        ]);
        $slot = TeamScheduleSlot::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
        ]);

        $students = [];
        for ($i = 0; $i < 4; $i++) {
            $student = User::factory()->create([
                'partner_id' => $this->partner->id,
                'lastname' => 'Месяц'.$i,
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
            'team_title' => 'Группа-месяц-посещаемость',
            'summ_cents' => 15000,
            'operation_date' => '2026-09-02 10:00:00',
            'payment_month' => '2026-09-01',
        ]);

        foreach (['2026-09-01', '2026-09-08', '2026-09-15', '2026-09-22'] as $date) {
            $this->markVisitedOnSlot($students[0]->id, (int) $slot->id, $date, $visitedId);
            $this->markVisitedOnSlot($students[1]->id, (int) $slot->id, $date, $visitedId);
        }
        foreach (['2026-08-04', '2026-08-11', '2026-08-18', '2026-08-25'] as $date) {
            foreach ($students as $student) {
                $this->markVisitedOnSlot($student->id, (int) $slot->id, $date, $visitedId);
            }
        }

        $allTime = $this->ltvTeamsRowByTitle('Группа-месяц-посещаемость');
        $this->assertNotEmpty($allTime);
        $this->assertSame(3, (int) $allTime['avg_attendance']);

        $september = $this->ltvTeamsRowByTitle('Группа-месяц-посещаемость', ['payment_month' => '2026-09']);
        $this->assertNotEmpty($september);
        $this->assertSame(2, (int) $september['avg_attendance']);
    }

    public function test_avg_attendance_uses_latest_status_and_ignores_empty_sessions(): void
    {
        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $visitedId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $notAttendedId = (int) LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->where('code', 'not_attended')
            ->value('id');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-актуальный-статус',
        ]);
        $slot = TeamScheduleSlot::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
        ]);
        $studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Актуальный',
            'name' => 'Первый',
            'is_enabled' => 1,
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Актуальный',
            'name' => 'Второй',
            'is_enabled' => 1,
        ]);
        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($studentA, (int) $team->id);
        $sync->attachTeamForStudent($studentB, (int) $team->id);

        Payment::factory()->create([
            'user_id' => $studentA->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Группа-актуальный-статус',
            'summ_cents' => 20000,
            'operation_date' => '2026-09-10 12:00:00',
        ]);

        $this->markVisitedOnSlot($studentA->id, (int) $slot->id, '2026-09-03', $visitedId);
        $this->markVisitedOnSlot($studentB->id, (int) $slot->id, '2026-09-03', $visitedId);
        $this->markVisitedOnSlot($studentB->id, (int) $slot->id, '2026-09-03', $notAttendedId);

        $this->markVisitedOnSlot($studentA->id, (int) $slot->id, '2026-09-10', $visitedId);
        $this->markVisitedOnSlot($studentB->id, (int) $slot->id, '2026-09-10', $visitedId);

        $row = $this->ltvTeamsRowByTitle('Группа-актуальный-статус');
        $this->assertNotEmpty($row);
        $this->assertSame(2, (int) $row['avg_attendance']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ltvTeamsRowByTitle(string $title, array $extra = []): array
    {
        $rows = $this->ltvTeamsRows($extra);
        foreach ($rows as $row) {
            if ((string) ($row['team_title'] ?? '') === $title) {
                return $row;
            }
        }

        return [];
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

    /**
     * @return list<array<string, mixed>>
     */
    private function ltvTeamsRows(array $extra = []): array
    {
        $query = array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'period' => 'all',
        ], $extra);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.teams.data', $query))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }
}
