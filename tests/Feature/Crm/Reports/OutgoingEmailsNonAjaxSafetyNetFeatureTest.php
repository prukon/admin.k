<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\OutgoingEmailLog;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX / AJAX контракты отчёта «Исходящие письма»:
 * columns-settings POST, modal show fragment, validation 422.
 */
final class OutgoingEmailsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    private OutgoingEmailLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asSuperadmin();

        $this->log = OutgoingEmailLog::create([
            'partner_id'   => $this->partner->id,
            'status'       => OutgoingEmailLog::STATUS_SENT,
            'subject'      => 'Non-AJAX safety',
            'to_summary'   => 'safety@example.com',
            'to_addresses' => [['address' => 'safety@example.com', 'name' => '']],
            'html_body'    => '<p>Body</p>',
        ]);
    }

    public function test_columns_settings_non_ajax_post_saves_and_returns_json_success_not_empty_200(): void
    {
        $payload = [
            'columns' => [
                'id'      => true,
                'subject' => false,
                'status'  => true,
            ],
        ];

        $response = $this->post('/admin/reports/emails/columns-settings', $payload, [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_outgoing_emails')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);
    }

    public function test_columns_settings_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('reports.emails.index'))
            ->post('/admin/reports/emails/columns-settings', [], [
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);
    }

    public function test_columns_settings_ajax_validation_failure_returns_422_json(): void
    {
        $this->postJson('/admin/reports/emails/columns-settings', [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_columns_settings_ajax_returns_json_contract(): void
    {
        $this->postJson('/admin/reports/emails/columns-settings', [
            'columns' => [
                'to_summary' => true,
                'actions'    => true,
            ],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonStructure(['success'])
            ->assertJson(['success' => true]);
    }

    public function test_show_modal_ajax_contract_returns_html_fragment(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.emails.show', ['log' => $this->log->id, 'modal' => 1]))
            ->assertOk()
            ->assertSee('outgoing-email-show-content', false)
            ->assertSee('safety@example.com', false)
            ->assertSee('data:text/html;charset=utf-8;base64,' . base64_encode('<p>Body</p>'), false);
    }

    public function test_show_full_page_non_ajax_returns_html_layout_not_json(): void
    {
        $this->get(route('reports.emails.show', ['log' => $this->log->id]), [
            'HTTP_ACCEPT' => 'text/html',
        ])
            ->assertOk()
            ->assertViewIs('admin.report.outgoing_email_show')
            ->assertSee('Назад к списку', false);
    }
}
