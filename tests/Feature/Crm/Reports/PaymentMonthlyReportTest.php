<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use Tests\Feature\Crm\CrmTestCase;

class PaymentMonthlyReportTest extends CrmTestCase
{
    /**
     * [P0] Контроль доступа по праву can:reports.view (страница, data, total).
     */
    public function test_payment_monthly_routes_require_reports_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payments.monthly'))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payments.monthly.data', ['draw' => 1]))
            ->assertForbidden();

        $this->get(route('reports.payments.monthly.total'))->assertForbidden();
    }

    /**
     * [P0] /admin/reports/payments/monthly/total отдаёт сумму payments.summ по фильтрам.
     */
    public function test_payment_monthly_total_endpoint_returns_correct_sum(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 1000,
            'payment_month' => '2025-01-01',
        ]);
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ' => 2000,
            'payment_month' => '2025-02-01',
        ]);
        Payment::factory()->create([
            'user_id' => $this->foreignUser->id,
            'summ' => 9999,
            'payment_month' => '2025-01-01',
        ]);

        $expectedRaw = 3000.0;
        $expectedFormatted = number_format($expectedRaw, 0, '', ' ');

        $this->get(route('reports.payments.monthly.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => $expectedFormatted,
                'total_raw' => $expectedRaw,
            ]);
    }
}

