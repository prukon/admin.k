<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\UserTableSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX / AJAX контракты колонки «Статус выплаты».
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TbankPaymentsPayoutStatusNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asSuperadmin();
    }

    public function test_columns_settings_non_ajax_post_saves_payout_status_and_returns_json_not_empty_200(): void
    {
        $payload = [
            'columns' => [
                'payout_status' => true,
                'payout_amount' => true,
                'deal_id' => false,
            ],
        ];

        $response = $this->post('/admin/reports/tbank-payments/columns-settings', $payload, [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);
    }

    public function test_columns_settings_non_ajax_validation_failure_redirects_back_with_field_error(): void
    {
        $this->from(route('reports.tbank-payments.index'))
            ->post('/admin/reports/tbank-payments/columns-settings', [], [
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);
    }

    public function test_columns_settings_ajax_validation_failure_returns_422_json(): void
    {
        $this->postJson('/admin/reports/tbank-payments/columns-settings', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_columns_settings_ajax_persists_hidden_payout_status(): void
    {
        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => [
                'payout_status' => false,
                'amount' => true,
            ],
            'page_length' => 50,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonStructure(['success'])
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments')
            ->firstOrFail();

        $this->assertSame(50, $setting->page_length);
        $this->assertSame(false, $setting->columns['payout_status'] ?? null);
    }

    public function test_get_columns_settings_as_web_request_is_json_not_empty_200(): void
    {
        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => ['payout_status' => false],
        ])->assertOk();

        $response = $this->get('/admin/reports/tbank-payments/columns-settings', [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertSame(false, $payload['payout_status'] ?? null);
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    public function test_datatable_without_ajax_header_still_returns_payout_status_json_not_empty_html(): void
    {
        $payment = TinkoffPayment::query()->create([
            'order_id' => 'order-safety-'.uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 150000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
        TinkoffPayout::query()->create([
            'payment_id' => $payment->id,
            'partner_id' => $payment->partner_id,
            'deal_id' => 'deal-'.$payment->id,
            'amount' => 140000,
            'is_final' => true,
            'status' => 'COMPLETED',
            'source' => 'auto',
            'completed_at' => Carbon::parse('2026-09-10 14:35:00'),
        ]);

        $response = $this->get(route('reports.tbank-payments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $row = collect($json['data'])->firstWhere('id', $payment->id);
        $this->assertIsArray($row);
        $this->assertSame('COMPLETED', $row['payout_status']);
        $this->assertSame('10.09.2026 14:35', $row['payout_status_at']);
        $this->assertArrayNotHasKey('payout_status_at_raw', $row);
    }

    public function test_non_ajax_days_data_does_not_include_payout_status(): void
    {
        TinkoffPayment::query()->create([
            'order_id' => 'order-days-'.uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 11000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);

        $response = $this->get(route('reports.tbank-payments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'view' => 'days',
        ]), [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $row = $response->json('data.0');
        if (is_array($row)) {
            $this->assertArrayNotHasKey('payout_status', $row);
            $this->assertArrayNotHasKey('payout_status_at', $row);
        }
    }

    public function test_guest_html_redirects_to_login_json_is_unauthorized(): void
    {
        Auth::logout();

        $this->get(route('reports.tbank-payments.index'), ['HTTP_ACCEPT' => 'text/html'])
            ->assertRedirect();

        $this->getJson(route('reports.tbank-payments.data', ['draw' => 1]))
            ->assertStatus(401);

        $this->getJson(route('reports.tbank-payments.total'))
            ->assertStatus(401);

        $this->getJson('/admin/reports/tbank-payments/columns-settings')
            ->assertStatus(401);
    }

    public function test_unsupported_methods_do_not_return_500_or_create_columns_row(): void
    {
        $urls = [
            route('reports.tbank-payments.index'),
            route('reports.tbank-payments.total'),
            route('reports.tbank-payments.data'),
            '/admin/reports/tbank-payments/columns-settings',
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, [
                    'columns' => ['payout_status' => false],
                    'page_length' => 50,
                ]);
                $this->assertNotSame(500, $response->getStatusCode(), "$method $url");
                $this->assertContains(
                    $response->getStatusCode(),
                    [404, 405],
                    "$method $url → {$response->getStatusCode()}"
                );
            }
        }

        $this->assertSame(
            0,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'reports_tbank_payments')
                ->count()
        );
    }
}
