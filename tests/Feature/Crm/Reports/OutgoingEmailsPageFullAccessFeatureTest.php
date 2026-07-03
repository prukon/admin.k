<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\OutgoingEmailLog;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Отчёты → Исходящие письма» (/admin/reports/emails):
 * контроль доступа (guest / без права / с правом / superadmin) и smoke 200 для endpoints.
 */
final class OutgoingEmailsPageFullAccessFeatureTest extends CrmTestCase
{
    private OutgoingEmailLog $ownLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->ownLog = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status'     => OutgoingEmailLog::STATUS_SENT,
            'subject'    => 'full-access-smoke',
            'to_summary' => 'parent@example.com',
            'html_body'  => '<p>Smoke HTML</p>',
            'created_at' => now()->subHour(),
            'sent_at'    => now()->subMinutes(30),
        ]);
    }

    public function test_guest_cannot_access_any_outgoing_emails_endpoint(): void
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

    public function test_user_without_reports_emails_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('reports.emails.view', $this->partner);
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
                "Без reports.emails.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_reports_emails_view_all_section_endpoints_return_expected_status(): void
    {
        $actor = $this->createUserWithoutPermission('reports.emails.view', $this->partner);
        $this->grantReportsEmailsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_superadmin_all_section_endpoints_return_expected_status(): void
    {
        $this->asSuperadmin();

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_authorized_user_filter_param_variants_return_200(): void
    {
        $this->asSuperadmin();

        foreach ($this->allFilterParamVariants() as $params) {
            $this->get(route('reports.emails.index', $params))
                ->assertOk()
                ->assertViewHas('activeTab', 'emails');

            $this->get(route('reports.emails.total', $params))->assertOk();

            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('reports.emails.data', array_merge($this->baseDataTableParams(), $params)))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_data_endpoint_without_ajax_returns_404_not_empty_200(): void
    {
        $this->asSuperadmin();

        $this->get(route('reports.emails.data', $this->baseDataTableParams()))
            ->assertNotFound();
    }

    public function test_data_json_includes_show_url_and_datetime_fields_for_modal_and_columns(): void
    {
        $this->asSuperadmin();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.emails.data', $this->baseDataTableParams()))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $this->ownLog->id);
        $this->assertIsArray($row);

        $this->assertSame(
            route('reports.emails.show', ['log' => $this->ownLog->id]),
            $row['show_url'] ?? null
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) ($row['created_at'] ?? '')
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) ($row['sent_at'] ?? '')
        );
    }

    public function test_show_modal_ajax_returns_html_partial_for_list_modal(): void
    {
        $this->asSuperadmin();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.emails.show', ['log' => $this->ownLog->id, 'modal' => 1]))
            ->assertOk()
            ->assertSee('outgoing-email-show-content', false)
            ->assertSee('emailBodyTabsModal', false)
            ->assertSee('data:text/html;charset=utf-8;base64,' . base64_encode('<p>Smoke HTML</p>'), false)
            ->assertDontSee('Назад к списку', false);
    }

    public function test_show_full_page_renders_html_in_iframe_not_escaped_source(): void
    {
        $this->asSuperadmin();

        $htmlBody = '<!DOCTYPE html><html><body><strong>Full page</strong></body></html>';
        $log = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status'     => OutgoingEmailLog::STATUS_SENT,
            'subject'    => 'Full page HTML',
            'html_body'  => $htmlBody,
        ]);

        $this->get(route('reports.emails.show', ['log' => $log->id]))
            ->assertOk()
            ->assertViewIs('admin.report.outgoing_email_show')
            ->assertSee('data:text/html;charset=utf-8;base64,' . base64_encode($htmlBody), false)
            ->assertDontSee('&lt;!DOCTYPE html&gt;', false);
    }

    public function test_show_forbids_foreign_partner_log(): void
    {
        $this->asSuperadmin();

        $foreignLog = OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status'     => OutgoingEmailLog::STATUS_SENT,
            'subject'    => 'Foreign',
        ]);

        $this->get(route('reports.emails.show', ['log' => $foreignLog->id]))
            ->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->get(route('reports.emails.show', ['log' => $foreignLog->id, 'modal' => 1]))
            ->assertForbidden();
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(): void
    {
        $this->get(route('reports.emails.index'))
            ->assertOk()
            ->assertViewIs('admin.report.index')
            ->assertViewHas('activeTab', 'emails')
            ->assertSee('id="emails-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="outgoingEmailShowModal"', false)
            ->assertSee('js-outgoing-email-show', false)
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
        $this->assertMatchesRegularExpression(
            "/key:\s*'to_summary'[\s\S]{0,120}?type:\s*'link'/",
            $html
        );

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('reports.emails.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('reports.emails.total'))->assertOk();

        $this->get(route('reports.emails.mailable.classes.search', ['q' => '']))->assertOk();

        $this->get('/admin/reports/emails/columns-settings')->assertOk();

        $this->postJson('/admin/reports/emails/columns-settings', [
            'columns' => [
                'id'        => true,
                'subject'   => false,
                'to_summary'=> true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', Auth::id())
            ->where('table_key', 'reports_outgoing_emails')
            ->first();
        $this->assertNotNull($setting);

        $this->get(route('reports.emails.show', ['log' => $this->ownLog->id]))
            ->assertOk()
            ->assertViewHas('log');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allSectionRoutesPayload(): array
    {
        $logId = $this->ownLog->id;

        return [
            [
                'method'  => 'GET',
                'url'     => route('reports.emails.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.emails.data', $this->baseDataTableParams()),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.emails.total'),
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.emails.mailable.classes.search', ['q' => '']),
            ],
            [
                'method'  => 'GET',
                'url'     => '/admin/reports/emails/columns-settings',
            ],
            [
                'method'  => 'POST',
                'url'     => '/admin/reports/emails/columns-settings',
                'data'    => ['columns' => ['id' => true, 'status' => true]],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.emails.show', ['log' => $logId]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.emails.show', ['log' => $logId, 'modal' => 1]),
                'headers' => ['HTTP_ACCEPT' => 'text/html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('reports.emails.index', [
                    'status'          => ['sent'],
                    'created_at_from' => now()->subMonth()->toDateString(),
                    'q'               => 'parent@',
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
            ['created_at_from' => now()->subWeek()->toDateString()],
            ['created_at_to' => now()->toDateString()],
            ['sent_at_from' => now()->subWeek()->toDateString()],
            ['sent_at_to' => now()->toDateString()],
            ['status' => ['sent']],
            ['status' => ['failed']],
            ['mailable_class' => 'App\\Mail\\ClientWelcomeCredentialsMail'],
            ['q' => 'parent@'],
            [
                'created_at_from' => now()->subMonth()->toDateString(),
                'sent_at_to'      => now()->toDateString(),
                'status'          => ['sent'],
                'q'               => 'full-access',
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

    private function grantReportsEmailsView(int $roleId): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id'    => $this->partner->id,
                'role_id'       => $roleId,
                'permission_id' => $this->permissionId('reports.emails.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
