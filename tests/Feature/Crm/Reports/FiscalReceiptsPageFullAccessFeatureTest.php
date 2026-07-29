<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\FiscalReceipt;
use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Отчёты → Чеки» (/admin/reports/fiscal-receipts):
 * контроль доступа (guest / без права / с правом / superadmin) и smoke 200 для endpoints.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class FiscalReceiptsPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'amount'     => 1500,
            'type'       => 'income',
            'status'     => 'processed',
        ]);
    }

    public function test_guest_cannot_access_any_fiscal_receipts_endpoint(): void
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

    public function test_user_without_reports_fiscal_receipts_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('reports.fiscal.receipts.view', $this->partner);
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
                "Без reports.fiscal.receipts.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_reports_fiscal_receipts_view_all_section_endpoints_return_expected_status(): void
    {
        $actor = $this->createUserWithoutPermission('reports.fiscal.receipts.view', $this->partner);
        $this->grantFiscalReceiptsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser(isSuperadmin: false);
    }

    public function test_superadmin_all_section_endpoints_return_expected_status(): void
    {
        $this->asSuperadmin();

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser(isSuperadmin: true);
    }

    public function test_authorized_user_filter_param_variants_return_200(): void
    {
        $this->asSuperadmin();

        foreach ($this->allFilterParamVariants() as $params) {
            $this->get(route('reports.fiscal-receipts.index', $params))
                ->assertOk()
                ->assertViewHas('activeTab', 'fiscal-receipts');

            $this->get(route('reports.fiscal-receipts.total', $params))->assertOk();

            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.fiscal-receipts.data', array_merge($this->baseDataTableParams(), $params)))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_columns_settings_ajax_and_non_ajax_contracts(): void
    {
        $this->asSuperadmin();

        $payload = ['columns' => ['partner' => true, 'error' => false]];

        $this->postJson('/admin/reports/fiscal-receipts/columns-settings', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->post('/admin/reports/fiscal-receipts/columns-settings', $payload, [
            'HTTP_ACCEPT' => 'text/html',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->from(route('reports.fiscal-receipts.index'))
            ->post('/admin/reports/fiscal-receipts/columns-settings', [], [
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);

        $this->postJson('/admin/reports/fiscal-receipts/columns-settings', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(bool $isSuperadmin): void
    {
        $index = $this->get(route('reports.fiscal-receipts.index'))
            ->assertOk()
            ->assertViewIs('admin.report.index')
            ->assertViewHas('activeTab', 'fiscal-receipts')
            ->assertViewHas('frCanFilterPartner', $isSuperadmin);

        if ($isSuperadmin) {
            $index->assertSee('fr-filter-partner', false);
        } else {
            $index->assertDontSee('fr-filter-partner', false);
        }

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.fiscal-receipts.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.fiscal-receipts.total'))->assertOk();

        $this->get('/admin/reports/fiscal-receipts/columns-settings')->assertOk();

        $this->postJson('/admin/reports/fiscal-receipts/columns-settings', [
            'columns' => ['partner' => true, 'external_id' => false],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get(route('reports.fiscal-receipts.partners.search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $partner = Partner::factory()->create(['title' => 'Fiscal Smoke Partner']);
        $this->get(route('reports.fiscal-receipts.partners.search', ['q' => 'Fiscal Smoke']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $indexParams = [
            'type'   => 'income',
            'status' => 'processed',
        ];
        if ($isSuperadmin) {
            $indexParams['partner_id'] = $partner->id;
        }

        $this->get(route('reports.fiscal-receipts.index', $indexParams))->assertOk();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allSectionRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('reports.fiscal-receipts.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.fiscal-receipts.data', $this->baseDataTableParams()),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url'    => route('reports.fiscal-receipts.total'),
            ],
            [
                'method' => 'GET',
                'url'    => route('reports.fiscal-receipts.partners.search', ['q' => 'x']),
            ],
            [
                'method' => 'GET',
                'url'    => '/admin/reports/fiscal-receipts/columns-settings',
            ],
            [
                'method'  => 'POST',
                'url'     => '/admin/reports/fiscal-receipts/columns-settings',
                'data'    => ['columns' => ['partner' => true]],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.fiscal-receipts.index', [
                    'type'   => 'income',
                    'status' => 'error',
                ]),
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
            ['partner_id' => $this->partner->id],
            ['type' => 'income'],
            ['status' => 'processed'],
            ['created_from' => now()->subMonth()->toDateString()],
            ['created_to' => now()->toDateString()],
            [
                'partner_id' => $this->partner->id,
                'type'       => 'income',
                'status'     => 'processed',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDataTableParams(): array
    {
        return [
            'draw'   => 1,
            'start'  => 0,
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

    private function grantFiscalReceiptsView(int $roleId): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id'    => $this->partner->id,
                'role_id'       => $roleId,
                'permission_id' => $this->permissionId('reports.fiscal.receipts.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
