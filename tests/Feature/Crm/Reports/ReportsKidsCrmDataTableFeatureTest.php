<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\OutgoingEmailLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * KidsCrmDataTable для отчётов debt / LTV / payments-monthly (6e):
 * columns-settings, server-side detail tables, UI и полный доступ (200) по reports.view.
 */
final class ReportsKidsCrmDataTableFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
    }

    // -------------------------------------------------------------------------
    // columns-settings: контроль доступа
    // -------------------------------------------------------------------------

    public function test_debts_columns_settings_forbidden_without_reports_view(): void
    {
        $this->assertColumnsSettingsForbiddenWithoutReportsView(
            route('reports.debts.columns-settings.get'),
            route('reports.debts.columns-settings.save'),
        );
    }

    public function test_ltv_columns_settings_forbidden_without_reports_view(): void
    {
        $this->assertColumnsSettingsForbiddenWithoutReportsView(
            route('reports.ltv.columns-settings.get'),
            route('reports.ltv.columns-settings.save'),
        );
    }

    public function test_monthly_columns_settings_forbidden_without_reports_view(): void
    {
        $this->assertColumnsSettingsForbiddenWithoutReportsView(
            route('reports.payments.monthly.columns-settings.get'),
            route('reports.payments.monthly.columns-settings.save'),
        );
    }

    // -------------------------------------------------------------------------
    // columns-settings: сохранение и чтение
    // -------------------------------------------------------------------------

    public function test_debts_columns_settings_saved_and_loaded(): void
    {
        $this->assertColumnsSettingsRoundTrip(
            'reports_debts',
            route('reports.debts.columns-settings.get'),
            route('reports.debts.columns-settings.save'),
            [
                'user_name' => true,
                'month' => false,
                'price' => true,
            ],
        );
    }

    public function test_ltv_columns_settings_saved_and_loaded(): void
    {
        $this->assertColumnsSettingsRoundTrip(
            'reports_ltv',
            route('reports.ltv.columns-settings.get'),
            route('reports.ltv.columns-settings.save'),
            [
                'user_name' => true,
                'team_title' => false,
                'total_price' => true,
                'payment_count' => true,
            ],
        );
    }

    public function test_monthly_columns_settings_saved_and_loaded(): void
    {
        $this->assertColumnsSettingsRoundTrip(
            'reports_payments_monthly',
            route('reports.payments.monthly.columns-settings.get'),
            route('reports.payments.monthly.columns-settings.save'),
            [
                'month_title' => true,
                'payments_count' => false,
                'total_sum' => true,
            ],
        );
    }

    public function test_debts_columns_settings_validation_requires_columns_array(): void
    {
        $this->postJson(route('reports.debts.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    // -------------------------------------------------------------------------
    // UI: KidsCrmDataTable и переключатели колонок
    // -------------------------------------------------------------------------

    public function test_debts_page_renders_kidscrm_datatable_and_column_toggles(): void
    {
        $this->get(route('debts'))
            ->assertOk()
            ->assertViewIs('admin.report.index')
            ->assertViewHas('activeTab', 'debt')
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="debts-table"', false)
            ->assertSee('data-column-key="user_name"', false)
            ->assertSee('data-column-key="month"', false)
            ->assertSee('data-column-key="price"', false)
            ->assertSee("key: 'price', type: 'money'", false)
            ->assertSee('id="logsTable"', false)
            ->assertSee("KidsCrmDataTable.create('#logsTable'", false)
            ->assertDontSee('window.KidsCrmDataTableColumns = {', false)
            ->assertDontSee('initKidsCrmTooltipHints', false);
    }

    public function test_ltv_page_renders_kidscrm_datatable_and_column_toggles(): void
    {
        $this->get(route('reports.ltv'))
            ->assertOk()
            ->assertViewHas('activeTab', 'ltv')
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="ltv-table"', false)
            ->assertSee('data-column-key="user_name"', false)
            ->assertSee('data-column-key="total_price"', false)
            ->assertSee('data-column-key="payment_count"', false)
            ->assertSee("key: 'total_price', type: 'money'", false);
    }

    public function test_monthly_page_renders_kidscrm_datatable_and_column_toggles(): void
    {
        $this->get(route('reports.payments.monthly'))
            ->assertOk()
            ->assertViewHas('activeTab', 'payment-monthly')
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="payments-monthly-table"', false)
            ->assertSee('data-column-key="month_title"', false)
            ->assertSee('data-column-key="payments_count"', false)
            ->assertSee('data-column-key="total_sum"', false)
            ->assertSee("key: 'total_sum', type: 'money'", false);
    }

    public function test_migrated_report_tabs_render_kidscrm_datatable(): void
    {
        $this->asSuperadmin();

        $this->get(route('reports.emails.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="emails-table"', false);

        $this->get(route('reports.fiscal-receipts.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="fiscal-receipts-table"', false);

        $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="payment-intents-table"', false);
    }

    // -------------------------------------------------------------------------
    // Детализация: server-side DataTables (draw) и legacy JSON
    // -------------------------------------------------------------------------

    public function test_ltv_user_payments_datatables_draw_returns_meta_fields(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 700,
            'operation_date' => '2025-03-10 12:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ' => 300,
            'operation_date' => '2025-03-15 12:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $student->id,
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($json['draw'] ?? 0));
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsTotal', $json);
        $this->assertSame(2, (int) ($json['meta_payments_count'] ?? 0));
        $this->assertSame(1000.0, (float) ($json['meta_sum_total'] ?? 0));
        $this->assertCount(2, $json['data']);
    }

    public function test_ltv_user_payments_legacy_json_without_draw(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 500,
            'operation_date' => '2025-04-01 10:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', ['user' => $student->id]))
            ->assertOk()
            ->json();

        $this->assertSame($student->id, (int) ($json['user_id'] ?? 0));
        $this->assertArrayHasKey('payments', $json);
        $this->assertCount(1, $json['payments']);
        $this->assertArrayNotHasKey('draw', $json);
    }

    public function test_monthly_month_payments_datatables_draw_returns_meta_fields(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 400,
            'payment_month' => '2025-02-01',
            'operation_date' => '2025-02-05 10:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'summ' => 600,
            'payment_month' => '2025-02-01',
            'operation_date' => '2025-02-20 10:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-02',
                'mode' => 'subscription',
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($json['draw'] ?? 0));
        $this->assertArrayHasKey('data', $json);
        $this->assertSame(2, (int) ($json['meta_payments_count'] ?? 0));
        $this->assertSame(1000.0, (float) ($json['meta_sum_total'] ?? 0));
        $this->assertCount(2, $json['data']);
    }

    public function test_monthly_month_payments_legacy_json_without_draw(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 250,
            'payment_month' => '2025-05-01',
            'operation_date' => '2025-05-12 10:00:00',
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-05',
                'mode' => 'subscription',
            ]))
            ->assertOk()
            ->json();

        $this->assertSame('2025-05', $json['month_key'] ?? null);
        $this->assertSame('subscription', $json['mode'] ?? null);
        $this->assertArrayHasKey('payments', $json);
        $this->assertCount(1, $json['payments']);
        $this->assertArrayNotHasKey('draw', $json);
    }

    // -------------------------------------------------------------------------
    // Полный доступ: страница и все эндпоинты → 200
    // -------------------------------------------------------------------------

    public function test_debts_page_and_all_endpoints_return_200_for_authorized_user(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->insertUserPrice($student, [
            'is_paid'   => 0,
            'price'     => 500,
            'new_month' => '2026-01-01',
        ]);

        $this->get(route('debts'))->assertOk();
        $this->get(route('reports.debts.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('debts.getDebts', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.debts.columns-settings.get'))->assertOk();

        $this->postJson(route('reports.debts.columns-settings.save'), [
            'columns' => ['user_name' => true, 'month' => true, 'price' => true],
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_ltv_page_and_all_endpoints_return_200_for_authorized_user(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 900,
            'operation_date' => '2025-06-01 10:00:00',
        ]);

        $this->get(route('reports.ltv'))->assertOk();
        $this->get(route('reports.ltv.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', [
                'user' => $student->id,
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data', 'meta_payments_count', 'meta_sum_total']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.ltv.user_payments', ['user' => $student->id]))
            ->assertOk()
            ->assertJsonStructure(['user_id', 'payments']);

        $this->get(route('reports.ltv.columns-settings.get'))->assertOk();

        $this->postJson(route('reports.ltv.columns-settings.save'), [
            'columns' => ['user_name' => true, 'total_price' => true],
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_monthly_page_and_all_endpoints_return_200_for_authorized_user(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Payment::factory()->forUser($student)->create([
            'summ' => 800,
            'payment_month' => '2025-07-01',
            'operation_date' => '2025-07-10 10:00:00',
        ]);

        $this->get(route('reports.payments.monthly'))->assertOk();
        $this->get(route('reports.payments.monthly.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-07',
                'mode' => 'subscription',
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data', 'meta_payments_count', 'meta_sum_total']);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payments.monthly.payments', [
                'yearMonth' => '2025-07',
                'mode' => 'subscription',
            ]))
            ->assertOk()
            ->assertJsonStructure(['month_key', 'mode', 'payments']);

        $this->get(route('reports.payments.monthly.columns-settings.get'))->assertOk();

        $this->postJson(route('reports.payments.monthly.columns-settings.save'), [
            'columns' => ['month_title' => true, 'total_sum' => true],
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_outgoing_emails_page_and_all_endpoints_return_200_for_authorized_user(): void
    {
        $this->asSuperadmin();

        $log = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'full-access-smoke',
        ]);

        $this->get(route('reports.emails.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false);

        $this->get(route('reports.emails.index'))
            ->assertSee("key: 'to_summary'", false)
            ->assertSee("type: 'link'", false)
            ->assertSee('js-outgoing-email-show', false)
            ->assertSee('id="outgoingEmailShowModal"', false)
            ->assertSee('openOutgoingEmailShowModal', false);

        $html = $this->get(route('reports.emails.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            "/key:\s*'created_at'[\s\S]{0,80}?type:\s*'datetime'/",
            $html
        );
        $this->assertMatchesRegularExpression(
            "/key:\s*'sent_at'[\s\S]{0,80}?type:\s*'datetime'/",
            $html
        );

        $this->get(route('reports.emails.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.emails.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.emails.mailable.classes.search', ['q' => '']))->assertOk();

        $this->get('/admin/reports/emails/columns-settings')->assertOk();

        $this->postJson('/admin/reports/emails/columns-settings', [
            'columns' => ['id' => true, 'subject' => true],
        ])->assertOk()->assertJson(['success' => true]);

        $this->get(route('reports.emails.show', ['log' => $log->id]))->assertOk();
    }

    public function test_fiscal_receipts_page_and_all_endpoints_return_200_for_authorized_user(): void
    {
        $this->asSuperadmin();

        $this->get(route('reports.fiscal-receipts.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false);

        $this->get(route('reports.fiscal-receipts.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.fiscal-receipts.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.fiscal-receipts.partners.search', ['q' => '']))->assertOk();

        $this->get('/admin/reports/fiscal-receipts/columns-settings')->assertOk();

        $this->postJson('/admin/reports/fiscal-receipts/columns-settings', [
            'columns' => ['partner' => true, 'error' => true],
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_payment_intents_page_and_all_endpoints_return_200_for_authorized_user(): void
    {
        $this->asSuperadmin();

        $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false);

        $this->get(route('reports.payment-intents.total'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.payment-intents.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.payment-intents.partners.search', ['q' => '']))->assertOk();
        $this->get(route('reports.payment-intents.users.search', ['q' => '']))->assertOk();

        $this->get('/admin/reports/payment-intents/columns-settings')->assertOk();

        $this->postJson('/admin/reports/payment-intents/columns-settings', [
            'columns' => ['id' => true, 'status' => true],
        ])->assertOk()->assertJson(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function assertColumnsSettingsForbiddenWithoutReportsView(string $getUrl, string $postUrl): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get($getUrl)->assertForbidden();
        $this->postJson($postUrl, ['columns' => ['user_name' => true]])->assertForbidden();
    }

    /**
     * @param  array<string, bool>  $columns
     */
    private function assertColumnsSettingsRoundTrip(
        string $tableKey,
        string $getUrl,
        string $postUrl,
        array $columns,
    ): void {
        $payload = ['columns' => $columns];

        $this->get($getUrl)->assertOk()->assertExactJson([]);

        $this->postJson($postUrl, $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', $tableKey)
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($columns, $setting->columns);

        $this->get($getUrl)->assertOk()->assertExactJson($columns);
    }
}
