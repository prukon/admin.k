<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\TinkoffPayment;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX / AJAX контракты вкладки «Платежи T‑Bank»:
 * columns-settings, валидация фильтров по полям, data без AJAX, PUT/PATCH/DELETE.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TbankPaymentsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asSuperadmin();

        TinkoffPayment::query()->create([
            'order_id' => 'order-safety-'.uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 150000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
    }

    public function test_columns_settings_non_ajax_post_saves_and_returns_json_success_not_empty_200(): void
    {
        $payload = [
            'columns' => [
                'partner' => true,
                'deal_id' => false,
                'amount' => true,
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

    public function test_columns_settings_ajax_returns_json_contract(): void
    {
        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => [
                'order_id' => true,
                'status' => false,
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
        $this->assertSame(false, $setting->columns['status'] ?? null);
    }

    public function test_get_columns_settings_as_web_request_is_json_not_empty_200(): void
    {
        $response = $this->get('/admin/reports/tbank-payments/columns-settings', [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    public function test_invalid_status_on_index_redirects_back_with_status_field_error(): void
    {
        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.index', ['status' => 'NOT_A_STATUS']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['status']);
    }

    public function test_invalid_method_on_index_redirects_back_with_method_field_error(): void
    {
        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.index', ['method' => 'cash']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['method']);
    }

    public function test_invalid_method_ajax_returns_422_json(): void
    {
        $this->getJson(route('reports.tbank-payments.total', ['method' => 'cash']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['method']);

        $this->getJson(route('reports.tbank-payments.data', ['draw' => 1, 'method' => 'cash']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['method']);
    }

    public function test_invalid_dates_return_field_errors_on_ajax_and_non_ajax(): void
    {
        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.total', [
                'created_from' => '2026-09-10',
                'created_to' => '2026-09-01',
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['created_to']);

        $this->getJson(route('reports.tbank-payments.total', [
            'created_from' => '2026-09-10',
            'created_to' => '2026-09-01',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['created_to']);
    }

    public function test_invalid_partner_id_returns_field_error(): void
    {
        $this->getJson(route('reports.tbank-payments.total', ['partner_id' => 'abc']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id']);

        $this->getJson(route('reports.tbank-payments.total', ['partner_id' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id']);
    }

    public function test_invalid_without_payout_on_index_redirects_back_with_field_error(): void
    {
        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.index', ['without_payout' => 'maybe']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['without_payout']);
    }

    public function test_invalid_without_payout_ajax_returns_422_json(): void
    {
        $this->getJson(route('reports.tbank-payments.total', ['without_payout' => 'maybe']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['without_payout']);

        $this->getJson(route('reports.tbank-payments.data', ['draw' => 1, 'without_payout' => 'maybe']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['without_payout'])
            ->assertJsonPath('errors.without_payout.0', 'Поле «Не было выплаты» содержит недопустимое значение.');
    }

    public function test_invalid_without_payout_non_ajax_total_and_data_redirect_with_field_error(): void
    {
        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.total', ['without_payout' => 'on']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['without_payout']);

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.data', ['draw' => 1, 'without_payout' => 'yes']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['without_payout']);
    }

    public function test_non_ajax_data_with_without_payout_returns_json_not_empty_html(): void
    {
        $response = $this->get(route('reports.tbank-payments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'without_payout' => 1,
        ]), [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
    }

    public function test_partners_search_ajax_validation_failure_returns_422_json(): void
    {
        $this->getJson(route('reports.tbank-payments.partners.search', [
            'q' => str_repeat('z', 300),
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_partners_search_ajax_returns_results_contract(): void
    {
        $this->partner->update(['title' => 'Safety Net Partner Tbank']);

        $this->getJson(route('reports.tbank-payments.partners.search', ['q' => 'Safety Net']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonStructure(['results'])
            ->assertJsonFragment([
                'id' => $this->partner->id,
                'text' => 'Safety Net Partner Tbank',
            ]);
    }

    public function test_datatable_without_ajax_header_still_returns_json_not_empty_html(): void
    {
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
        $this->assertArrayHasKey('recordsTotal', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
    }

    public function test_total_json_contract_has_formatted_and_raw(): void
    {
        $this->get(route('reports.tbank-payments.total'))
            ->assertOk()
            ->assertJsonStructure(['total_formatted', 'total_raw']);
    }

    public function test_unsupported_methods_do_not_return_500_or_empty_200(): void
    {
        $urls = [
            route('reports.tbank-payments.index'),
            route('reports.tbank-payments.total'),
            route('reports.tbank-payments.data'),
            '/admin/reports/tbank-payments/columns-settings',
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, ['columns' => ['partner' => true], 'page_length' => 50]);
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

    public function test_guest_html_redirects_to_login_json_is_unauthorized(): void
    {
        Auth::logout();

        $this->get(route('reports.tbank-payments.index'), ['HTTP_ACCEPT' => 'text/html'])
            ->assertRedirect();

        $this->getJson(route('reports.tbank-payments.total'))
            ->assertStatus(401);

        $this->get(route('reports.tbank-payments.index', ['view' => 'days']), ['HTTP_ACCEPT' => 'text/html'])
            ->assertRedirect();

        $this->getJson(route('reports.tbank-payments.total', ['view' => 'months']))
            ->assertStatus(401);
    }

    public function test_non_ajax_data_with_days_view_returns_json_not_empty_html(): void
    {
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
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
    }

    public function test_invalid_view_non_ajax_redirects_back_with_view_field_error(): void
    {
        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.index', ['view' => 'weeks']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.total', ['view' => 'weeks']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);

        $this->from(route('reports.tbank-payments.index'))
            ->post('/admin/reports/tbank-payments/columns-settings', [
                'view' => 'weeks',
                'columns' => ['amount' => true],
            ], ['HTTP_ACCEPT' => 'text/html'])
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);
    }
}
