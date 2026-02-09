<?php

namespace Tests\Feature\Crm;

use App\Http\Controllers\Admin\Report\DeptReportController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeptReportTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Явно подставляем текущего партнёра в контейнер,
        // чтобы app('current_partner') в контроллере всегда работал в тестах.
        app()->instance('current_partner', $this->partner);

        // Глобальная настройка доступа к отчётам:
        // право reports-view определяется флагом на пользователе.
        Gate::before(function ($user, string $ability) {
            if ($ability === 'reports-view') {
                return $user->reports_view_allowed ?? false;
            }

            return null;
        });

        // По умолчанию пользователь из CrmTestCase имеет доступ к отчётам
        $this->user->reports_view_allowed = true;
    }

    /**
     * 1. [P1] Фильтрация задолженностей по партнёру
     */
    public function test_debts_and_getDebts_filter_by_current_partner(): void
    {
        Carbon::setTestNow('2026-02-15');

        // Долги текущего партнёра
        DB::table('users_prices')->insert([
            [
                'user_id'    => $this->user->id,
                'is_paid'    => 0,
                'price'      => 100,
                'new_month'  => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Другой партнёр и его пользователь с долгом
        $otherPartner = \App\Models\Partner::factory()->create();
        $otherUser = User::factory()->create([
            'partner_id' => $otherPartner->id,
            'is_enabled' => 1,
        ]);

        DB::table('users_prices')->insert([
            [
                'user_id'    => $otherUser->id,
                'is_paid'    => 0,
                'price'      => 999,
                'new_month'  => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

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

        DB::table('users_prices')->insert([
            // 1. Нужная запись: неоплачено, активный юзер, price > 0
            [
                'user_id'    => $activeUser->id,
                'is_paid'    => 0,
                'price'      => 100,
                'new_month'  => $month,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 2. Оплачено — не должно попасть
            [
                'user_id'    => $activeUser->id,
                'is_paid'    => 1,
                'price'      => 200,
                'new_month'  => $month,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 3. Неактивный пользователь — не должно попасть
            [
                'user_id'    => $disabledUser->id,
                'is_paid'    => 0,
                'price'      => 300,
                'new_month'  => $month,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 4. Нулевая цена — не должно попасть
            [
                'user_id'    => $activeUser->id,
                'is_paid'    => 0,
                'price'      => 0,
                'new_month'  => $month,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

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

        DB::table('users_prices')->insert([
            // Прошлый месяц — должен попасть
            [
                'user_id'    => $user->id,
                'is_paid'    => 0,
                'price'      => 100,
                'new_month'  => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Текущий месяц — не должен попасть
            [
                'user_id'    => $user->id,
                'is_paid'    => 0,
                'price'      => 200,
                'new_month'  => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Более ранний месяц — должен попасть
            [
                'user_id'    => $user->id,
                'is_paid'    => 0,
                'price'      => 300,
                'new_month'  => '2025-12-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('debts.getDebts'), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);

        $months = collect($data)->pluck('month')->sort()->values()->all();
        $this->assertSame(['2025-12-01', '2026-01-01'], $months);
    }

    /**
     * 4. [P1] Корректность общей суммы totalUnpaidPrice в debts()
     */
    public function test_debts_totalUnpaidPrice_matches_sum_from_getDebts(): void
    {
        Carbon::setTestNow('2026-02-15');

        $user = $this->user;

        DB::table('users_prices')->insert([
            [
                'user_id'    => $user->id,
                'is_paid'    => 0,
                'price'      => 100,
                'new_month'  => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id'    => $user->id,
                'is_paid'    => 0,
                'price'      => 250,
                'new_month'  => '2025-12-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id'    => $user->id,
                'is_paid'    => 0,
                'price'      => 500,
                'new_month'  => '2025-11-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
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

        DB::table('users_prices')->insert([
            [
                'user_id'    => $this->user->id,
                'is_paid'    => 0,
                'price'      => 123.45,
                'new_month'  => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
     * 9. [P1] Проверка доступа по праву can:reports-view
     */
    public function test_debts_routes_require_reports_view_permission(): void
    {
        // Создаём пользователя без права reports-view
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
     * 11. [P3] Поведение formatedDate при некорректном формате месяца
     */
    public function test_formatedDate_returns_null_for_invalid_month_string(): void
    {
        /** @var DeptReportController $controller */
        $controller = app(DeptReportController::class);

        $result = $controller->formatedDate('НепонятныйФормат 2026');

        $this->assertNull($result);
    }
}