<?php

namespace Tests\Feature\Crm\Reports;

use App\Http\Controllers\Admin\Report\DeptReportController;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class DeptReportTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin(); // реальные права reports.view
    }

    /**
     * 1. [P1] Фильтрация задолженностей по партнёру
     */
    public function test_debts_and_getDebts_filter_by_current_partner(): void
    {
        Carbon::setTestNow('2026-02-15');

        // Долги текущего партнёра
        $this->insertUserPrice($this->user, [
            'is_paid'   => 0,
            'price'     => 100,
            'new_month' => '2026-01-01',
        ]);

        // Другой партнёр и его пользователь с долгом
        $otherPartner = \App\Models\Partner::factory()->create();
        $otherUser = User::factory()->create([
            'partner_id' => $otherPartner->id,
            'is_enabled' => 1,
        ]);
        $otherTeam = Team::factory()->create(['partner_id' => $otherPartner->id]);

        $this->insertUserPrice($otherUser, [
            'is_paid'   => 0,
            'price'     => 999,
            'new_month' => '2026-01-01',
        ], $otherTeam);

        // Проверяем debts(): сумма только по текущему партнёру
        $viewResponse = $this->get(route('debts'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertViewIs('admin.report.index');
        $viewResponse->assertViewHas('totalUnpaidPrice', '100');

        // Проверяем getDebts(): строки только по текущему партнёру
        $jsonResponse = $this->get(route('debts.getDebts'), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $jsonResponse->assertStatus(200);
        $data = $jsonResponse->json('data');

        $this->assertNotEmpty($data);

        $userIds = collect($data)->pluck('user_id')->unique()->values()->all();

        $this->assertContains($this->user->id, $userIds);
        $this->assertNotContains($otherUser->id, $userIds);
    }

    /**
     * 2. [P1] Учитываются только неоплаченные, активные и положительные долги
     */
    public function test_getDebts_returns_only_unpaid_active_positive_debts(): void
    {
        Carbon::setTestNow('2026-02-15');

        // Активный пользователь текущего партнёра (из CrmTestCase)
        $activeUser = $this->user;

        // Неактивный пользователь
        $disabledUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 0,
        ]);

        $month = '2026-01-01';

        $teamUnpaid = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamPaid = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamZero = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamDisabled = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->insertUserPrice($activeUser, [
            'is_paid'   => 0,
            'price'     => 100,
            'new_month' => $month,
        ], $teamUnpaid);

        $this->insertUserPrice($activeUser, [
            'is_paid'   => 1,
            'price'     => 200,
            'new_month' => $month,
        ], $teamPaid);

        $this->insertUserPrice($disabledUser, [
            'is_paid'   => 0,
            'price'     => 300,
            'new_month' => $month,
        ], $teamDisabled);

        $this->insertUserPrice($activeUser, [
            'is_paid'   => 0,
            'price'     => 0,
            'new_month' => $month,
        ], $teamZero);

        $response = $this->get(route('debts.getDebts'), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Должна быть ровно 1 запись
        $this->assertCount(1, $data);

        $row = $data[0];
        $this->assertSame($activeUser->id, $row['user_id']);
        $this->assertEquals(100, $row['price']);
    }

    /**
     * 3. [P1] Граница по месяцу: new_month < currentMonth
     */
    public function test_getDebts_respects_month_boundary_before_currentMonth(): void
    {
        Carbon::setTestNow('2026-02-15');

        $user = $this->user;

        $this->insertUserPrice($user, [
            'is_paid'   => 0,
            'price'     => 100,
            'new_month' => '2026-01-01',
        ]);
        $this->insertUserPrice($user, [
            'is_paid'   => 0,
            'price'     => 200,
            'new_month' => '2026-02-01',
        ]);
        $this->insertUserPrice($user, [
            'is_paid'   => 0,
            'price'     => 300,
            'new_month' => '2025-12-01',
        ]);

        $response = $this->get(route('debts.getDebts'), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);

        $months = collect($data)->pluck('month')->sort()->values()->all();
        $this->assertSame(['Декабрь 2025', 'Январь 2026'], $months);
    }

    /**
     * 4. [P1] Корректность общей суммы totalUnpaidPrice в debts()
     */
    public function test_debts_totalUnpaidPrice_matches_sum_from_getDebts(): void
    {
        Carbon::setTestNow('2026-02-15');

        $user = $this->user;

        $this->insertUserPrice($user, [
            'is_paid'   => 0,
            'price'     => 100,
            'new_month' => '2026-01-01',
        ]);
        $this->insertUserPrice($user, [
            'is_paid'   => 0,
            'price'     => 250,
            'new_month' => '2025-12-01',
        ]);
        $this->insertUserPrice($user, [
            'is_paid'   => 0,
            'price'     => 500,
            'new_month' => '2025-11-01',
        ]);

        $jsonResponse = $this->get(route('debts.getDebts'), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $jsonResponse->assertStatus(200);
        $data = $jsonResponse->json('data');

        $sumFromJson = collect($data)->sum('price');
        $this->assertEquals(850, $sumFromJson);

        $viewResponse = $this->get(route('debts'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertViewIs('admin.report.index');
        $viewResponse->assertViewHas('totalUnpaidPrice', '850');
    }

    /**
     * 6. [P1] Пустой отчёт: нет задолженностей
     */
    public function test_getDebts_and_debts_return_empty_result_when_no_debts(): void
    {
        Carbon::setTestNow('2026-02-15');

        // Ничего не создаём в users_prices

        $jsonResponse = $this->get(route('debts.getDebts'), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $jsonResponse->assertStatus(200);
        $data = $jsonResponse->json('data');

        $this->assertIsArray($data);
        $this->assertCount(0, $data);

        $viewResponse = $this->get(route('debts'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertViewIs('admin.report.index');
        $viewResponse->assertViewHas('totalUnpaidPrice', '0');
    }

    /**
     * 7. [P2] Структура JSON для DataTables
     */
    public function test_getDebts_returns_valid_datatables_json_structure(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->insertUserPrice($this->user, [
            'is_paid'   => 0,
            'price'     => 123.45,
            'new_month' => '2026-01-01',
        ]);

        $response = $this->get(route('debts.getDebts', ['draw' => 1]), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);
        $this->assertNotEmpty($json['data']);

        $row = $json['data'][0];

        $this->assertArrayHasKey('user_id', $row);
        $this->assertArrayHasKey('user_name', $row);
        $this->assertArrayHasKey('month', $row);
        $this->assertArrayHasKey('price', $row);

        $this->assertIsNumeric($row['price']);
    }

    /**
     * 9. [P1] Проверка доступа по праву can:reports.view
     */
    public function test_debts_routes_require_reports_view_permission(): void
    {
        // Создаём пользователя без права reports.view
        $unauthorizedUser = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        // Не ставим ему reports_view_allowed => Gate::before вернёт false
        $this->actingAs($unauthorizedUser);

        // debts
        $this->get(route('debts'))
            ->assertStatus(403);

        // getDebts (AJAX)
        $this->get(route('debts.getDebts'), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->assertStatus(403);
    }

    /**
     * [P0] /admin/reports/debts/total отдаёт сумму задолженности по тем же фильтрам.
     */
    public function test_debts_total_endpoint_returns_formatted_and_raw_sum(): void
    {
        // В CrmTestCase current_partner установлен, но права reports.view нет → дадим superadmin для доступа.
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        // Долги текущего партнёра
        $this->insertUserPrice($this->user, [
            'price'     => 1000,
            'is_paid'   => 0,
            'new_month' => '2020-01-01',
        ]);
        $this->insertUserPrice($this->user, [
            'price'     => 2000,
            'is_paid'   => 0,
            'new_month' => '2020-02-01',
        ]);

        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $this->insertUserPrice($this->foreignUser, [
            'price'     => 9999,
            'is_paid'   => 0,
            'new_month' => now()->subMonths(1)->format('Y-m-01'),
        ], $foreignTeam);

        $expectedRaw = 3000.0;
        $expectedFormatted = number_format($expectedRaw, 0, '', ' ');

        $this->get(route('reports.debts.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => $expectedFormatted,
                'total_raw' => $expectedRaw,
            ]);
    }

    /**
     * 11. [P3] Поведение formatedDate при некорректном формате месяца
     */
    public function test_formatedDate_returns_null_for_invalid_month_string(): void
    {
        /** @var DeptReportController $controller */
        $controller = app(DeptReportController::class);

        $result = $controller->formatedDate('НепонятныйФормат 2026');

        $this->assertNull($result);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_debts_page_shows_location_filter_with_locations_view(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Долги филиал',
            'is_enabled' => true,
        ]);

        $this->get(route('debts'))
            ->assertOk()
            ->assertSee('pay-debt-filter-location', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_debts_total_respects_filter_location_id(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'location_id' => $locA->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'location_id' => $locB->id]);

        $userA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id' => $teamA->id,
        ]);
        $userB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id' => $teamB->id,
        ]);

        $this->insertUserPrice($userA, [
            'is_paid'   => 0,
            'price'     => 1000,
            'new_month' => '2026-01-01',
        ], $teamA);
        $this->insertUserPrice($userB, [
            'is_paid'   => 0,
            'price'     => 2000,
            'new_month' => '2026-01-01',
        ], $teamB);

        $this->get(route('reports.debts.total', ['filter_location_id' => $locA->id]))
            ->assertOk()
            ->assertJson(['total_raw' => 1000.0]);

        $this->get(route('reports.debts.total', ['filter_location_id' => $locB->id]))
            ->assertOk()
            ->assertJson(['total_raw' => 2000.0]);
    }
}