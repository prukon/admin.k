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
 * Персональное «Показать N» на отчётах платежей / LTV / intents / чеки и выплатах T‑Bank.
 *
 * @see \Tests\Feature\Crm\SchoolLeads\SchoolLeadsPageLengthFeatureTest
 */
final class ReportsAndPayoutsPageLengthFeatureTest extends CrmTestCase
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
            'auth'          => 'admin',
            'permission'    => 'reports.view',
            'index_route'   => 'payments',
            'get_url'       => '/admin/reports/payments/columns-settings',
            'save_url'      => '/admin/reports/payments/columns-settings',
            'table_key'     => 'reports_payments',
            'create'        => "KidsCrmDataTable.create('#payments-table'",
            'view_var'      => 'paymentsPageLength',
            'sample_columns'=> ['user_name' => false, 'team_title' => true],
        ]];
        yield 'payments_monthly' => [[
            'auth'          => 'admin',
            'permission'    => 'reports.view',
            'index_route'   => 'reports.payments.monthly',
            'get_url'       => '/admin/reports/payments/monthly/columns-settings',
            'save_url'      => '/admin/reports/payments/monthly/columns-settings',
            'table_key'     => 'reports_payments_monthly',
            'create'        => "KidsCrmDataTable.create('#payments-monthly-table'",
            'view_var'      => 'paymentsMonthlyPageLength',
            'sample_columns'=> ['month_title' => true, 'payments_count' => false],
        ]];
        yield 'ltv' => [[
            'auth'          => 'admin',
            'permission'    => 'reports.view',
            'index_route'   => 'reports.ltv',
            'get_url'       => '/admin/reports/ltv/columns-settings',
            'save_url'      => '/admin/reports/ltv/columns-settings',
            'table_key'     => 'reports_ltv',
            'create'        => "KidsCrmDataTable.create('#ltv-table'",
            'view_var'      => 'ltvPageLength',
            'sample_columns'=> ['user_name' => true, 'team_title' => false],
        ]];
        yield 'payment_intents' => [[
            'auth'          => 'superadmin',
            'permission'    => 'reports.payment.intents.view',
            'index_route'   => 'reports.payment-intents.index',
            'get_url'       => '/admin/reports/payment-intents/columns-settings',
            'save_url'      => '/admin/reports/payment-intents/columns-settings',
            'table_key'     => 'reports_payment_intents',
            'create'        => "KidsCrmDataTable.create('#payment-intents-table'",
            'view_var'      => 'paymentIntentsPageLength',
            'sample_columns'=> ['id' => true, 'status' => false],
        ]];
        yield 'fiscal_receipts' => [[
            'auth'          => 'superadmin',
            'permission'    => 'reports.fiscal.receipts.view',
            'index_route'   => 'reports.fiscal-receipts.index',
            'get_url'       => '/admin/reports/fiscal-receipts/columns-settings',
            'save_url'      => '/admin/reports/fiscal-receipts/columns-settings',
            'table_key'     => 'reports_fiscal_receipts',
            'create'        => "KidsCrmDataTable.create('#fiscal-receipts-table'",
            'view_var'      => 'fiscalReceiptsPageLength',
            'sample_columns'=> ['partner' => true, 'error' => false],
        ]];
        yield 'tinkoff_payouts' => [[
            'auth'          => 'payouts',
            'permission'    => 'tbank.payouts.manage',
            'index_route'   => 'admin.tinkoff.payouts.index',
            'get_url'       => '/admin/tinkoff/payouts/columns-settings',
            'save_url'      => '/admin/tinkoff/payouts/columns-settings',
            'table_key'     => 'tinkoff_payouts_index',
            'create'        => "KidsCrmDataTable.create('#payouts-table'",
            'view_var'      => 'payoutsPageLength',
            'sample_columns'=> ['status' => true, 'source' => false],
        ]];
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_index_uses_default_page_length_when_nothing_saved(array $case): void
    {
        $this->authenticateCase($case);
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->delete();

        $html = $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 10)
            ->getContent();

        $chunk = $this->createChunk($html, $case['create']);
        $this->assertStringContainsString('persistPageLength: true', $chunk);
        $this->assertMatchesRegularExpression('/pageLength:\s*10\b/', $chunk);
        $this->assertSame(1, substr_count($html, 'persistPageLength: true'));
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_after_changing_show_by_reopening_page_keeps_saved_length(array $case): void
    {
        $this->authenticateCase($case);

        $this->postJson($case['save_url'], ['page_length' => 50])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $html = $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 50)
            ->getContent();

        $chunk = $this->createChunk($html, $case['create']);
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $chunk);
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*10\b/', $chunk);
        $this->assertStringContainsString('persistPageLength: true', $chunk);
        $this->assertStringNotContainsString('start:', $chunk);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_ajax_save_page_length_does_not_wipe_hidden_columns(array $case): void
    {
        $this->authenticateCase($case);

        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => $case['table_key']],
            ['columns' => $case['sample_columns']]
        );

        $this->postJson($case['save_url'], ['page_length' => 100])->assertOk();

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->firstOrFail();

        $this->assertSame(100, $setting->page_length);
        foreach ($case['sample_columns'] as $key => $visible) {
            $this->assertSame($visible, $setting->columns[$key] ?? null, $key);
        }

        $payload = $this->getJson($case['get_url'])->assertOk()->json();
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_hiding_columns_then_reopening_page_does_not_reset_show_by(array $case): void
    {
        $this->authenticateCase($case);

        $this->postJson($case['save_url'], ['page_length' => 20])->assertOk();
        $this->postJson($case['save_url'], ['columns' => $case['sample_columns']])->assertOk();

        $html = $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 20)
            ->getContent();

        $this->assertMatchesRegularExpression('/pageLength:\s*20\b/', $this->createChunk($html, $case['create']));

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->firstOrFail();
        $this->assertSame(20, $setting->page_length);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_ajax_save_invalid_page_length_returns_422_with_field_error(array $case): void
    {
        $this->authenticateCase($case);

        $this->postJson($case['save_url'], ['page_length' => 15])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page_length'])
            ->assertJsonPath('errors.page_length.0', 'Можно показать 10, 20, 50 или 100 записей.');

        foreach ([0, -1, 25, 'abc', 10.5] as $invalid) {
            $this->postJson($case['save_url'], ['page_length' => $invalid])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['page_length']);
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
    public function test_non_ajax_save_page_length_returns_json_and_invalid_redirects_with_field_error(array $case): void
    {
        $this->authenticateCase($case);

        $ok = $this->from(route($case['index_route']))
            ->post($case['save_url'], ['page_length' => '50']);

        $this->assertSame(200, $ok->getStatusCode());
        $this->assertNotSame('', trim((string) $ok->getContent()));
        $ok->assertJson(['success' => true]);
        $this->assertSame(
            50,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', $case['table_key'])
                ->value('page_length')
        );

        $this->from(route($case['index_route']))
            ->post($case['save_url'], ['page_length' => 7])
            ->assertStatus(302)
            ->assertSessionHasErrors(['page_length']);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_empty_columns_together_with_page_length_does_not_wipe_hidden_columns(array $case): void
    {
        $this->authenticateCase($case);

        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => $case['table_key']],
            [
                'columns'     => $case['sample_columns'],
                'page_length' => 10,
            ]
        );

        $this->postJson($case['save_url'], [
            'columns'     => [],
            'page_length' => 50,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->firstOrFail();

        $this->assertSame(10, $setting->page_length);
        foreach ($case['sample_columns'] as $key => $visible) {
            $this->assertSame($visible, $setting->columns[$key] ?? null, $key);
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_changing_show_by_does_not_persist_page_number(array $case): void
    {
        $this->authenticateCase($case);

        $this->postJson($case['save_url'], [
            'page_length' => 20,
            'start'       => 40,
            'draw'        => 3,
        ])->assertOk();

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->firstOrFail();

        $this->assertSame(20, $setting->page_length);
        $this->assertArrayNotHasKey('start', $setting->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_guest_is_denied_on_index_and_page_length_endpoints(array $case): void
    {
        $this->authenticateCase($case);
        Auth::logout();

        $index = $this->get(route($case['index_route']));
        $this->assertContains($index->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $index->getStatusCode());
        $this->assertNotSame(200, $index->getStatusCode());

        $get = $this->getJson($case['get_url']);
        $this->assertContains($get->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $get->getStatusCode());

        $post = $this->from(route($case['index_route']))
            ->post($case['save_url'], ['page_length' => 20]);
        $this->assertContains($post->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $post->getStatusCode());
        $this->assertNotSame(200, $post->getStatusCode());
        $this->assertSame(
            0,
            UserTableSetting::where('table_key', $case['table_key'])->count()
        );
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_user_without_permission_gets_403_on_index_and_page_length_endpoints(array $case): void
    {
        $denied = $this->createUserWithoutPermission($case['permission'], $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route($case['index_route']))->assertForbidden();
        $this->getJson($case['get_url'])->assertForbidden();
        $this->postJson($case['save_url'], ['page_length' => 20])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_index_falls_back_to_default_when_stored_page_length_is_invalid(array $case): void
    {
        $this->authenticateCase($case);

        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => $case['table_key']],
            ['page_length' => 99]
        );

        $html = $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 10)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/pageLength:\s*10\b/',
            $this->createChunk($html, $case['create'])
        );
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_index_does_not_use_another_admins_or_foreign_partner_page_length(array $case): void
    {
        $this->authenticateCase($case);

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
        ]);
        UserTableSetting::updateOrCreate(
            ['user_id' => $other->id, 'table_key' => $case['table_key']],
            ['page_length' => 100]
        );
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->foreignUser->id, 'table_key' => $case['table_key']],
            ['page_length' => 50]
        );

        $html = $this->get(route($case['index_route']))
            ->assertOk()
            ->assertViewHas($case['view_var'], 10)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/pageLength:\s*10\b/',
            $this->createChunk($html, $case['create'])
        );
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_ajax_save_page_length_without_columns_leaves_columns_empty_and_get_omits_page_length(array $case): void
    {
        $this->authenticateCase($case);
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->delete();

        $this->postJson($case['save_url'], ['page_length' => 20])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->firstOrFail();
        $this->assertSame(20, $setting->page_length);
        $this->assertNull($setting->columns);

        $payload = $this->getJson($case['get_url'])->assertOk()->json();
        $this->assertSame([], $payload);
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_ajax_save_accepts_each_allowed_page_length(array $case): void
    {
        $this->authenticateCase($case);

        foreach (UserTableSetting::PAGE_LENGTHS as $length) {
            $this->postJson($case['save_url'], ['page_length' => $length])
                ->assertOk()
                ->assertJson(['success' => true]);

            $this->assertSame(
                $length,
                UserTableSetting::where('user_id', $this->user->id)
                    ->where('table_key', $case['table_key'])
                    ->value('page_length')
            );
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_ajax_save_empty_payload_still_requires_columns(array $case): void
    {
        $this->authenticateCase($case);

        $this->postJson($case['save_url'], [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_non_ajax_empty_payload_redirects_back_with_columns_field_error(array $case): void
    {
        $this->authenticateCase($case);

        $this->from(route($case['index_route']))
            ->post($case['save_url'], [])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_ajax_save_can_persist_columns_and_page_length_together(array $case): void
    {
        $this->authenticateCase($case);

        $this->postJson($case['save_url'], [
            'columns'     => $case['sample_columns'],
            'page_length' => 20,
        ])->assertOk();

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', $case['table_key'])
            ->firstOrFail();

        $this->assertSame(20, $setting->page_length);
        foreach ($case['sample_columns'] as $key => $visible) {
            $this->assertSame($visible, $setting->columns[$key] ?? null, $key);
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('tablesProvider')]
    public function test_reopening_page_with_filters_keeps_saved_show_by(array $case): void
    {
        $this->authenticateCase($case);

        $this->postJson($case['save_url'], ['page_length' => 50])->assertOk();

        $html = $this->get(route($case['index_route'], [
            'status'  => 'all',
            'user_id' => 1,
        ]))
            ->assertOk()
            ->assertViewHas($case['view_var'], 50)
            ->getContent();

        $chunk = $this->createChunk($html, $case['create']);
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $chunk);
        $this->assertStringContainsString('persistPageLength: true', $chunk);
    }

    public function test_saved_show_by_on_payments_does_not_change_other_reports(): void
    {
        $this->asAdmin();

        $this->postJson('/admin/reports/payments/columns-settings', [
            'page_length' => 100,
        ])->assertOk();

        $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->assertViewHas('paymentsMonthlyPageLength', 10);
        $this->get(route('reports.ltv'))
            ->assertOk()
            ->assertViewHas('ltvPageLength', 10);
        $this->get(route('payments'))
            ->assertOk()
            ->assertViewHas('paymentsPageLength', 100);
    }

    public function test_after_saving_show_by_nested_monthly_and_ltv_tables_still_show_ten(): void
    {
        $this->asAdmin();

        $this->postJson(route('reports.payments.monthly.columns-settings.save'), [
            'page_length' => 50,
        ])->assertOk();
        $this->postJson(route('reports.ltv.columns-settings.save'), [
            'page_length' => 50,
        ])->assertOk();

        $monthly = $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->assertViewHas('paymentsMonthlyPageLength', 50)
            ->getContent();
        $monthlyMain = $this->createChunk($monthly, "KidsCrmDataTable.create('#payments-monthly-table'");
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $monthlyMain);
        $monthlyDetail = $this->jsFunctionChunk($monthly, 'function initMonthlyPaymentsDetailTable');
        $this->assertStringContainsString('pageLength: 10', $monthlyDetail);
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*50\b/', $monthlyDetail);
        $this->assertStringNotContainsString('persistPageLength', $monthlyDetail);
        $this->assertSame(1, substr_count($monthly, 'persistPageLength: true'));

        $ltv = $this->get(route('reports.ltv'))
            ->assertOk()
            ->assertViewHas('ltvPageLength', 50)
            ->getContent();
        $ltvMain = $this->createChunk($ltv, "KidsCrmDataTable.create('#ltv-table'");
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $ltvMain);
        $ltvDetail = $this->jsFunctionChunk($ltv, 'function initLtvUserPaymentsDetailTable');
        $this->assertStringContainsString('pageLength: 10', $ltvDetail);
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*50\b/', $ltvDetail);
        $this->assertStringNotContainsString('persistPageLength', $ltvDetail);
        $this->assertSame(1, substr_count($ltv, 'persistPageLength: true'));
    }

    public function test_monthly_and_ltv_detail_tables_do_not_opt_in_to_page_length_persist(): void
    {
        $this->asAdmin();

        $monthly = $this->get(route('reports.payments.monthly'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($monthly, 'persistPageLength: true'));
        $this->assertStringContainsString("KidsCrmDataTable.create('#payments-monthly-table'", $monthly);
        $this->assertStringContainsString('function initMonthlyPaymentsDetailTable', $monthly);
        $this->assertStringContainsString('pageLength: 10', $this->jsFunctionChunk(
            $monthly,
            'function initMonthlyPaymentsDetailTable'
        ));

        $ltv = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($ltv, 'persistPageLength: true'));
        $this->assertStringContainsString("KidsCrmDataTable.create('#ltv-table'", $ltv);
        $this->assertStringContainsString('function initLtvUserPaymentsDetailTable', $ltv);
        $this->assertStringContainsString('pageLength: 10', $this->jsFunctionChunk(
            $ltv,
            'function initLtvUserPaymentsDetailTable'
        ));
    }

    public function test_debts_and_emails_reports_do_not_opt_in_to_page_length_persist(): void
    {
        $this->asSuperadmin();

        $debts = $this->get(route('debts'))->assertOk()->getContent();
        $this->assertStringNotContainsString('persistPageLength: true', $debts);

        $emails = $this->get(route('reports.emails.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('persistPageLength: true', $emails);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function authenticateCase(array $case): void
    {
        if ($case['auth'] === 'superadmin') {
            $this->asSuperadmin();

            return;
        }

        $this->asAdmin();
        if ($case['auth'] === 'payouts') {
            $this->grantPermission($this->user, 'tbank.payouts.manage');
        }
    }

    private function createChunk(string $html, string $needle): string
    {
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos, $needle.' не найден');

        return substr($html, $pos, 4500);
    }

    private function jsFunctionChunk(string $html, string $fn): string
    {
        $pos = strpos($html, $fn);
        $this->assertNotFalse($pos, $fn.' не найден');

        return substr($html, $pos, 2500);
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
