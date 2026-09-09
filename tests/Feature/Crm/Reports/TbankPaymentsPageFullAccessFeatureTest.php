<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Partner;
use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Отчёты → Платежи T‑Bank» (/admin/reports/tbank-payments).
 */
final class TbankPaymentsPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-full-access-'.uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 150000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
    }

    public function test_guest_cannot_access_any_tbank_payments_report_endpoint(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutesPayload() as $item) {
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

    public function test_user_without_reports_tbank_payments_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->allSectionRoutesPayload() as $item) {
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
                "Без reports.tbank.payments.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_reports_tbank_payments_view_all_section_endpoints_return_expected_status(): void
    {
        $actor = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->grantTbankPaymentsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser(isSuperadmin: false);
    }

    public function test_superadmin_all_section_endpoints_return_expected_status(): void
    {
        $this->asSuperadmin();

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser(isSuperadmin: true);
    }

    public function test_columns_settings_ajax_and_non_ajax_contracts(): void
    {
        $this->asSuperadmin();

        $payload = ['columns' => ['partner' => true, 'deal_id' => false]];

        $this->postJson('/admin/reports/tbank-payments/columns-settings', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->post('/admin/reports/tbank-payments/columns-settings', $payload, [
            'HTTP_ACCEPT' => 'text/html',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->from(route('reports.tbank-payments.index'))
            ->post('/admin/reports/tbank-payments/columns-settings', [], [
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_authorized_user_filter_param_variants_return_200(): void
    {
        $this->asSuperadmin();

        foreach ($this->allFilterParamVariants() as $params) {
            $this->get(route('reports.tbank-payments.index', $params))
                ->assertOk()
                ->assertViewHas('activeTab', 'tbank-payments');

            $this->get(route('reports.tbank-payments.total', $params))
                ->assertOk()
                ->assertJsonStructure(['total_formatted', 'total_raw']);

            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), $params)))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_unsupported_methods_on_section_do_not_return_500_or_empty_200(): void
    {
        $this->asSuperadmin();

        $urls = [
            route('reports.tbank-payments.index'),
            route('reports.tbank-payments.total'),
            '/admin/reports/tbank-payments/columns-settings',
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, ['page_length' => 50]);
                $this->assertNotSame(500, $response->getStatusCode(), "$method $url");
                $this->assertContains($response->getStatusCode(), [404, 405], "$method $url");
            }
        }
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(bool $isSuperadmin): void
    {
        $index = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewIs('admin.report.index')
            ->assertViewHas('activeTab', 'tbank-payments')
            ->assertViewHas('tpCanFilterPartner', $isSuperadmin)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="tbank-payments-table"', false)
            ->assertSee('Комиссия платформы', false)
            ->assertSee('data-column-key="platform_commission"', false)
            ->assertSee('data-column-key="receipt"', false)
            ->assertSee('<th>Чек</th>', false);

        if ($isSuperadmin) {
            $index->assertSee('tp-filter-partner', false);
            $index->assertSee('id="tp-toolbar-commissions"', false);
        } else {
            $index->assertDontSee('tp-filter-partner', false);
            $index->assertDontSee('id="tp-toolbar-commissions"', false);
        }

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.tbank-payments.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.tbank-payments.total'))->assertOk();

        $this->get('/admin/reports/tbank-payments/columns-settings')->assertOk();

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => ['partner' => true, 'order_id' => false],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get(route('reports.tbank-payments.partners.search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $partner = Partner::factory()->create(['title' => 'Tbank Smoke Partner']);
        $this->get(route('reports.tbank-payments.partners.search', ['q' => 'Tbank Smoke']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $indexParams = ['status' => 'CONFIRMED'];
        if ($isSuperadmin) {
            $indexParams['partner_id'] = $partner->id;
        }

        $this->get(route('reports.tbank-payments.index', $indexParams))->assertOk();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allSectionRoutesPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.data', $this->baseDataTableParams()),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.total'),
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.partners.search', ['q' => 'x']),
            ],
            [
                'method' => 'GET',
                'url' => '/admin/reports/tbank-payments/columns-settings',
            ],
            [
                'method' => 'POST',
                'url' => '/admin/reports/tbank-payments/columns-settings',
                'data' => ['columns' => ['partner' => true]],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.index', ['status' => 'CONFIRMED']),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allFilterParamVariants(): array
    {
        return [
            [],
            ['status' => 'all'],
            ['status' => 'CONFIRMED'],
            ['method' => 'all'],
            ['method' => 'card'],
            ['method' => 'sbp'],
            ['method' => 'tpay'],
            ['partner_id' => $this->partner->id],
            ['created_from' => now()->subMonth()->toDateString()],
            ['created_to' => now()->toDateString()],
            [
                'status' => 'CONFIRMED',
                'method' => 'card',
                'partner_id' => $this->partner->id,
                'created_from' => now()->subMonth()->toDateString(),
                'created_to' => now()->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDataTableParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function grantTbankPaymentsView(int $roleId): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId('reports.tbank.payments.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
