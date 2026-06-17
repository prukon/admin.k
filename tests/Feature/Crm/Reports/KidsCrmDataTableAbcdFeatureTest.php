<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Contract;
use App\Models\PartnerLead;
use App\Models\Payment;
use App\Models\PaymentSystem;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Итерации A–D KidsCrmDataTable: icon, inline-select, actions, money/tooltip;
 * контроль доступа страниц и связанных endpoint'ов (200 при наличии прав).
 */
final class KidsCrmDataTableAbcdFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    // --- UI / assets (новый функционал) ---

    public function test_kids_tooltip_render_text_defers_bootstrap_toggle_to_init(): void
    {
        $tooltipJs = (string) file_get_contents(base_path('resources/js/kids-tooltip.js'));

        $this->assertStringContainsString('data-dt-ellipsis-title', $tooltipJs);
        $this->assertStringContainsString('function isTextOverflowing', $tooltipJs);
        $this->assertStringContainsString('dt-cell-ellipsis--truncated', $tooltipJs);
        $this->assertStringNotContainsString(
            "+ 'data-bs-toggle=\"tooltip\"'",
            $tooltipJs
        );
        $this->assertStringContainsString("+ 'data-dt-ellipsis-title=\"'", $tooltipJs);
    }

    public function test_datatables_columns_css_money_value_does_not_target_dt_col_id(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/datatables-columns.css'));

        $this->assertStringContainsString('td.dt-col-count .dt-col-money-value', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/th\.dt-col-id[\s\S]{0,80}display:\s*block/',
            $css,
            'dt-col-id must not get display:block from money-value rule'
        );
    }

    public function test_kids_datatable_money_type_wraps_formatted_value(): void
    {
        $presetJs = (string) file_get_contents(base_path('resources/js/kids-datatable.js'));

        $this->assertStringContainsString("className: col.className || 'dt-col-count'", $presetJs);
        $this->assertStringContainsString('dt-col-money-value', $presetJs);
        $this->assertStringNotContainsString("className: col.className || 'dt-col-count text-end'", $presetJs);
    }

    public function test_payments_page_includes_icon_and_actions_client_renderers(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('payments'))->assertOk()->getContent();

        $this->assertStringContainsString('renderPaymentsReceiptCell', $html);
        $this->assertStringContainsString('KidsCrmDataTable.renderIcon', $html);
        $this->assertStringContainsString('renderPaymentsRefundActions', $html);
        $this->assertStringContainsString("return 'icon'", $html);
        $this->assertStringContainsString("return 'actions'", $html);
        $this->assertStringNotContainsString("name === 'receipt' || name === 'refund_action'", $html);
    }

    public function test_school_leads_page_includes_inline_select_and_contract_actions_markup(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringContainsString("type: 'inline-select'", $html);
        $this->assertStringContainsString('inlineSelect:', $html);
        $this->assertStringContainsString('lead-status-badge', $html);
        $this->assertStringContainsString('lead-status-select', $html);
        $this->assertStringContainsString('edit-lead', $html);
        $this->assertStringContainsString("key: 'contract'", $html);
        $this->assertStringContainsString("type: 'actions'", $html);
        $this->assertStringNotContainsString("type: 'custom'", $html);
    }

    public function test_partner_leads_page_includes_inline_select_without_custom(): void
    {
        $this->asSuperadmin();
        $this->grantPermissionForCurrentUser('partnerLeads.view');

        $html = $this->get(route('admin.partner-leads'))->assertOk()->getContent();

        $this->assertStringContainsString("type: 'inline-select'", $html);
        $this->assertStringContainsString('lead-status-badge', $html);
        $this->assertStringNotContainsString("type: 'custom'", $html);
    }

    public function test_users_page_contract_column_uses_icon_renderer(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertStringContainsString("key: 'contract'", $html);
        $this->assertStringContainsString("type: 'icon'", $html);
        $this->assertStringContainsString('renderContractCell', $html);
        $this->assertStringContainsString('fa-file-pdf', $html);
    }

    public function test_teams_page_month_price_uses_money_preset(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.team.index'))->assertOk()->getContent();

        $this->assertStringContainsString("key: 'month_price'", $html);
        $this->assertStringContainsString("type: 'money'", $html);
        $this->assertStringNotContainsString("type: 'custom'", $html);
    }

    // --- API / data (новый функционал) ---

    public function test_get_payments_returns_refund_meta_fields_without_html_refund_action(): void
    {
        $this->asAdmin();

        $ps = new PaymentSystem();
        $ps->partner_id = $this->partner->id;
        $ps->name = 'tbank';
        $ps->save();

        $payment = Payment::factory()->create([
            'partner_id'     => $this->partner->id,
            'user_id'        => $this->user->id,
            'summ'           => 150.00,
            'deal_id'        => 'deal-abcd-meta',
            'payment_id'     => 'abcd-refund-meta',
            'payment_status' => 'CONFIRMED',
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'))
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $payment->id);
        $this->assertNotNull($row);

        $this->assertArrayHasKey('refund_actions_available', $row);
        $this->assertArrayHasKey('refund_disabled', $row);
        $this->assertArrayHasKey('refund_disabled_title', $row);
        $this->assertArrayHasKey('refund_show_history', $row);
        $this->assertArrayHasKey('receipt_url', $row);
        $this->assertArrayHasKey('has_receipt', $row);

        $this->assertArrayNotHasKey('refund_action', $row);
        foreach (['refund_disabled_title'] as $key) {
            $value = (string) ($row[$key] ?? '');
            $this->assertStringNotContainsString('<button', $value, "Field {$key} must not contain HTML");
        }
    }

    public function test_users_data_includes_latest_contract_for_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->grantPermissionForUser($actor, 'users.view');
        $this->grantPermissionForUser($actor, 'contracts.view');
        $this->actingAs($actor);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'АБЦД',
            'lastname'   => 'ДоговорТест',
        ]);

        Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/abcd-contract.pdf',
            'source_sha256'   => str_repeat('b', 64),
            'status'          => 'signed',
        ]);

        $response = $this
            ->getJson('/admin/users/data?draw=1&start=0&length=50&name=ДоговорТест')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $student->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('latest_contract', $row);
        $this->assertIsArray($row['latest_contract']);
        $this->assertArrayHasKey('url', $row['latest_contract']);
        $this->assertArrayHasKey('status', $row['latest_contract']);
        $this->assertArrayHasKey('status_label', $row['latest_contract']);
    }

    public function test_teams_data_returns_month_price_integer_and_null(): void
    {
        $this->asAdmin();

        $withPrice = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'ABCD price set',
            'month_price' => 4200,
        ]);
        $withoutPrice = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'ABCD price null',
            'month_price' => null,
        ]);

        $json = $this
            ->getJson('/admin/teams/data?draw=1&start=0&length=100&title=ABCD price')
            ->assertOk()
            ->json();

        $rowWith = collect($json['data'])->firstWhere('id', $withPrice->id);
        $rowWithout = collect($json['data'])->firstWhere('id', $withoutPrice->id);

        $this->assertSame(4200, $rowWith['month_price']);
        $this->assertNull($rowWithout['month_price']);
    }

    public function test_school_lead_status_update_endpoint_still_works_with_inline_select(): void
    {
        $this->asAdmin();

        $processingStatusId = $this->schoolLeadProcessingStatusId();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Inline select ABCD',
            'phone'                 => '+7 900 200-00-02',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this
            ->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id' => $processingStatusId,
                'comment'               => 'ABCD inline-select smoke',
            ])
            ->assertOk();

        $this->assertSame($processingStatusId, (int) $lead->fresh()->school_lead_status_id);
    }

    public function test_partner_lead_status_update_endpoint_still_works_with_inline_select(): void
    {
        $this->asSuperadmin();
        $this->grantPermissionForCurrentUser('partnerLeads.view');

        $lead = PartnerLead::create([
            'name'   => 'Partner inline ABCD',
            'phone'  => '+7 900 300-00-03',
            'status' => 'new',
        ]);

        $this
            ->putJson(route('admin.partner-leads.update', ['partnerLead' => $lead->id]), [
                'status'  => 'processing',
                'comment' => 'ABCD partner inline-select',
            ])
            ->assertOk();

        $status = $lead->fresh()->status;
        $this->assertSame('processing', is_object($status) && property_exists($status, 'value') ? $status->value : $status);
    }

    // --- Контроль доступа: страница + все endpoint'ы → 200 ---

    public function test_abcd_payments_section_endpoints_return_200_with_reports_view(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->grantPermissionForUser($actor, 'reports.view');
        $this->actingAs($actor);

        $routes = [
            ['GET', '/admin/reports/payments', ['HTTP_ACCEPT' => 'text/html']],
            ['GET', '/admin/reports/getPayments', ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']],
            ['GET', '/admin/reports/payments/columns-settings', []],
            ['POST', '/admin/reports/payments/columns-settings', [], [
                'columns' => ['user_name' => true, 'receipt' => true, 'refund_action' => true],
            ]],
            ['GET', '/admin/reports/payments/total', []],
            ['GET', '/admin/reports/payments/users-search?q=', []],
            ['GET', '/admin/reports/payments/teams-search?q=', []],
        ];

        foreach ($routes as $item) {
            $this->callSectionRoute($item, 'reports.view');
        }
    }

    public function test_abcd_users_section_endpoints_return_200_with_users_and_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->grantPermissionForUser($actor, 'users.view');
        $this->grantPermissionForUser($actor, 'contracts.view');
        $this->actingAs($actor);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Access',
            'lastname'   => 'ABCD users',
        ]);

        $routes = [
            ['GET', route('admin.user1'), ['HTTP_ACCEPT' => 'text/html']],
            ['GET', '/admin/users/data?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest']],
            ['GET', route('admin.users.table-settings.get'), []],
            ['POST', route('admin.users.table-settings.save'), [], [
                'columns' => ['name' => true, 'contract' => true, 'actions' => true],
            ]],
            ['GET', '/admin/users/' . $student->id . '/edit', ['X-Requested-With' => 'XMLHttpRequest']],
            ['GET', route('admin.users.parents.search', ['q' => '']), []],
            ['GET', route('logs.data.user'), []],
        ];

        foreach ($routes as $item) {
            $this->callSectionRoute($item, 'users+contracts.view');
        }
    }

    public function test_abcd_school_leads_section_endpoints_return_200_with_school_leads_view(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermissionForUser($actor, 'schoolLeads.view');
        $this->actingAs($actor);

        $systemStatusId = $this->schoolLeadSystemStatusId();
        $processingStatusId = $this->schoolLeadProcessingStatusId();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Access ABCD',
            'phone'                 => '+7 900 400-00-04',
            'school_lead_status_id' => $systemStatusId,
        ]);
        $deleteLead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Delete ABCD',
            'phone'                 => '+7 900 400-00-05',
            'school_lead_status_id' => $systemStatusId,
        ]);

        $routes = [
            ['GET', route('admin.school-leads'), ['HTTP_ACCEPT' => 'text/html']],
            ['GET', route('admin.school-leads.data', [
                'draw'       => 1,
                'start'      => 0,
                'length'     => 10,
                'status_ids' => [$systemStatusId],
            ]), []],
            ['GET', route('admin.school-leads.statuses.index'), []],
            ['POST', route('admin.school-leads.statuses.store'), [], [
                'name'  => 'ABCD статус',
                'color' => '#ff0000',
            ]],
            ['GET', route('admin.school-leads.columns-settings.get'), []],
            ['POST', route('admin.school-leads.columns-settings.save'), [], [
                'columns' => ['name' => true, 'status' => true, 'contract' => true, 'actions' => true],
            ]],
            ['PUT', route('admin.school-leads.update', ['schoolLead' => $lead->id]), [], [
                'school_lead_status_id' => $processingStatusId,
                'comment'               => 'ABCD access',
            ]],
            ['DELETE', route('admin.school-leads.destroy', ['schoolLead' => $deleteLead->id]), []],
        ];

        foreach ($routes as $item) {
            $this->callSectionRoute($item, 'schoolLeads.view');
        }
    }

    public function test_abcd_partner_leads_section_endpoints_return_200_with_partner_leads_view(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPermissionForUser($actor, 'partnerLeads.view');
        $this->actingAs($actor);

        $lead = PartnerLead::create([
            'name'   => 'Access partner ABCD',
            'phone'  => '+7 900 500-00-06',
            'status' => 'new',
        ]);
        $deleteLead = PartnerLead::create([
            'name'   => 'Delete partner ABCD',
            'phone'  => '+7 900 500-00-07',
            'status' => 'new',
        ]);

        $routes = [
            ['GET', route('admin.partner-leads'), ['HTTP_ACCEPT' => 'text/html']],
            ['GET', route('admin.partner-leads.data', [
                'draw'     => 1,
                'start'    => 0,
                'length'   => 10,
                'statuses' => ['new'],
            ]), []],
            ['GET', route('admin.partner-leads.columns-settings.get'), []],
            ['POST', route('admin.partner-leads.columns-settings.save'), [], [
                'columns' => ['name' => true, 'status' => true, 'actions' => true],
            ]],
            ['PUT', route('admin.partner-leads.update', ['partnerLead' => $lead->id]), [], [
                'status'  => 'processing',
                'comment' => 'ABCD partner access',
            ]],
            ['DELETE', route('admin.partner-leads.destroy', ['partnerLead' => $deleteLead->id]), []],
        ];

        foreach ($routes as $item) {
            $this->callSectionRoute($item, 'partnerLeads.view');
        }
    }

    public function test_abcd_teams_section_endpoints_return_200_with_groups_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantPermissionForUser($actor, 'groups.view');
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'ABCD teams access',
            'month_price' => 2500,
        ]);

        $routes = [
            ['GET', route('admin.team.index'), ['HTTP_ACCEPT' => 'text/html']],
            ['GET', '/admin/teams/data?draw=1&start=0&length=10&title=ABCD teams', ['X-Requested-With' => 'XMLHttpRequest']],
            ['GET', '/admin/teams/columns-settings', []],
            ['POST', '/admin/teams/columns-settings', [], [
                'columns' => ['title' => true, 'month_price' => true, 'actions' => true],
            ]],
            ['GET', route('admin.team.edit', ['id' => $team->id]), ['X-Requested-With' => 'XMLHttpRequest']],
            ['GET', route('logs.data.team'), []],
        ];

        foreach ($routes as $item) {
            $this->callSectionRoute($item, 'groups.view');
        }
    }

    public function test_abcd_affected_pages_forbidden_without_required_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('payments'))->assertForbidden();
        $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'))
            ->assertForbidden();

        $actorUsers = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actorUsers);
        $this->get(route('admin.user1'))->assertForbidden();

        $actorLeads = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actorLeads);
        $this->get(route('admin.school-leads'))->assertForbidden();

        $actorPartnerLeads = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->actingAs($actorPartnerLeads);
        $this->get(route('admin.partner-leads'))->assertForbidden();

        $actorTeams = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actorTeams);
        $this->get(route('admin.team.index'))->assertForbidden();
    }

    /**
     * @param array{0: string, 1: string, 2?: array<string, string>, 3?: array<string, mixed>, 4?: string} $item
     */
    private function callSectionRoute(array $item, string $scopeLabel): void
    {
        $method = $item[0];
        $url = $item[1];
        $headers = $item[2] ?? [];
        $data = $item[3] ?? [];
        $label = $item[4] ?? "{$scopeLabel}: {$method} {$url}";

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $path .= '?' . $query;
        }

        $response = $this->call($method, $path, $data, [], [], array_merge(
            ['HTTP_ACCEPT' => 'application/json'],
            $headers
        ));

        $this->assertSame(
            200,
            $response->getStatusCode(),
            $label . ' → ' . $response->getStatusCode()
        );
    }

    private function grantPermissionForCurrentUser(string $permissionName): void
    {
        $this->grantPermissionForUser($this->user, $permissionName);
    }

    private function grantPermissionForUser(User $user, string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id'    => $this->partner->id,
                'role_id'       => $user->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
