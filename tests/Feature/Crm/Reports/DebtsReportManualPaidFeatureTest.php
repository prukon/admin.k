<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Фича: отчёт «Задолженности» учитывает effective_is_paid
 * (ручная отметка из «Установка цен» / доп. платежей).
 *
 * UX-баг (до фикса): is_manual_paid=1 при is_paid=0 оставлял месяц в долгах.
 *
 * @see \App\Http\Controllers\Admin\Report\DeptReportController
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class DebtsReportManualPaidFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-02-15');

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $this->student = $this->makeStudentWithTeams([$this->team]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // UX-баг: ручная оплата через реальный HTTP → долг пропадает
    // -------------------------------------------------------------------------

    public function test_manual_paid_via_setting_prices_removes_month_from_debts_report_and_total(): void
    {
        $this->grantPermissionForUser($this->user, 'setPrices.manualPaid.manage');
        $this->actingAs($this->user->fresh());

        $this->insertUserPrice($this->student, [
            'is_paid' => 0,
            'is_manual_paid' => null,
            'price' => 1500,
            'new_month' => '2026-01-01',
        ], $this->team);

        $this->assertDebtTotal(1500.0);
        $this->assertDebtPrices([1500.0]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Январь 2026',
                'mode' => 'paid',
                'comment' => 'Оплата наличными в кассе',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.effective_is_paid', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-01-01',
            'is_paid' => 0,
            'is_manual_paid' => 1,
        ]);

        $this->assertDebtTotal(0.0);
        $this->assertDebtPrices([]);

        $this->get(route('debts'))
            ->assertOk()
            ->assertViewHas('totalUnpaidPrice', '0');
    }

    public function test_manual_unpaid_override_puts_auto_paid_month_back_into_debts(): void
    {
        $this->grantPermissionForUser($this->user, 'setPrices.manualPaid.manage');
        $this->actingAs($this->user->fresh());

        $this->insertUserPrice($this->student, [
            'is_paid' => 1,
            'is_manual_paid' => null,
            'price' => 2200,
            'new_month' => '2026-01-01',
        ], $this->team);

        $this->assertDebtTotal(0.0);
        $this->assertDebtPrices([]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Январь 2026',
                'mode' => 'unpaid',
                'comment' => 'Ошибочный webhook, возврат долга',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.effective_is_paid', false);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-01-01',
            'is_paid' => 1,
            'is_manual_paid' => 0,
        ]);

        $this->assertDebtTotal(2200.0);
        $this->assertDebtPrices([2200.0]);
    }

    public function test_manual_paid_custom_payment_removes_row_from_debts_report(): void
    {
        $this->grantPermissionForUser($this->user, 'setPrices.manualPaid.manage');
        $this->grantPermissionForUser($this->user, 'setPrices.customPayments.view');
        $this->actingAs($this->user->fresh());

        $payment = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount_cents' => 80000,
            'is_paid' => false,
            'is_manual_paid' => null,
            'date_start' => '2025-12-01',
            'date_end' => '2025-12-31',
            'note' => 'Доп. платёж для долга',
        ]);

        $this->assertDebtTotal(800.0);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]), [
                'mode' => 'paid',
                'comment' => 'Перевод на карту тренера',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.effective_is_paid', true);

        $this->assertDatabaseHas('user_custom_payment', [
            'id' => $payment->id,
            'is_paid' => 0,
            'is_manual_paid' => 1,
        ]);

        $this->assertDebtTotal(0.0);
        $this->assertDebtPrices([]);
    }

    public function test_manual_unpaid_custom_payment_puts_auto_paid_row_back_into_debts(): void
    {
        $this->grantPermissionForUser($this->user, 'setPrices.manualPaid.manage');
        $this->grantPermissionForUser($this->user, 'setPrices.customPayments.view');
        $this->actingAs($this->user->fresh());

        $payment = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount_cents' => 45000,
            'is_paid' => true,
            'is_manual_paid' => null,
            'date_start' => '2025-11-01',
            'date_end' => '2025-11-30',
            'note' => 'Уже оплачен онлайн',
        ]);

        $this->assertDebtTotal(0.0);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]), [
                'mode' => 'unpaid',
                'comment' => 'Возврат / отмена оплаты',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.effective_is_paid', false);

        $this->assertDebtTotal(450.0);
        $this->assertDebtPrices([450.0]);
    }

    public function test_auto_paid_month_without_manual_flag_is_not_shown_as_debt(): void
    {
        $this->insertUserPrice($this->student, [
            'is_paid' => 1,
            'is_manual_paid' => null,
            'price' => 999,
            'new_month' => '2026-01-01',
        ], $this->team);

        $this->assertDebtTotal(0.0);
        $this->assertDebtPrices([]);
    }

    public function test_manual_paid_with_short_comment_returns_field_error_and_keeps_debt(): void
    {
        $this->grantPermissionForUser($this->user, 'setPrices.manualPaid.manage');
        $this->actingAs($this->user->fresh());

        $this->insertUserPrice($this->student, [
            'is_paid' => 0,
            'price' => 500,
            'new_month' => '2026-01-01',
        ], $this->team);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Январь 2026',
                'mode' => 'paid',
                'comment' => 'ab',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-01-01',
            'is_manual_paid' => null,
        ]);

        $this->assertDebtTotal(500.0);
    }

    public function test_manual_paid_non_ajax_redirects_updates_row_and_clears_debt(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-01-01',
            'price_cents' => 130000,
            'is_paid' => 0,
            'is_manual_paid' => null,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);

        $this->assertDebtTotal(1300.0);

        $response = $this->post(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Январь 2026',
            'mode' => 'paid',
            'comment' => 'Non-AJAX fallback из формы',
        ]);

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => 1,
        ]);

        $this->assertDebtTotal(0.0);
    }

    // -------------------------------------------------------------------------
    // HTTP / доступ к отчёту задолженностей
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_any_debts_endpoint(): void
    {
        Auth::logout();

        foreach ($this->debtsEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_reports_view_gets_403_on_all_debts_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->debtsEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без reports.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_authorized_user_debts_endpoints_return_expected_statuses(): void
    {
        $this->insertUserPrice($this->student, [
            'is_paid' => 0,
            'price' => 100,
            'new_month' => '2026-01-01',
        ], $this->team);

        $this->get(route('debts'))->assertOk();
        $this->get(route('reports.debts.total'))
            ->assertOk()
            ->assertJsonStructure(['total_formatted', 'total_raw']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.debts.columns-settings.get'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('reports.debts.columns-settings.save'), [
                'columns' => [
                    'user_name' => true,
                    'month' => true,
                    'price' => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_get_debts_without_ajax_header_returns_404(): void
    {
        $this->get(route('debts.getDebts'))->assertNotFound();
    }

    public function test_debts_columns_settings_validation_requires_columns_array(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('reports.debts.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    // -------------------------------------------------------------------------
    // Blade / UI-контракт страницы задолженностей
    // -------------------------------------------------------------------------

    public function test_debts_page_renders_default_active_status_and_effective_total_after_manual_paid(): void
    {
        $teamPaid = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamOpen = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudentWithTeams([$teamPaid, $teamOpen]);

        $this->insertUserPrice($student, [
            'is_paid' => 0,
            'is_manual_paid' => 1,
            'price' => 5000,
            'new_month' => '2026-01-01',
        ], $teamPaid);

        $this->insertUserPrice($student, [
            'is_paid' => 0,
            'is_manual_paid' => null,
            'price' => 750,
            'new_month' => '2025-12-01',
        ], $teamOpen);

        $this->withoutVite();
        $html = $this->get(route('debts'))
            ->assertOk()
            ->assertViewHas('totalUnpaidPrice', '750')
            ->assertSee('id="debts-table"', false)
            ->assertSee('id="debt-report-filters"', false)
            ->assertSee('pay-debt-filter-user-status', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('/admin/reports/getDebts', false)
            ->getContent();

        $this->assertTrue(
            str_contains($html, '/admin/reports/debts/total')
            || str_contains($html, 'admin\/reports\/debts\/total'),
            'Страница должна дергать /admin/reports/debts/total для обновления суммы'
        );
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="active"[^>]*selected/',
            $html,
            'По умолчанию в фильтре активности должен быть selected=active'
        );
        $this->assertStringContainsString('refreshDebtReportTotal', $html);
        $this->assertStringContainsString('debtReportFilterParams', $html);
        $this->assertStringContainsString("defaultFilterUserStatus = 'active'", $html);
    }

    public function test_monthly_setting_prices_page_includes_manual_paid_modal(): void
    {
        $this->grantPermissionForUser($this->user, 'setPrices.view');
        $this->actingAs($this->user->fresh());

        $this->withoutVite();
        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('id="manualUserPricePaidModal"', false)
            ->assertSee('id="manualUserPricePaidComment"', false)
            ->assertSee('id="manualUserPricePaidConfirmBtn"', false);
    }

    public function test_debts_team_filter_ignores_manually_paid_sibling_month(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа А долг']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа Б долг']);
        $student = $this->makeStudentWithTeams([$teamA, $teamB]);

        $this->insertUserPrice($student, [
            'is_paid' => 0,
            'is_manual_paid' => 1,
            'price' => 1000,
            'new_month' => '2026-01-01',
        ], $teamA);

        $this->insertUserPrice($student, [
            'is_paid' => 0,
            'is_manual_paid' => null,
            'price' => 400,
            'new_month' => '2026-01-01',
        ], $teamB);

        $this->get(route('reports.debts.total', ['filter_team_id' => $teamA->id]))
            ->assertOk()
            ->assertJson(['total_raw' => 0.0]);

        $this->get(route('reports.debts.total', ['filter_team_id' => $teamB->id]))
            ->assertOk()
            ->assertJson(['total_raw' => 400.0]);
    }

    public function test_foreign_partner_manual_paid_month_does_not_leak_into_debts(): void
    {
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $this->insertUserPrice($this->foreignUser, [
            'is_paid' => 0,
            'is_manual_paid' => null,
            'price' => 7777,
            'new_month' => '2026-01-01',
        ], $foreignTeam);

        $this->assertDebtTotal(0.0);
        $this->assertDebtPrices([]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function debtsEndpointsPayload(): array
    {
        return [
            ['method' => 'GET', 'url' => route('debts')],
            ['method' => 'GET', 'url' => route('reports.debts.total')],
            [
                'method' => 'GET',
                'url' => route('debts.getDebts', ['draw' => 1]),
                'headers' => [
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                    'HTTP_ACCEPT' => 'application/json',
                ],
            ],
            ['method' => 'GET', 'url' => route('reports.debts.columns-settings.get')],
            [
                'method' => 'POST',
                'url' => route('reports.debts.columns-settings.save'),
                'data' => ['columns' => ['user_name' => true]],
                'headers' => [
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                    'HTTP_ACCEPT' => 'application/json',
                ],
            ],
        ];
    }

    private function assertDebtTotal(float $expectedRaw): void
    {
        $this->get(route('reports.debts.total'))
            ->assertOk()
            ->assertJson(['total_raw' => $expectedRaw]);
    }

    /**
     * @param  list<float>  $expectedPrices
     */
    private function assertDebtPrices(array $expectedPrices): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', ['draw' => 1, 'start' => 0, 'length' => 50]));

        $response->assertOk();

        $prices = collect($response->json('data'))
            ->pluck('price')
            ->map(fn ($p) => (float) $p)
            ->sort()
            ->values()
            ->all();

        $expected = collect($expectedPrices)->sort()->values()->all();
        $this->assertSame($expected, $prices);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }
}
