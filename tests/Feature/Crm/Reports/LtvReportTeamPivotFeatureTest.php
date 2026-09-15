<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отчёт LTV: одна строка на ученика; колонка «Группа» — оплаченные группы, не все текущие.
 */
final class LtvReportTeamPivotFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ltvRows(array $query = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.data', $query))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    public function test_ltv_aggregates_one_row_per_student_with_comma_separated_legacy_teams(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'summ_cents' => 100000,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'summ_cents' => 50000,
        ]);

        $rows = collect($this->ltvRows(['filter_user_id' => $student->id]));
        $studentRows = $rows->filter(fn ($r) => (int) ($r['user_id'] ?? 0) === $student->id);

        $this->assertCount(1, $studentRows, 'LTV: один ученик — одна агрегированная строка');

        $row = $studentRows->first();
        $teamTitle = html_entity_decode((string) ($row['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('LTV-A', $teamTitle);
        $this->assertStringContainsString('LTV-B', $teamTitle);
        $this->assertEquals(1500, (float) ($row['total_price'] ?? 0));
        $this->assertEquals(2, (int) ($row['payment_count'] ?? 0));
    }

    public function test_ltv_team_title_shows_paid_groups_not_all_membership(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'LTV-A',
            'summ_cents' => 100000,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'LTV-A',
            'summ_cents' => 50000,
        ]);

        $rows = collect($this->ltvRows(['filter_user_id' => $student->id]));
        $row = $rows->first(fn ($r) => (int) ($r['user_id'] ?? 0) === $student->id);
        $this->assertNotNull($row);

        $teamTitle = html_entity_decode((string) ($row['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame('LTV-A', $teamTitle);
        $this->assertStringNotContainsString('LTV-B', $teamTitle);
        $this->assertEquals(1500, (float) ($row['total_price'] ?? 0));
    }

    public function test_ltv_filter_by_team_uses_paid_team_id(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'LTV-A',
            'summ_cents' => 390000,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'LTV-B',
            'summ_cents' => 350000,
        ]);

        $rowsA = collect($this->ltvRows([
            'filter_user_id' => $student->id,
            'filter_team_id' => $teamA->id,
        ]));
        $rowA = $rowsA->first(fn ($r) => (int) ($r['user_id'] ?? 0) === $student->id);
        $this->assertNotNull($rowA);
        $this->assertEquals(3900, (float) ($rowA['total_price'] ?? 0));
        $this->assertEquals(1, (int) ($rowA['payment_count'] ?? 0));
        $teamTitleA = html_entity_decode((string) ($rowA['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame('LTV-A', $teamTitleA);

        $this->get(route('reports.ltv.total', [
            'filter_user_id' => $student->id,
            'filter_team_id' => $teamA->id,
        ]))
            ->assertOk()
            ->assertJson(['total_raw' => 3900.0]);

        $rowsB = collect($this->ltvRows([
            'filter_user_id' => $student->id,
            'filter_team_id' => $teamB->id,
        ]));
        $rowB = $rowsB->first(fn ($r) => (int) ($r['user_id'] ?? 0) === $student->id);
        $this->assertNotNull($rowB);
        $this->assertEquals(3500, (float) ($rowB['total_price'] ?? 0));
    }

    /**
     * UX Хруль: ученик в двух группах, два платежа — в сводке только оплаченные группы,
     * без третьей группы, в которой он состоит, но не платил.
     */
    public function test_ltv_summary_lists_only_paid_groups_not_unpaid_membership(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Робототехника, Главная 10']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Футбол, ДСКВ2 , Главная 10']);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Лишняя группа']);

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
        $sync->attachTeamForStudent($student, (int) $teamC->id);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Робототехника, Главная 10',
            'summ_cents' => 390000,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'Футбол, ДСКВ2 , Главная 10',
            'summ_cents' => 350000,
        ]);

        $rows = collect($this->ltvRows(['filter_user_id' => $student->id, 'status' => '']));
        $row = $rows->first(fn ($r) => (int) ($r['user_id'] ?? 0) === $student->id);
        $this->assertNotNull($row);

        $teamTitle = $this->decodeTeamTitle($row['team_title'] ?? '');
        $this->assertStringContainsString('Робототехника, Главная 10', $teamTitle);
        $this->assertStringContainsString('Футбол, ДСКВ2 , Главная 10', $teamTitle);
        $this->assertStringNotContainsString('Лишняя группа', $teamTitle);
        $this->assertEquals(7400, (float) ($row['total_price'] ?? 0));
        $this->assertEquals(2, (int) ($row['payment_count'] ?? 0));
    }

    public function test_renamed_paid_group_in_ltv_shows_live_title_not_snapshot(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Старое имя A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'is_enabled' => 1,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Старое имя A',
            'summ_cents' => 100000,
        ]);

        $teamA->title = 'Новое имя A';
        $teamA->save();

        $rows = collect($this->ltvRows(['filter_user_id' => $student->id, 'status' => '']));
        $row = $rows->first(fn ($r) => (int) ($r['user_id'] ?? 0) === $student->id);
        $this->assertNotNull($row);
        $teamTitle = $this->decodeTeamTitle($row['team_title'] ?? '');
        $this->assertSame('Новое имя A', $teamTitle);
        $this->assertStringNotContainsString('Старое имя A', $teamTitle);
        $this->assertStringNotContainsString('Группа B', $teamTitle);
    }

    /**
     * UI шлёт filter_team_id во вложенную таблицу (ltvReportFilterParams),
     * как monthly. Без фильтра — оба платежа; с фильтром — только оплаченная группа.
     */
    public function test_ltv_nested_payments_honor_paid_group_filter_like_monthly(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'is_enabled' => 1,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        $payA = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'LTV-A',
            'summ_cents' => 390000,
        ]);
        $payB = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'team_title' => 'LTV-B',
            'summ_cents' => 350000,
        ]);

        $all = collect($this->ltvNestedPaymentRows($student->id, ['status' => '']));
        $this->assertTrue($all->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payA->id));
        $this->assertTrue($all->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payB->id));

        $onlyA = collect($this->ltvNestedPaymentRows($student->id, [
            'filter_team_id' => $teamA->id,
            'status' => '',
        ]));
        $this->assertTrue($onlyA->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payA->id));
        $this->assertFalse($onlyA->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payB->id));

        $drawJson = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $student->id,
                'filter_team_id' => $teamA->id,
                'status' => '',
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'columns' => $this->ltvNestedDataTableColumns(),
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($drawJson['meta_payments_count'] ?? 0));
        $this->assertEquals(3900.0, (float) ($drawJson['meta_sum_total'] ?? 0));
        $this->assertCount(1, $drawJson['data'] ?? []);
    }

    public function test_ltv_user_payments_detail_shows_paid_team_title(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'LTV-A',
            'summ_cents' => 100000,
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.user_payments', ['user' => $student->id]))
            ->assertOk()
            ->json();

        $items = collect($json['payments'] ?? []);
        $match = $items->first(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id);
        $this->assertNotNull($match);
        $teamTitle = html_entity_decode((string) ($match['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame('LTV-A', $teamTitle);
        $this->assertStringNotContainsString('LTV-B', $teamTitle);
    }

    private function decodeTeamTitle(mixed $value): string
    {
        return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function ltvNestedPaymentRows(int $userId, array $query = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.user_payments', array_merge([
                'user' => $userId,
            ], $query)))
            ->assertOk()
            ->json();

        return $json['payments'] ?? [];
    }

    /**
     * @return list<array{data: string, name: string, searchable: bool, orderable: bool}>
     */
    private function ltvNestedDataTableColumns(): array
    {
        return [
            ['data' => 'operation_date', 'name' => 'operation_date', 'searchable' => true, 'orderable' => true],
            ['data' => 'summ', 'name' => 'summ', 'searchable' => false, 'orderable' => true],
            ['data' => 'payment_month', 'name' => 'payment_month', 'searchable' => true, 'orderable' => true],
            ['data' => 'payment_provider', 'name' => 'payment_provider', 'searchable' => false, 'orderable' => false],
        ];
    }
}
