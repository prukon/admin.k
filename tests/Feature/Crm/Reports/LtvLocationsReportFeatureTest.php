<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Services\TeamUserSyncService;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отчёт «Платежи по объектам»: группировка по payments.location_id, право reports.ltv.locations.view.
 */
final class LtvLocationsReportFeatureTest extends CrmTestCase
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

    public function test_page_requires_reports_ltv_locations_view(): void
    {
        $denied = $this->createUserWithoutPermission('reports.ltv.locations.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('reports.ltv.locations'))->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.locations.data', ['draw' => 1]))
            ->assertForbidden();
        $this->get(route('reports.ltv.locations.total'))->assertForbidden();
    }

    public function test_guest_cannot_open_page(): void
    {
        Auth::logout();

        $this->get(route('reports.ltv.locations'))->assertRedirect();
        $this->getJson(route('reports.ltv.locations.total'))->assertStatus(401);
    }

    public function test_trainer_without_reports_view_can_open_tab_and_sees_menu(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);

        $html = $this->get(route('reports.ltv.locations'))->assertOk()->getContent();
        $this->assertStringContainsString('Платежи по объектам', $html);
        $this->assertStringContainsString('id="ltv-locations-table"', $html);
        $this->assertStringNotContainsString('Платежи по ученикам', $html);
        $this->assertStringContainsString('Отчеты', $html);
    }

    public function test_admin_page_shows_tab_next_to_teams_ltv(): void
    {
        $this->asAdmin();
        $html = $this->get(route('reports.ltv.locations'))->assertOk()->getContent();

        $teamsPos = strpos($html, 'Платежи по группам');
        $locationsPos = strpos($html, 'Платежи по объектам');
        $this->assertNotFalse($teamsPos);
        $this->assertNotFalse($locationsPos);
        $this->assertTrue($teamsPos < $locationsPos);
        $this->assertStringContainsString('id="ltv-locations-table"', $html);
        $this->assertStringContainsString('<th>Объект</th>', $html);
        $this->assertStringContainsString('<th>Ученики</th>', $html);
        $this->assertStringContainsString('<th>Ср. посещаемость</th>', $html);
        $this->assertStringContainsString('<th>ФИО</th>', $html);
        $this->assertStringContainsString('<th>Группа</th>', $html);
        $this->assertStringContainsString("KidsCrmDataTable.create('#ltv-locations-table'", $html);
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
        $this->assertStringContainsString("data: 'team_title'", $html);
        $this->assertStringContainsString("name: 'team_title'", $html);
    }

    public function test_non_ajax_data_returns_404(): void
    {
        $this->asAdmin();
        $this->get(route('reports.ltv.locations.data', ['draw' => 1]))->assertNotFound();
    }

    public function test_aggregates_one_row_per_paid_location_and_null_as_without_object(): void
    {
        $this->asAdmin();

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект-Альфа',
            'is_enabled' => 1,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект-Бета',
            'is_enabled' => 0,
        ]);

        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-Альфа',
            'location_id' => $locA->id,
            'is_enabled' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-Бета',
            'location_id' => $locB->id,
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
            'location_id' => $locA->id,
            'summ_cents' => 100000,
            'operation_date' => '2026-09-01 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $studentOne->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа-Альфа',
            'location_id' => $locA->id,
            'summ_cents' => 50000,
            'operation_date' => '2026-09-02 11:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $studentTwo->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'Группа-Бета',
            'location_id' => $locB->id,
            'summ_cents' => 200000,
            'operation_date' => '2026-09-03 12:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $studentTwo->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'location_id' => null,
            'summ_cents' => 70000,
            'operation_date' => '2026-09-04 13:00:00',
        ]);

        $rows = $this->ltvLocationsRows();
        $byName = [];
        foreach ($rows as $row) {
            $byName[(string) $row['location_name']] = $row;
        }

        $this->assertArrayHasKey('Объект-Альфа', $byName);
        $this->assertSame(2, (int) $byName['Объект-Альфа']['payment_count']);
        $this->assertEquals(1500.0, (float) $byName['Объект-Альфа']['total_price']);
        $this->assertStringContainsString('Иванов', (string) $byName['Объект-Альфа']['user_names']);
        $this->assertSame(['Иванов Пётр'], $byName['Объект-Альфа']['user_names_items']);
        $this->assertSame((int) $locA->id, (int) $byName['Объект-Альфа']['location_id']);
        $this->assertTrue(
            $byName['Объект-Альфа']['avg_attendance'] === null
            || $byName['Объект-Альфа']['avg_attendance'] === ''
        );

        $this->assertArrayHasKey('Объект-Бета', $byName);
        $this->assertSame(1, (int) $byName['Объект-Бета']['payment_count']);
        $this->assertEquals(2000.0, (float) $byName['Объект-Бета']['total_price']);
        $this->assertSame(['Сидорова Анна'], $byName['Объект-Бета']['user_names_items']);

        $this->assertArrayHasKey('Без объекта', $byName);
        $this->assertSame(0, (int) $byName['Без объекта']['location_id']);
        $this->assertSame(1, (int) $byName['Без объекта']['payment_count']);
        $this->assertEquals(700.0, (float) $byName['Без объекта']['total_price']);
        $this->assertTrue(
            $byName['Без объекта']['avg_attendance'] === null
            || $byName['Без объекта']['avg_attendance'] === ''
        );

        $this->get(route('reports.ltv.locations.total', ['period' => 'all']))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(4200, 0, '', ' '),
                'total_raw' => 4200.0,
            ]);
    }

    public function test_user_names_items_lists_each_paying_student(): void
    {
        $this->asAdmin();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Два-ученика-объект',
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Два-ученика',
            'location_id' => $location->id,
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
            'location_id' => $location->id,
            'summ_cents' => 10000,
            'operation_date' => '2026-09-01 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $second->id,
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'team_title' => 'Два-ученика',
            'location_id' => $location->id,
            'summ_cents' => 20000,
            'operation_date' => '2026-09-02 11:00:00',
        ]);

        $rows = $this->ltvLocationsRows(['period' => 'all']);
        $this->assertNotEmpty($rows);
        $row = $rows[0];
        $this->assertSame('Алексеев Игорь, Борисова Мария', $row['user_names']);
        $this->assertSame(['Алексеев Игорь', 'Борисова Мария'], $row['user_names_items']);
    }

    public function test_nested_location_payments_include_user_name_and_team_title(): void
    {
        $this->asAdmin();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Детализация-объект',
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Детализация-группа',
            'location_id' => $location->id,
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
            'location_id' => $location->id,
            'summ_cents' => 123400,
            'operation_date' => '2026-09-10 15:30:00',
            'payment_month' => '2026-09-01',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.locations.payments', [
                'location' => $location->id,
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
        $this->assertStringContainsString('Детализация-группа', (string) ($row['team_title'] ?? ''));
        $this->assertArrayHasKey('payment_provider', $row);
        $this->assertSame(1, (int) ($json['meta_payments_count'] ?? 0));
        $this->assertEquals(1234.0, (float) ($json['meta_sum_total'] ?? 0));
    }

    public function test_nested_payments_for_null_location_are_allowed(): void
    {
        $this->asAdmin();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Безъектов',
            'name' => 'Олег',
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'location_id' => null,
            'summ_cents' => 45000,
            'operation_date' => '2026-09-12 09:00:00',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.locations.payments', [
                'location' => 0,
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'period' => 'all',
            ]))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($json['data'] ?? []);
        $row = $json['data'][0];
        $this->assertStringContainsString('Безъектов', (string) ($row['user_name'] ?? ''));
        $this->assertSame('Без команды', (string) ($row['team_title'] ?? ''));
        $this->assertSame(1, (int) ($json['meta_payments_count'] ?? 0));
        $this->assertEquals(450.0, (float) ($json['meta_sum_total'] ?? 0));
    }

    public function test_datatable_search_matches_location_name_not_amount(): void
    {
        $this->asAdmin();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'УникальныйОбъектLTV',
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-поиск',
            'location_id' => $location->id,
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
            'team_title' => 'Группа-поиск',
            'location_id' => $location->id,
            'summ_cents' => 999900,
            'operation_date' => '2026-09-05 10:00:00',
        ]);

        $hit = $this->ltvLocationsRows(['search' => ['value' => 'УникальныйОбъектLTV']]);
        $this->assertNotEmpty($hit);
        $this->assertSame('УникальныйОбъектLTV', $hit[0]['location_name']);

        $miss = $this->ltvLocationsRows(['search' => ['value' => '9999']]);
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

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект-посещаемость',
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-посещаемость',
            'location_id' => $location->id,
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
            'location_id' => $location->id,
            'summ_cents' => 10000,
            'operation_date' => '2026-09-01 10:00:00',
            'payment_month' => '2026-09-01',
        ]);

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

        $allTime = $this->ltvLocationsRowByName('Объект-посещаемость');
        $this->assertNotEmpty($allTime);
        $this->assertSame(3, (int) $allTime['avg_attendance']);

        $september = $this->ltvLocationsRowByName('Объект-посещаемость', ['payment_month' => '2026-09']);
        $this->assertNotEmpty($september);
        $this->assertSame(3, (int) $september['avg_attendance']);
    }

    public function test_avg_attendance_payment_month_limits_journal_period(): void
    {
        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $visitedId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект-месяц-посещаемость',
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа-месяц-посещаемость',
            'location_id' => $location->id,
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
            'location_id' => $location->id,
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

        $allTime = $this->ltvLocationsRowByName('Объект-месяц-посещаемость');
        $this->assertNotEmpty($allTime);
        $this->assertSame(3, (int) $allTime['avg_attendance']);

        $september = $this->ltvLocationsRowByName('Объект-месяц-посещаемость', ['payment_month' => '2026-09']);
        $this->assertNotEmpty($september);
        $this->assertSame(2, (int) $september['avg_attendance']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ltvLocationsRowByName(string $name, array $extra = []): array
    {
        $rows = $this->ltvLocationsRows($extra);
        foreach ($rows as $row) {
            if ((string) ($row['location_name'] ?? '') === $name) {
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
    private function ltvLocationsRows(array $extra = []): array
    {
        $query = array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'period' => 'all',
        ], $extra);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.locations.data', $query))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }
}
