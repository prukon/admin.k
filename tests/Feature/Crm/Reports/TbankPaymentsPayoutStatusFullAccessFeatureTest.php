<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * HTTP-матрица колонки «Статус выплаты» на вкладке «Платежи T‑Bank».
 */
final class TbankPaymentsPayoutStatusFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_access_payout_status_endpoints(): void
    {
        Auth::logout();

        foreach ($this->sectionRoutes() as $item) {
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

    public function test_user_without_report_permission_gets_403_on_payout_status_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->sectionRoutes() as $item) {
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

    public function test_viewer_with_report_permission_gets_payout_status_without_extra_rights(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);

        $this->assertFalse($actor->can('tbank.payouts.manage'));
        $this->assertFalse($actor->can('reports.additional.value.view'));

        $this->assertAuthorizedPayoutStatusEndpoints(isSuperadmin: false);
    }

    public function test_superadmin_payout_status_endpoints_return_expected_status(): void
    {
        $this->asSuperadmin();

        $this->assertAuthorizedPayoutStatusEndpoints(isSuperadmin: true);
    }

    public function test_authorized_filter_variants_keep_payout_status_in_payments_json(): void
    {
        $this->asSuperadmin();

        $payment = $this->makePayment(['method' => 'sbp', 'status' => 'CONFIRMED', 'amount' => 18000]);
        $this->makePayout($payment, [
            'status' => 'REJECTED',
        ]);
        $payoutId = (int) TinkoffPayout::query()->where('payment_id', $payment->id)->value('id');
        TinkoffPayout::query()->whereKey($payoutId)->update([
            'updated_at' => Carbon::parse('2026-08-03 14:35:00'),
        ]);

        foreach ($this->paymentsFilterVariants() as $params) {
            $index = $this->get(route('reports.tbank-payments.index', $params));
            $this->assertSame(200, $index->getStatusCode(), 'index '.json_encode($params));
            $this->assertNotSame('', trim((string) $index->getContent()));
            $index->assertSee('<th>Статус выплаты</th>', false);

            $total = $this->get(route('reports.tbank-payments.total', $params));
            $this->assertSame(200, $total->getStatusCode(), 'total '.json_encode($params));
            $total->assertJsonStructure(['total_formatted', 'total_raw']);
            $this->assertArrayNotHasKey('payout_status', $total->json());

            $data = $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), $params)));
            $this->assertSame(200, $data->getStatusCode(), 'data '.json_encode($params));
            $this->assertNotSame('', trim((string) $data->getContent()));
            $row = collect($data->json('data'))->firstWhere('id', $payment->id);
            $this->assertIsArray($row, 'data row '.json_encode($params));
            $this->assertSame('REJECTED', $row['payout_status']);
            $this->assertSame('03.08.2026 14:35', $row['payout_status_at']);
            $this->assertArrayNotHasKey('payout_status_at_raw', $row);
        }
    }

    public function test_days_and_months_authorized_data_do_not_include_payout_status(): void
    {
        $this->asSuperadmin();

        $payment = $this->makePayment(['amount' => 21000]);
        $payment->forceFill(['created_at' => Carbon::parse('2026-09-10 12:00:00')])->save();
        $this->makePayout($payment, ['status' => 'COMPLETED', 'completed_at' => Carbon::parse('2026-09-10 13:00:00')]);

        foreach (['days', 'months'] as $view) {
            $index = $this->get(route('reports.tbank-payments.index', ['view' => $view]))->assertOk();
            $this->assertNotSame('', trim((string) $index->getContent()));
            preg_match('/id="tbank-payments-table"[\s\S]*?<thead>([\s\S]*?)<\/thead>/', (string) $index->getContent(), $thead);
            $this->assertStringNotContainsString('<th>Статус выплаты</th>', $thead[1] ?? '');

            $row = $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), ['view' => $view])))
                ->assertOk()
                ->json('data.0');

            if (is_array($row)) {
                $this->assertArrayNotHasKey('payout_status', $row);
                $this->assertArrayNotHasKey('payout_status_at', $row);
            }
        }
    }

    public function test_user_without_organization_is_logged_out_from_payout_status_page(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $response = $this->from(route('login'))
            ->get(route('reports.tbank-payments.index'));

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Ваша организация недоступна.',
        ]);
    }

    public function test_unsupported_methods_do_not_return_500_or_empty_200(): void
    {
        $this->asSuperadmin();

        $urls = [
            route('reports.tbank-payments.index'),
            route('reports.tbank-payments.total'),
            route('reports.tbank-payments.data', $this->baseDataTableParams()),
            '/admin/reports/tbank-payments/columns-settings',
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, [
                    'columns' => ['payout_status' => true],
                    'page_length' => 50,
                ]);
                $this->assertNotSame(500, $response->getStatusCode(), "$method $url");
                $this->assertContains($response->getStatusCode(), [404, 405], "$method $url");
            }
        }
    }

    private function assertAuthorizedPayoutStatusEndpoints(bool $isSuperadmin): void
    {
        $payment = $this->makePayment(['amount' => 15000]);
        $this->makePayout($payment, [
            'status' => 'COMPLETED',
            'completed_at' => Carbon::parse('2026-09-10 14:35:00'),
        ]);

        $index = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewIs('admin.report.index')
            ->assertSee('id="tpColPayoutStatus"', false)
            ->assertSee('<th>Статус выплаты</th>', false)
            ->assertSee("key: 'payout_status'", false);

        $this->assertNotSame('', trim((string) $index->getContent()));
        if (! $isSuperadmin) {
            $index->assertDontSee('id="tp-toolbar-commissions"', false);
        }

        $data = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.tbank-payments.data', $this->baseDataTableParams()))
            ->assertOk();
        $this->assertNotSame('', trim((string) $data->getContent()));
        $row = collect($data->json('data'))->firstWhere('id', $payment->id);
        $this->assertIsArray($row);
        $this->assertSame('COMPLETED', $row['payout_status']);
        $this->assertSame('10.09.2026 14:35', $row['payout_status_at']);

        $this->get(route('reports.tbank-payments.total'))
            ->assertOk()
            ->assertJsonStructure(['total_formatted', 'total_raw'])
            ->assertJsonMissingPath('payout_status');

        $this->get('/admin/reports/tbank-payments/columns-settings')->assertOk();

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => ['payout_status' => true, 'amount' => true],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sectionRoutes(): array
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
                'url' => '/admin/reports/tbank-payments/columns-settings',
            ],
            [
                'method' => 'POST',
                'url' => '/admin/reports/tbank-payments/columns-settings',
                'data' => ['columns' => ['payout_status' => true]],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.index', ['without_payout' => 1]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), ['without_payout' => 1])),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.index', ['view' => 'days']),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('reports.tbank-payments.data', array_merge($this->baseDataTableParams(), ['view' => 'days'])),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentsFilterVariants(): array
    {
        return [
            [],
            ['status' => 'CONFIRMED'],
            ['method' => 'sbp'],
            ['without_payout' => 1],
            ['status' => 'all', 'method' => 'all'],
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePayment(array $overrides = []): TinkoffPayment
    {
        return TinkoffPayment::query()->create(array_merge([
            'order_id' => 'order-'.uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePayout(TinkoffPayment $payment, array $overrides = []): TinkoffPayout
    {
        return TinkoffPayout::query()->create(array_merge([
            'payment_id' => $payment->id,
            'partner_id' => $payment->partner_id,
            'deal_id' => (string) ($payment->deal_id ?: 'deal-'.$payment->id),
            'amount' => 1000,
            'is_final' => true,
            'status' => 'COMPLETED',
            'source' => 'auto',
        ], $overrides));
    }

    private function grantTbankPaymentsViewToAdmin(): User
    {
        $actor = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => (int) $actor->role_id,
                'permission_id' => $this->permissionId('reports.tbank.payments.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $actor;
    }
}
