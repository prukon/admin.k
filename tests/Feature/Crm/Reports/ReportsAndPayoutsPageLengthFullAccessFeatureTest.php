<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Доступ к персональному «Показать N» на отчётах и выплатах T‑Bank.
 *
 * @see ReportsAndPayoutsPageLengthFeatureTest
 */
final class ReportsAndPayoutsPageLengthFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function tablesProvider(): iterable
    {
        yield 'payments' => [[
            'permission'  => 'reports.view',
            'index_route' => 'payments',
            'get_url'     => '/admin/reports/payments/columns-settings',
            'save_url'    => '/admin/reports/payments/columns-settings',
            'table_key'   => 'reports_payments',
            'view_var'    => 'paymentsPageLength',
            'create'      => "KidsCrmDataTable.create('#payments-table'",
        ]];
        yield 'payments_monthly' => [[
            'permission'  => 'reports.view',
            'index_route' => 'reports.payments.monthly',
            'get_url'     => '/admin/reports/payments/monthly/columns-settings',
            'save_url'    => '/admin/reports/payments/monthly/columns-settings',
            'table_key'   => 'reports_payments_monthly',
            'view_var'    => 'paymentsMonthlyPageLength',
            'create'      => "KidsCrmDataTable.create('#payments-monthly-table'",
        ]];
        yield 'ltv' => [[
            'permission'  => 'reports.view',
            'index_route' => 'reports.ltv',
            'get_url'     => '/admin/reports/ltv/columns-settings',
            'save_url'    => '/admin/reports/ltv/columns-settings',
            'table_key'   => 'reports_ltv',
            'view_var'    => 'ltvPageLength',
            'create'      => "KidsCrmDataTable.create('#ltv-table'",
        ]];
        yield 'payment_intents' => [[
            'permission'  => 'reports.payment.intents.view',
            'index_route' => 'reports.payment-intents.index',
            'get_url'     => '/admin/reports/payment-intents/columns-settings',
            'save_url'    => '/admin/reports/payment-intents/columns-settings',
            'table_key'   => 'reports_payment_intents',
            'view_var'    => 'paymentIntentsPageLength',
            'create'      => "KidsCrmDataTable.create('#payment-intents-table'",
        ]];
        yield 'fiscal_receipts' => [[
            'permission'  => 'reports.fiscal.receipts.view',
            'index_route' => 'reports.fiscal-receipts.index',
            'get_url'     => '/admin/reports/fiscal-receipts/columns-settings',
            'save_url'    => '/admin/reports/fiscal-receipts/columns-settings',
            'table_key'   => 'reports_fiscal_receipts',
            'view_var'    => 'fiscalReceiptsPageLength',
            'create'      => "KidsCrmDataTable.create('#fiscal-receipts-table'",
        ]];
        yield 'tinkoff_payouts' => [[
            'permission'  => 'tbank.payouts.manage',
            'index_route' => 'admin.tinkoff.payouts.index',
            'get_url'     => '/admin/tinkoff/payouts/columns-settings',
            'save_url'    => '/admin/tinkoff/payouts/columns-settings',
            'table_key'   => 'tinkoff_payouts_index',
            'view_var'    => 'payoutsPageLength',
            'create'      => "KidsCrmDataTable.create('#payouts-table'",
        ]];
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_guest_is_denied_on_page_length_endpoints_without_500(array $case): void
    {
        Auth::logout();

        foreach ($this->pageLengthRoutes($case) as $item) {
            $response = $this->call($item['method'], $item['url'], $item['data'] ?? []);
            $this->assertNotSame(500, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertNotSame(200, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        }

        $this->assertSame(
            0,
            UserTableSetting::where('table_key', $case['table_key'])->count()
        );
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_user_without_permission_gets_403_on_page_length_endpoints(array $case): void
    {
        $denied = $this->createUserWithoutPermission($case['permission'], $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->pageLengthRoutes($case) as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $response->assertForbidden();
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_viewer_with_permission_can_save_show_by_and_sees_it_after_reload(array $case): void
    {
        $actor = $this->createUserWithoutPermission($case['permission'], $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, $case['permission']);

        $this->postJson($case['save_url'], ['page_length' => 50])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $html = $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 50)
            ->getContent();

        $this->assertStringContainsString('persistPageLength: true', $html);
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $html);

        $payload = $this->getJson($case['get_url'])->assertOk()->json();
        $this->assertArrayNotHasKey('page_length', $payload);
        $this->assertIsArray($payload);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_admin_can_save_show_by_via_ajax_and_non_ajax(array $case): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, $case['permission']);

        $this->postJson($case['save_url'], ['page_length' => 20])
            ->assertOk()
            ->assertJson(['success' => true]);

        $nonAjax = $this->from(route($case['index_route']))
            ->post($case['save_url'], ['page_length' => 100]);

        $this->assertNotSame(500, $nonAjax->getStatusCode());
        $this->assertSame(200, $nonAjax->getStatusCode());
        $this->assertNotSame('', trim((string) $nonAjax->getContent()));
        $nonAjax->assertJson(['success' => true]);

        $this->assertSame(
            100,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', $case['table_key'])
                ->value('page_length')
        );
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_unsupported_methods_on_columns_settings_do_not_save_page_length(array $case): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, $case['permission']);

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->json($method, $case['save_url'], ['page_length' => 50]);
            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertContains($response->getStatusCode(), [404, 405], $method);
        }

        $this->assertSame(
            0,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', $case['table_key'])
                ->count()
        );
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_get_columns_settings_as_web_request_is_json_not_empty_200(array $case): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, $case['permission']);

        $response = $this->get($case['get_url']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    /**
     * @param  array<string, mixed>  $case
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function pageLengthRoutes(array $case): array
    {
        return [
            ['method' => 'GET', 'url' => route($case['index_route'])],
            ['method' => 'GET', 'url' => $case['get_url']],
            ['method' => 'POST', 'url' => $case['save_url'], 'data' => ['page_length' => 20]],
        ];
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
