<?php

namespace Tests\Feature\Crm\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Итерации 1–2 миграции KidsCrmDataTable: custom → типовые колонки.
 */
final class KidsCrmDataTableColumnsMigrationIter1Iter2Test extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_iteration1_pages_use_preset_column_types_without_custom(): void
    {
        $this->asSuperadmin();

        $this->assertPageColumnUsesPresetType(route('admin.setting.logs'), 'description', 'text');
        $this->assertPageColumnUsesPresetType(route('debts'), 'user_name', 'text');
        $this->assertPageColumnUsesPresetType(route('debts'), 'month', 'text');
        $this->assertPageColumnUsesPresetType(route('debts'), 'price', 'money');
        $this->assertPageColumnUsesPresetType(route('reports.emails.index'), 'status', 'badge');
        $this->assertPageColumnUsesPresetType(route('reports.emails.index'), 'created_at', 'datetime');
        $this->assertPageColumnUsesPresetType(route('reports.emails.index'), 'sent_at', 'datetime');
        $this->assertPageColumnUsesPresetType(route('reports.emails.index'), 'error_excerpt', 'text');
        $this->assertPageColumnUsesPresetType(route('reports.emails.index'), 'actions', 'actions');
    }

    public function test_iteration1_contract_pages_use_badge_for_status_label(): void
    {
        $this->asAdmin();

        $this->assertPageColumnUsesPresetType(route('contracts.index'), 'status_label', 'badge');
        $this->assertPageColumnUsesPresetType(route('contract-templates.index'), 'status_label', 'badge');
    }

    public function test_iteration2_report_pages_use_preset_types_with_only_expandable_custom(): void
    {
        $this->asSuperadmin();

        $monthlyHtml = $this->get(route('reports.payments.monthly'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($monthlyHtml, 'total_sum', 'money');
        $this->assertColumnBlockUsesType($monthlyHtml, 'month_key', 'text');
        $this->assertSame(1, substr_count($monthlyHtml, "type: 'custom'"));

        $ltvHtml = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($ltvHtml, 'total_price', 'money');
        $this->assertColumnBlockUsesType($ltvHtml, 'first_payment_date', 'datetime');
        $this->assertColumnBlockUsesType($ltvHtml, 'is_enabled', 'badge');
        $this->assertColumnBlockUsesType($ltvHtml, 'user_id', 'id');
        $this->assertSame(1, substr_count($ltvHtml, "type: 'custom'"));
    }

    public function test_iteration2_partners_and_teams_pages_use_preset_types(): void
    {
        $this->asSuperadmin();

        $this->assertPageColumnUsesPresetType(route('admin.partner.index'), 'title', 'link');
        $this->assertPageColumnUsesPresetType(route('admin.team.index'), 'weekdays_label', 'list');
    }

    public function test_iteration2_payouts_page_uses_preset_types_without_custom(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.tinkoff.payouts.index'))->assertOk()->getContent();

        $this->assertColumnBlockUsesType($html, 'status', 'badge');
        $this->assertColumnBlockUsesType($html, 'payment', 'link');
        $this->assertColumnBlockUsesType($html, 'when_to_run', 'datetime');
        $this->assertStringNotContainsString("type: 'custom'", $html);
    }

    private function assertPageColumnUsesPresetType(string $url, string $key, string $type): void
    {
        $html = $this->get($url)->assertOk()->getContent();
        $this->assertColumnBlockUsesType($html, $key, $type);
    }

    private function assertColumnBlockUsesType(string $html, string $key, string $type): void
    {
        $pattern = "/key:\s*'".preg_quote($key, '/')."'[\s\S]{0,120}?type:\s*'".preg_quote($type, '/')."'/";
        $this->assertMatchesRegularExpression(
            $pattern,
            $html,
            "Expected column '{$key}' to use preset type '{$type}'"
        );
        $this->assertDoesNotMatchRegularExpression(
            "/key:\s*'".preg_quote($key, '/')."'[\s\S]{0,40}?type:\s*'custom'/",
            $html,
            "Column '{$key}' must not use type 'custom'"
        );
    }

    public function test_iteration7_school_leads_use_inline_select_and_actions_without_custom(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertColumnBlockUsesType($html, 'status', 'inline-select');
        $this->assertColumnBlockUsesType($html, 'contract', 'actions');
        $this->assertStringNotContainsString("type: 'custom'", $html);
    }

    public function test_iteration_abcd_icon_inline_select_and_actions_preset_types(): void
    {
        $this->asSuperadmin();

        $paymentsHtml = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringContainsString("name === 'receipt'", $paymentsHtml);
        $this->assertStringContainsString("return 'icon'", $paymentsHtml);
        $this->assertStringContainsString("name === 'refund_action'", $paymentsHtml);
        $this->assertStringContainsString("return 'actions'", $paymentsHtml);
        $this->assertStringContainsString('renderPaymentsRefundActions', $paymentsHtml);
        $this->assertStringNotContainsString("name === 'receipt' || name === 'refund_action'", $paymentsHtml);

        $usersHtml = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($usersHtml, 'contract', 'icon');

        $schoolLeadsHtml = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($schoolLeadsHtml, 'status', 'inline-select');

        $this->grantPartnerLeadsViewForCurrentUser();
        $partnerLeadsHtml = $this->get(route('admin.partner-leads'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($partnerLeadsHtml, 'status', 'inline-select');
        $this->assertStringNotContainsString("type: 'custom'", $partnerLeadsHtml);

        $presetJs = (string) file_get_contents(base_path('resources/js/kids-datatable.js'));
        $this->assertStringContainsString("case 'icon':", $presetJs);
        $this->assertStringContainsString("case 'inline-select':", $presetJs);
        $this->assertStringContainsString('renderIcon:', $presetJs);
        $this->assertStringContainsString('renderInlineSelect:', $presetJs);
    }

    public function test_iteration6_payments_report_buildPaymentsKidsColumns_without_legacy_custom_mapper(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('payments'))->assertOk()->getContent();

        $this->assertStringContainsString('buildPaymentsKidsColumns', $html);
        $this->assertStringContainsString('paymentsColumnPresetType', $html);
        $this->assertStringNotContainsString('wrapPaymentsColumnsForKids', $html);
        $this->assertStringContainsString("return 'money'", $html);
        $this->assertStringContainsString("return 'datetime'", $html);
        $this->assertStringContainsString("return 'badge'", $html);
    }

    public function test_iteration5_tbank_commissions_ulp_assignments_and_custom_payments_use_preset_types(): void
    {
        $this->asSuperadmin();

        $tbankHtml = $this->get(route('admin.setting.tbankCommissions'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($tbankHtml, 'partner_title', 'text');
        $this->assertColumnBlockUsesType($tbankHtml, 'acquiring_percent', 'text');
        $this->assertColumnBlockUsesType($tbankHtml, 'auto_payout', 'badge');
        $this->assertColumnBlockUsesType($tbankHtml, 'actions', 'actions');
        $this->assertStringNotContainsString("type: 'custom'", $tbankHtml);

        $this->grantLessonPackagesViewForCurrentUser();
        $assignmentsHtml = $this->get(route('admin.lesson-packages.assignments'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($assignmentsHtml, 'id', 'id');
        $this->assertColumnBlockUsesType($assignmentsHtml, 'paid', 'badge');
        $this->assertColumnBlockUsesType($assignmentsHtml, 'pay_link', 'text');
        $this->assertColumnBlockUsesType($assignmentsHtml, 'actions', 'actions');
        $this->assertStringNotContainsString("type: 'custom'", $assignmentsHtml);

        $this->get(route('admin.settingPrices.customPayments'))->assertOk();

        $customPaymentsJs = (string) file_get_contents(base_path('resources/js/setting-prices-custom-payments.js'));
        $this->assertColumnBlockUsesType($customPaymentsJs, 'status', 'badge');
        $this->assertColumnBlockUsesType($customPaymentsJs, 'actions', 'actions');
        $this->assertStringNotContainsString("type: 'custom'", $customPaymentsJs);
    }

    private function grantLessonPackagesViewForCurrentUser(): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId('lessonPackages.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function test_iteration4_users_trainers_and_partner_leads_use_preset_types(): void
    {
        $this->asSuperadmin();

        $usersHtml = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($usersHtml, 'avatar', 'image');
        $this->assertColumnBlockUsesType($usersHtml, 'name', 'link');
        $this->assertColumnBlockUsesType($usersHtml, 'status_label', 'badge');
        $this->assertColumnBlockUsesType($usersHtml, 'contract', 'icon');

        $this->grantTrainersViewForCurrentUser();
        $trainersHtml = $this->get(route('admin.trainers.index'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($trainersHtml, 'avatar', 'image');
        $this->assertStringNotContainsString("type: 'custom'", $trainersHtml);

        $this->grantPartnerLeadsViewForCurrentUser();
        $leadsHtml = $this->get(route('admin.partner-leads'))->assertOk()->getContent();
        $this->assertColumnBlockUsesType($leadsHtml, 'website', 'link');
        $this->assertColumnBlockUsesType($leadsHtml, 'status', 'inline-select');
        $this->assertStringNotContainsString("type: 'custom'", $leadsHtml);
    }

    private function grantTrainersViewForCurrentUser(): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId('trainers.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function grantPartnerLeadsViewForCurrentUser(): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId('partnerLeads.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function test_iteration3_fiscal_receipts_page_uses_preset_types_without_custom(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('reports.fiscal-receipts.index'))->assertOk()->getContent();

        $this->assertColumnBlockUsesType($html, 'amount', 'money');
        $this->assertColumnBlockUsesType($html, 'request_payload', 'inline-actions');
        $this->assertColumnBlockUsesType($html, 'webhook_payload', 'inline-actions');
        $this->assertColumnBlockUsesType($html, 'processed_at', 'datetime');
        $this->assertStringNotContainsString("type: 'custom'", $html);
    }

    public function test_get_debts_formats_month_for_display(): void
    {
        Carbon::setTestNow('2026-02-15');
        $this->asAdmin();

        $this->insertUserPrice($this->user, [
            'is_paid'   => 0,
            'price'     => 500,
            'new_month' => '2026-01-01',
        ]);

        DB::table('user_custom_payment')->insert([
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'is_paid' => 0,
            'amount' => 300,
            'date_start' => '2025-12-01',
            'date_end' => '2025-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('debts.getDebts', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        );

        $monthly = $rows->first(fn ($row) => ($row['month'] ?? '') === 'Январь 2026');
        $this->assertNotNull($monthly, 'Expected formatted monthly debt month');

        $period = $rows->first(fn ($row) => str_contains((string) ($row['month'] ?? ''), ' — '));
        $this->assertNotNull($period, 'Expected period debt month preserved as range');
        $this->assertStringContainsString('2025-12-01', (string) $period['month']);
        $this->assertStringContainsString('2025-12-31', (string) $period['month']);
    }
}
