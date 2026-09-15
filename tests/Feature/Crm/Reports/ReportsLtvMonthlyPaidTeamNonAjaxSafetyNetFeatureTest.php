<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX GET data/детализации LTV и monthly → 404, не пустой 200 и не 500.
 * Columns-settings: AJAX 200/422, native POST JSON или 302 с errors по полям.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ReportsLtvMonthlyPaidTeamNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_monthly_and_ltv_data_without_ajax_header_return_404_not_empty_200(): void
    {
        $dt = ['draw' => 1, 'start' => 0, 'length' => 10];

        $this->get(route('reports.payments.monthly.data', $dt + ['mode' => 'subscription']))
            ->assertNotFound();
        $this->get(route('reports.ltv.data', $dt))
            ->assertNotFound();
        $this->get(route('reports.payments.monthly.payments', $dt + [
            'yearMonth' => '2026-08',
            'mode' => 'subscription',
        ]))->assertNotFound();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $this->get(route('reports.ltv.user_payments', $dt + ['user' => $student->id]))
            ->assertNotFound();
    }

    public function test_ajax_data_returns_datatables_json_not_empty_200(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'summ_cents' => 50000,
            'payment_month' => '2026-08-01',
        ]);

        $dt = ['draw' => 1, 'start' => 0, 'length' => 10];

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.payments.monthly.data', $dt + ['mode' => 'subscription']))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.data', $dt))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $monthlyNested = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.payments.monthly.payments', $dt + [
                'yearMonth' => '2026-08',
                'mode' => 'subscription',
            ]))
            ->assertOk();
        $this->assertNotSame('', trim((string) $monthlyNested->getContent()));
        $monthlyNested->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.user_payments', $dt + ['user' => $student->id]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_columns_settings_non_ajax_post_saves_and_invalid_redirects_with_field_error(): void
    {
        $payload = ['columns' => ['team_title' => true, 'user_name' => false]];

        $this->post(route('reports.ltv.columns-settings.save'), $payload, [
            'HTTP_ACCEPT' => 'text/html',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->from(route('reports.ltv'))
            ->post(route('reports.ltv.columns-settings.save'), [], [
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);

        $this->from(route('reports.payments.monthly'))
            ->post(route('reports.payments.monthly.columns-settings.save'), ['page_length' => 7], [
                'HTTP_ACCEPT' => 'text/html',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['page_length']);
    }

    public function test_guest_non_ajax_data_does_not_return_500(): void
    {
        Auth::logout();

        $response = $this->get(route('reports.ltv.data', ['draw' => 1]));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403, 404]);
    }
}
