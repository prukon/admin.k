<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * «Платежи по месяцам»: колонка и фильтр группы — оплаченная группа, не все группы ученика.
 */
final class PaymentMonthlyReportTeamPivotFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_month_detail_team_title_shows_paid_group_when_team_id_set(): void
    {
        [$student, $teamA] = $this->studentInTwoTeams('МонАльфа', 'МонБета');

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'МонАльфа',
            'summ_cents' => 150000,
            'payment_month' => '2025-09-01',
            'operation_date' => '2025-09-11 20:40:00',
        ]);

        $rows = collect($this->monthPaymentRows('2025-09', ['filter_user_id' => $student->id]));
        $match = $rows->firstWhere('id', $payment->id);

        $this->assertNotNull($match);
        $teamTitle = html_entity_decode((string) ($match['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame('МонАльфа', $teamTitle);
        $this->assertStringNotContainsString('МонБета', $teamTitle);
        $this->assertSame((int) $teamA->id, (int) $payment->team_id);
    }

    public function test_month_detail_team_title_lists_all_groups_for_legacy_payment_without_team_id(): void
    {
        [$student] = $this->studentInTwoTeams('МонАльфа', 'МонБета');

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'summ_cents' => 150000,
            'payment_month' => '2025-09-01',
            'operation_date' => '2025-09-11 20:40:00',
        ]);

        $rows = collect($this->monthPaymentRows('2025-09', ['filter_user_id' => $student->id]));
        $match = $rows->firstWhere('id', $payment->id);

        $this->assertNotNull($match);
        $teamTitle = html_entity_decode((string) ($match['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('МонАльфа', $teamTitle);
        $this->assertStringContainsString('МонБета', $teamTitle);
    }

    public function test_filter_by_team_uses_paid_team_id_when_set(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoTeams('Группа A', 'Группа B');
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа C']);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа A',
            'summ_cents' => 90000,
            'payment_month' => '2025-09-01',
            'operation_date' => '2025-09-11 20:40:00',
        ]);

        $onlyA = collect($this->monthPaymentRows('2025-09', ['filter_team_id' => $teamA->id]));
        $this->assertTrue($onlyA->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyB = collect($this->monthPaymentRows('2025-09', ['filter_team_id' => $teamB->id]));
        $this->assertFalse($onlyB->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyC = collect($this->monthPaymentRows('2025-09', ['filter_team_id' => $teamC->id]));
        $this->assertFalse($onlyC->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));
    }

    public function test_filter_by_team_shows_legacy_payment_without_team_id_if_student_in_team(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoTeams('Группа A', 'Группа B');
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа C']);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'summ_cents' => 90000,
            'payment_month' => '2025-09-01',
            'operation_date' => '2025-09-11 20:40:00',
        ]);

        $onlyA = collect($this->monthPaymentRows('2025-09', ['filter_team_id' => $teamA->id]));
        $this->assertTrue($onlyA->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyB = collect($this->monthPaymentRows('2025-09', ['filter_team_id' => $teamB->id]));
        $this->assertTrue($onlyB->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyC = collect($this->monthPaymentRows('2025-09', ['filter_team_id' => $teamC->id]));
        $this->assertFalse($onlyC->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));
    }

    /**
     * UX Хруль: два платежа в одном месяце за разные группы — две строки,
     * в каждой только оплаченная группа, без склейки названий.
     */
    public function test_two_payments_same_month_show_only_the_paid_group_each_not_concatenated_membership(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoTeams(
            'Робототехника, Главная 10',
            'Футбол, ДСКВ2 , Главная 10'
        );

        $payA = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Робототехника, Главная 10',
            'summ_cents' => 390000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:40:36',
        ]);
        $payB = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'Футбол, ДСКВ2 , Главная 10',
            'summ_cents' => 350000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:41:47',
        ]);

        $rows = collect($this->monthPaymentRows('2026-08', [
            'filter_user_id' => $student->id,
            'status' => '',
        ]));
        $this->assertCount(2, $rows);

        $rowA = $rows->firstWhere('id', $payA->id);
        $rowB = $rows->firstWhere('id', $payB->id);
        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);

        $titleA = $this->decodeTeamTitle($rowA['team_title'] ?? '');
        $titleB = $this->decodeTeamTitle($rowB['team_title'] ?? '');
        $this->assertSame('Робототехника, Главная 10', $titleA);
        $this->assertSame('Футбол, ДСКВ2 , Главная 10', $titleB);
        $this->assertStringNotContainsString('Футбол', $titleA);
        $this->assertStringNotContainsString('Робототехника', $titleB);

        $onlyRobotics = collect($this->monthPaymentRows('2026-08', [
            'filter_user_id' => $student->id,
            'filter_team_id' => $teamA->id,
            'status' => '',
        ]));
        $this->assertTrue($onlyRobotics->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payA->id));
        $this->assertFalse($onlyRobotics->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payB->id));
        $this->assertCount(1, $onlyRobotics);
    }

    public function test_month_summary_count_and_sum_follow_paid_group_filter(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoTeams('Группа A', 'Группа B');

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа A',
            'summ_cents' => 390000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:40:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'Группа B',
            'summ_cents' => 350000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:41:00',
        ]);

        $all = $this->monthSummaryRow('2026-08', [
            'filter_user_id' => $student->id,
            'status' => '',
        ]);
        $this->assertNotNull($all);
        $this->assertSame(2, (int) ($all['payments_count'] ?? 0));
        $this->assertEquals(7400.0, (float) ($all['total_sum'] ?? 0));

        $onlyA = $this->monthSummaryRow('2026-08', [
            'filter_user_id' => $student->id,
            'filter_team_id' => $teamA->id,
            'status' => '',
        ]);
        $this->assertNotNull($onlyA);
        $this->assertSame(1, (int) ($onlyA['payments_count'] ?? 0));
        $this->assertEquals(3900.0, (float) ($onlyA['total_sum'] ?? 0));
    }

    public function test_renamed_paid_group_shows_live_title_not_snapshot_or_other_membership(): void
    {
        [$student, $teamA] = $this->studentInTwoTeams('Старое имя A', 'Группа B');

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Старое имя A',
            'summ_cents' => 150000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:40:00',
        ]);

        $teamA->title = 'Новое имя A';
        $teamA->save();

        $rows = collect($this->monthPaymentRows('2026-08', [
            'filter_user_id' => $student->id,
            'status' => '',
        ]));
        $match = $rows->firstWhere('id', $payment->id);
        $this->assertNotNull($match);
        $title = $this->decodeTeamTitle($match['team_title'] ?? '');
        $this->assertSame('Новое имя A', $title);
        $this->assertStringNotContainsString('Старое имя A', $title);
        $this->assertStringNotContainsString('Группа B', $title);
    }

    public function test_garbage_team_filter_does_not_return_server_error(): void
    {
        [$student, $teamA] = $this->studentInTwoTeams('Группа A', 'Группа B');

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа A',
            'summ_cents' => 90000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:40:00',
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2026-08',
                'mode' => 'subscription',
                'filter_team_id' => 'not-a-team',
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'columns' => $this->monthPaymentsDataTableColumns(),
            ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.payments.monthly.total', [
            'filter_team_id' => 'abc',
        ]))
            ->assertOk()
            ->assertJsonStructure(['total_formatted', 'total_raw']);
    }

    public function test_foreign_partner_payment_is_not_listed_in_month_detail(): void
    {
        [$student, $teamA] = $this->studentInTwoTeams('Группа A', 'Группа B');

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа A',
            'summ_cents' => 90000,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:40:00',
        ]);
        Payment::factory()->create([
            'user_id' => $this->foreignUser->id,
            'partner_id' => $this->foreignUser->partner_id,
            'team_id' => $teamA->id,
            'team_title' => 'Чужая',
            'summ_cents' => 999900,
            'payment_month' => '2026-08-01',
            'operation_date' => '2026-09-11 20:40:00',
        ]);

        $rows = collect($this->monthPaymentRows('2026-08', ['status' => '']));
        $this->assertFalse($rows->contains(fn ($r) => (int) ($r['summ'] ?? 0) === 9999));
        $this->assertFalse($rows->contains(fn ($r) => str_contains($this->decodeTeamTitle($r['team_title'] ?? ''), 'Чужая')));
    }

    public function test_month_total_excludes_payment_for_other_paid_team(): void
    {
        [$student, $teamA, $teamB] = $this->studentInTwoTeams('Группа A', 'Группа B');

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа A',
            'summ_cents' => 390000,
            'payment_month' => '2025-09-01',
            'operation_date' => '2025-09-11 20:40:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'Группа B',
            'summ_cents' => 350000,
            'payment_month' => '2025-09-01',
            'operation_date' => '2025-09-11 20:41:00',
        ]);

        $this->get(route('reports.payments.monthly.total', [
            'filter_user_id' => $student->id,
            'filter_team_id' => $teamA->id,
        ]))
            ->assertOk()
            ->assertJson([
                'total_raw' => 3900.0,
            ]);
    }

    /**
     * @return array{0: User, 1: Team, 2: Team}
     */
    private function studentInTwoTeams(string $titleA, string $titleB): array
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => $titleA]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => $titleB]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'lastname' => 'Хруль',
            'name' => 'Исаак',
            'is_enabled' => 1,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        return [$student, $teamA, $teamB];
    }

    private function decodeTeamTitle(mixed $value): string
    {
        return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function monthSummaryRow(string $yearMonth, array $query = []): ?array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.payments.monthly.data', array_merge([
                'mode' => 'subscription',
                'draw' => 1,
                'start' => 0,
                'length' => 50,
            ], $query)))
            ->assertOk()
            ->json();

        $rows = collect($json['data'] ?? []);

        return $rows->first(function ($row) use ($yearMonth) {
            return (string) ($row['month_key'] ?? '') === $yearMonth;
        });
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function monthPaymentRows(string $yearMonth, array $query = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.payments.monthly.payments', array_merge([
                'yearMonth' => $yearMonth,
                'mode' => 'subscription',
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => $this->monthPaymentsDataTableColumns(),
            ], $query)))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    /**
     * @return list<array{data: string, name: string, searchable: bool, orderable: bool}>
     */
    private function monthPaymentsDataTableColumns(): array
    {
        return [
            ['data' => 'operation_date', 'name' => 'operation_date', 'searchable' => false, 'orderable' => true],
            ['data' => 'user_name', 'name' => 'user_name', 'searchable' => true, 'orderable' => true],
            ['data' => 'team_title', 'name' => 'team_title', 'searchable' => true, 'orderable' => true],
            ['data' => 'summ', 'name' => 'summ', 'searchable' => false, 'orderable' => true],
            ['data' => 'payment_month', 'name' => 'payment_month', 'searchable' => false, 'orderable' => true],
            ['data' => 'payment_provider', 'name' => 'payment_provider', 'searchable' => false, 'orderable' => false],
        ];
    }
}
