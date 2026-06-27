<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтр «Активность ученика» (status) в отчётах:
 * Платежи, Платежи по месяцам, LTV, Задолженности.
 *
 * Покрывает логику фильтрации и контроль доступа (guest / без reports.view / с правом).
 */
final class ReportsUserStatusFilterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Контроль доступа
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_user_status_report_endpoints(): void
    {
        Auth::logout();

        foreach ($this->allReportEndpointCalls() as $item) {
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

    public function test_user_without_reports_view_gets_403_on_user_status_report_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->allReportEndpointCalls() as $item) {
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

    public function test_user_with_reports_view_all_user_status_report_endpoints_return_200(): void
    {
        $this->asAdmin();
        $this->seedPaymentReportFixtures();

        $this->assertAllReportEndpointsReturn200();
    }

    public function test_superadmin_all_user_status_report_endpoints_return_200(): void
    {
        $this->asSuperadmin();
        $this->seedPaymentReportFixtures();

        $this->assertAllReportEndpointsReturn200();
    }

    public function test_all_user_status_filter_param_variants_return_200_for_each_report(): void
    {
        $this->asAdmin();
        [$activeStudent] = $this->seedPaymentReportFixtures();
        $this->seedDebtFixtures($activeStudent);

        foreach ($this->userStatusFilterVariants() as $label => $statusParams) {
            $this->assertPaymentsEndpointsOkWithStatus($statusParams, $label);
            $this->assertMonthlyEndpointsOkWithStatus($statusParams, $label);
            $this->assertLtvEndpointsOkWithStatus($statusParams, $label, $activeStudent);
            $this->assertDebtsEndpointsOkWithStatus($statusParams, $label);
        }
    }

    public function test_guest_and_unauthorized_cannot_access_pages_with_user_status_query_params(): void
    {
        $params = ['status' => 'inactive'];

        Auth::logout();
        $this->get(route('payments', $params))->assertRedirect();
        $this->get(route('reports.payments.monthly', $params))->assertRedirect();
        $this->get(route('reports.ltv', $params))->assertRedirect();
        $this->get(route('debts', $params))->assertRedirect();

        $denied = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('payments', $params))->assertForbidden();
        $this->get(route('reports.payments.monthly', $params))->assertForbidden();
        $this->get(route('reports.ltv', $params))->assertForbidden();
        $this->get(route('debts', $params))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // UI: фильтр на страницах
    // -------------------------------------------------------------------------

    public function test_all_report_pages_render_user_status_filter_with_active_selected_by_default(): void
    {
        $this->asAdmin();

        $pages = [
            ['route' => route('payments'), 'filterId' => 'pay-filter-user-status'],
            ['route' => route('reports.payments.monthly'), 'filterId' => 'pay-monthly-filter-user-status'],
            ['route' => route('reports.ltv'), 'filterId' => 'pay-ltv-filter-user-status'],
            ['route' => route('debts'), 'filterId' => 'pay-debt-filter-user-status'],
        ];

        foreach ($pages as $page) {
            $html = $this->get($page['route'])->assertOk()->getContent();

            $this->assertStringContainsString($page['filterId'], $html);
            $this->assertStringContainsString('Активность ученика', $html);
            $this->assertStringContainsString(
                '<option value="active" selected',
                $html,
                "Страница {$page['route']}: по умолчанию выбран «Только активные»"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Платежи (/admin/reports/payments)
    // -------------------------------------------------------------------------

    public function test_payments_report_user_status_filters_total_and_data_table(): void
    {
        $this->asAdmin();
        [$activeStudent, $inactiveStudent] = $this->seedActiveInactivePaymentStudents();

        $totalResponse = $this->getJson(route('reports.payments.total'))->assertOk();
        $this->assertEquals(1000.0, (float) ($totalResponse->json('sum_payments_raw') ?? 0));

        $activeRows = $this->paymentsDataRows(['status' => 'active']);
        $this->assertContains($activeStudent->id, $activeRows);
        $this->assertNotContains($inactiveStudent->id, $activeRows);

        $inactiveRows = $this->paymentsDataRows(['status' => 'inactive']);
        $this->assertNotContains($activeStudent->id, $inactiveRows);
        $this->assertContains($inactiveStudent->id, $inactiveRows);

        $inactiveTotalResponse = $this->getJson(route('reports.payments.total', ['status' => 'inactive']))->assertOk();
        $this->assertEquals(2000.0, (float) ($inactiveTotalResponse->json('sum_payments_raw') ?? 0));

        $allRows = $this->paymentsDataRows(['status' => '']);
        $this->assertContains($activeStudent->id, $allRows);
        $this->assertContains($inactiveStudent->id, $allRows);

        $allTotalResponse = $this->getJson(route('reports.payments.total', ['status' => '']))->assertOk();
        $this->assertEquals(3000.0, (float) ($allTotalResponse->json('sum_payments_raw') ?? 0));
    }

    // -------------------------------------------------------------------------
    // Платежи по месяцам
    // -------------------------------------------------------------------------

    public function test_monthly_report_user_status_filters_total_data_and_month_detail(): void
    {
        $this->asAdmin();
        [$activeStudent, $inactiveStudent] = $this->seedActiveInactivePaymentStudents('2025-09-01', '2025-09-15 10:00:00');

        $defaultTotalResponse = $this->get(route('reports.payments.monthly.total'))->assertOk();
        $this->assertEquals(1000.0, (float) ($defaultTotalResponse->json('total_raw') ?? 0));

        $activeSum = collect($this->monthlyDataRows(['status' => 'active']))->sum(fn ($row) => (float) $row['total_sum']);
        $this->assertEquals(1000.0, $activeSum);

        $inactiveTotalResponse = $this->get(route('reports.payments.monthly.total', ['status' => 'inactive']))->assertOk();
        $this->assertEquals(2000.0, (float) ($inactiveTotalResponse->json('total_raw') ?? 0));

        $inactiveSum = collect($this->monthlyDataRows(['status' => 'inactive']))->sum(fn ($row) => (float) $row['total_sum']);
        $this->assertEquals(2000.0, $inactiveSum);

        $allTotalResponse = $this->get(route('reports.payments.monthly.total', ['status' => '']))->assertOk();
        $this->assertEquals(3000.0, (float) ($allTotalResponse->json('total_raw') ?? 0));

        $activeDetail = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-09',
                'mode' => 'subscription',
                'draw' => 1,
                'status' => 'active',
            ]))
            ->assertOk()
            ->json();

        $this->assertEquals(1000.0, (float) ($activeDetail['meta_sum_total'] ?? 0));
        $this->assertSame(1, (int) ($activeDetail['meta_payments_count'] ?? 0));

        $inactiveDetail = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-09',
                'mode' => 'subscription',
                'draw' => 1,
                'status' => 'inactive',
            ]))
            ->assertOk()
            ->json();

        $this->assertEquals(2000.0, (float) ($inactiveDetail['meta_sum_total'] ?? 0));
        $this->assertSame(1, (int) ($inactiveDetail['meta_payments_count'] ?? 0));
    }

    // -------------------------------------------------------------------------
    // LTV
    // -------------------------------------------------------------------------

    public function test_ltv_report_user_status_filters_total_data_and_user_payments_detail(): void
    {
        $this->asAdmin();
        [$activeStudent, $inactiveStudent] = $this->seedActiveInactivePaymentStudents();

        $defaultTotalResponse = $this->get(route('reports.ltv.total'))->assertOk();
        $this->assertEquals(1000.0, (float) ($defaultTotalResponse->json('total_raw') ?? 0));

        $activeUserIds = collect($this->ltvDataRows())->pluck('user_id')->all();
        $this->assertContains($activeStudent->id, $activeUserIds);
        $this->assertNotContains($inactiveStudent->id, $activeUserIds);

        $inactiveUserIds = collect($this->ltvDataRows(['status' => 'inactive']))->pluck('user_id')->all();
        $this->assertNotContains($activeStudent->id, $inactiveUserIds);
        $this->assertContains($inactiveStudent->id, $inactiveUserIds);

        $allTotalResponse = $this->get(route('reports.ltv.total', ['status' => '']))->assertOk();
        $this->assertEquals(3000.0, (float) ($allTotalResponse->json('total_raw') ?? 0));

        $activeDetail = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $activeStudent->id,
                'draw' => 1,
                'status' => 'active',
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1000.0, (float) ($activeDetail['meta_sum_total'] ?? 0));

        $inactiveDetailBlocked = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $inactiveStudent->id,
                'draw' => 1,
                'status' => 'active',
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(0.0, (float) ($inactiveDetailBlocked['meta_sum_total'] ?? 0));
        $this->assertSame(0, (int) ($inactiveDetailBlocked['meta_payments_count'] ?? 0));

        $inactiveDetail = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $inactiveStudent->id,
                'draw' => 1,
                'status' => 'inactive',
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(2000.0, (float) ($inactiveDetail['meta_sum_total'] ?? 0));
    }

    // -------------------------------------------------------------------------
    // Задолженности
    // -------------------------------------------------------------------------

    public function test_debts_report_user_status_filters_total_and_data_table(): void
    {
        Carbon::setTestNow('2026-02-15');
        $this->asAdmin();

        $activeStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $inactiveStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 0,
        ]);

        $this->insertUserPrice($activeStudent, [
            'is_paid' => 0,
            'price' => 1000,
            'new_month' => '2026-01-01',
        ]);
        $this->insertUserPrice($inactiveStudent, [
            'is_paid' => 0,
            'price' => 2000,
            'new_month' => '2026-01-01',
        ]);

        $defaultTotalResponse = $this->get(route('reports.debts.total'))->assertOk();
        $this->assertEquals(1000.0, (float) ($defaultTotalResponse->json('total_raw') ?? 0));

        $defaultUserIds = collect($this->debtsDataRows())->pluck('user_id')->unique()->all();
        $this->assertContains($activeStudent->id, $defaultUserIds);
        $this->assertNotContains($inactiveStudent->id, $defaultUserIds);

        $inactiveUserIds = collect($this->debtsDataRows(['status' => 'inactive']))->pluck('user_id')->unique()->all();
        $this->assertNotContains($activeStudent->id, $inactiveUserIds);
        $this->assertContains($inactiveStudent->id, $inactiveUserIds);

        $inactiveTotalResponse = $this->get(route('reports.debts.total', ['status' => 'inactive']))->assertOk();
        $this->assertEquals(2000.0, (float) ($inactiveTotalResponse->json('total_raw') ?? 0));

        $allUserIds = collect($this->debtsDataRows(['status' => '']))->pluck('user_id')->unique()->all();
        $this->assertContains($activeStudent->id, $allUserIds);
        $this->assertContains($inactiveStudent->id, $allUserIds);

        $allTotalResponse = $this->get(route('reports.debts.total', ['status' => '']))->assertOk();
        $this->assertEquals(3000.0, (float) ($allTotalResponse->json('total_raw') ?? 0));
    }

    public function test_debts_page_with_explicit_status_active_matches_default_total(): void
    {
        Carbon::setTestNow('2026-02-15');
        $this->asAdmin();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->insertUserPrice($student, [
            'is_paid' => 0,
            'price' => 750,
            'new_month' => '2026-01-01',
        ]);

        $defaultTotal = (float) $this->get(route('reports.debts.total'))->json('total_raw');
        $explicitActive = (float) $this->get(route('reports.debts.total', ['status' => 'active']))->json('total_raw');

        $this->assertSame($defaultTotal, $explicitActive);
        $this->assertSame(750.0, $defaultTotal);
    }

    // -------------------------------------------------------------------------
    // Helpers: доступ и smoke 200
    // -------------------------------------------------------------------------

    private function assertAllReportEndpointsReturn200(): void
    {
        foreach ($this->userStatusFilterVariants() as $label => $statusParams) {
            $this->assertPaymentsEndpointsOkWithStatus($statusParams, $label);
            $this->assertMonthlyEndpointsOkWithStatus($statusParams, $label);
            $this->assertLtvEndpointsOkWithStatus($statusParams, $label);
            $this->assertDebtsEndpointsOkWithStatus($statusParams, $label);
        }
    }

    /**
     * @param  array<string, string>  $statusParams
     */
    private function assertPaymentsEndpointsOkWithStatus(array $statusParams, string $label): void
    {
        $this->get(route('payments', $statusParams))
            ->assertOk()
            ->assertViewHas('activeTab', 'payment');

        $this->getJson(route('reports.payments.total', $statusParams))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('payments.getPayments', array_merge($this->dataTablesBaseParams(), $statusParams)))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    /**
     * @param  array<string, string>  $statusParams
     */
    private function assertMonthlyEndpointsOkWithStatus(array $statusParams, string $label, ?User $student = null): void
    {
        $this->get(route('reports.payments.monthly', $statusParams))
            ->assertOk()
            ->assertViewHas('activeTab', 'payment-monthly');

        $this->get(route('reports.payments.monthly.total', $statusParams))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', array_merge(['draw' => 1, 'start' => 0, 'length' => 10], $statusParams)))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $detailParams = array_merge([
            'yearMonth' => '2025-09',
            'mode' => 'subscription',
            'draw' => 1,
        ], $statusParams);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', $detailParams))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data', 'meta_payments_count', 'meta_sum_total']);
    }

    /**
     * @param  array<string, string>  $statusParams
     */
    private function assertLtvEndpointsOkWithStatus(array $statusParams, string $label, ?User $student = null): void
    {
        $this->get(route('reports.ltv', $statusParams))
            ->assertOk()
            ->assertViewHas('activeTab', 'ltv');

        $this->get(route('reports.ltv.total', $statusParams))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', array_merge(['draw' => 1, 'start' => 0, 'length' => 10], $statusParams)))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        if ($student !== null) {
            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.ltv.user_payments', array_merge([
                    'user' => $student->id,
                    'draw' => 1,
                ], $statusParams)))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data', 'meta_payments_count', 'meta_sum_total']);
        }
    }

    /**
     * @param  array<string, string>  $statusParams
     */
    private function assertDebtsEndpointsOkWithStatus(array $statusParams, string $label): void
    {
        $this->get(route('debts', $statusParams))
            ->assertOk()
            ->assertViewHas('activeTab', 'debt');

        $this->get(route('reports.debts.total', $statusParams))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', array_merge(['draw' => 1, 'start' => 0, 'length' => 10], $statusParams)))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allReportEndpointCalls(): array
    {
        $student = User::factory()->create(['partner_id' => $this->partner->id]);

        return [
            ['method' => 'GET', 'url' => route('payments', ['status' => 'active'])],
            ['method' => 'GET', 'url' => route('reports.payments.total', ['status' => 'active'])],
            [
                'method' => 'GET',
                'url' => route('payments.getPayments'),
                'data' => array_merge($this->dataTablesBaseParams(), ['status' => 'active']),
                'headers' => ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'],
            ],
            ['method' => 'GET', 'url' => route('reports.payments.monthly', ['status' => 'active'])],
            ['method' => 'GET', 'url' => route('reports.payments.monthly.total', ['status' => 'active'])],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly.data', ['draw' => 1, 'status' => 'active']),
                'headers' => ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.payments.monthly.payments', [
                    'yearMonth' => '2025-01',
                    'mode' => 'subscription',
                    'draw' => 1,
                    'status' => 'active',
                ]),
                'headers' => ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'],
            ],
            ['method' => 'GET', 'url' => route('reports.ltv', ['status' => 'active'])],
            ['method' => 'GET', 'url' => route('reports.ltv.total', ['status' => 'active'])],
            [
                'method' => 'GET',
                'url' => route('reports.ltv.data', ['draw' => 1, 'status' => 'active']),
                'headers' => ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.ltv.user_payments', ['user' => $student->id, 'draw' => 1, 'status' => 'active']),
                'headers' => ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'],
            ],
            ['method' => 'GET', 'url' => route('debts', ['status' => 'active'])],
            ['method' => 'GET', 'url' => route('reports.debts.total', ['status' => 'active'])],
            [
                'method' => 'GET',
                'url' => route('debts.getDebts', ['draw' => 1, 'status' => 'active']),
                'headers' => ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function userStatusFilterVariants(): array
    {
        return [
            'implicit_default' => [],
            'default_active' => ['status' => 'active'],
            'inactive' => ['status' => 'inactive'],
            'all_students' => ['status' => ''],
        ];
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function seedActiveInactivePaymentStudents(
        string $paymentMonth = '2025-08-01',
        string $operationDate = '2025-08-10 10:00:00',
    ): array {
        $activeStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $inactiveStudent = User::factory()->disabled()->create([
            'partner_id' => $this->partner->id,
        ]);

        Payment::factory()->forUser($activeStudent)->create([
            'summ' => 1000,
            'payment_month' => $paymentMonth,
            'operation_date' => $operationDate,
        ]);
        Payment::factory()->forUser($inactiveStudent)->create([
            'summ' => 2000,
            'payment_month' => $paymentMonth,
            'operation_date' => $operationDate,
        ]);

        return [$activeStudent, $inactiveStudent];
    }

    /**
     * @return array{0: User}
     */
    private function seedPaymentReportFixtures(): array
    {
        return $this->seedActiveInactivePaymentStudents('2025-09-01', '2025-09-10 10:00:00');
    }

    private function seedDebtFixtures(User $activeStudent): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->insertUserPrice($activeStudent, [
            'is_paid' => 0,
            'price' => 400,
            'new_month' => '2026-01-01',
        ]);
    }

    /**
     * @param  array<string, string>  $extra
     * @return list<int>
     */
    private function paymentsDataRows(array $extra = []): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('payments.getPayments', array_merge($this->dataTablesBaseParams(), $extra)))
            ->assertOk()
            ->json();

        return collect($json['data'] ?? [])->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<string, string>  $extra
     * @return list<array<string, mixed>>
     */
    private function monthlyDataRows(array $extra = []): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', array_merge(['draw' => 1], $extra)))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    /**
     * @param  array<string, string>  $extra
     * @return list<array<string, mixed>>
     */
    private function ltvDataRows(array $extra = []): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', array_merge(['draw' => 1], $extra)))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    /**
     * @param  array<string, string>  $extra
     * @return list<array<string, mixed>>
     */
    private function debtsDataRows(array $extra = []): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', array_merge(['draw' => 1], $extra)))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataTablesBaseParams(int $length = 50): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => $length,
            'columns' => [
                ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'user_name', 'name' => 'user_name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'team_title', 'name' => 'team_title', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'summ', 'name' => 'summ', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'payment_month', 'name' => 'payment_month', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'operation_date', 'name' => 'operation_date', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'payment_provider', 'name' => 'payment_provider', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'payment_method_label', 'name' => 'payment_method_label', 'searchable' => 'false', 'orderable' => 'false'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }
}
