<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\FiscalReceipt;
use App\Models\Partner;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;

class FiscalReceiptsReportTest extends CrmTestCase
{
    /**
     * [P0] Контроль доступа по праву can:reports.fiscal.receipts.view.
     */
    public function test_fiscal_receipts_routes_require_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.fiscal.receipts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.fiscal-receipts.index'))->assertForbidden();
        $this->get(route('reports.fiscal-receipts.total'))->assertForbidden();
        $this->get(route('reports.fiscal-receipts.partners.search', ['q' => 'x']))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.fiscal-receipts.data', ['draw' => 1]))
            ->assertForbidden();

        $this->get('/admin/reports/fiscal-receipts/columns-settings')->assertForbidden();
        $this->postJson('/admin/reports/fiscal-receipts/columns-settings', ['columns' => ['partner' => true]])->assertForbidden();
    }

    /**
     * [P0] /admin/reports/fiscal-receipts/total отдаёт сумму amount по фильтрам и current_partner.
     */
    public function test_fiscal_receipts_total_endpoint_returns_correct_sum(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'type' => 'income',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'amount' => 2000,
            'type' => 'income',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'amount' => 9999,
            'type' => 'income',
        ]);

        $expectedRaw = 3000.0;
        $expectedFormatted = number_format($expectedRaw, 0, '', ' ');

        $this->get(route('reports.fiscal-receipts.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => $expectedFormatted,
                'total_raw' => $expectedRaw,
            ]);
    }

    /**
     * [P1] columns-settings (reports_fiscal_receipts) сохраняются и читаются.
     */
    public function test_fiscal_receipts_columns_settings_saved_and_loaded(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payload = [
            'columns' => [
                'partner' => true,
                'request_payload' => false,
            ],
        ];

        $this->postJson('/admin/reports/fiscal-receipts/columns-settings', $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_fiscal_receipts')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);

        $this->get('/admin/reports/fiscal-receipts/columns-settings')
            ->assertOk()
            ->assertExactJson($payload['columns']);
    }

    /**
     * [P1] Select2 partners-search возвращает results[].
     */
    public function test_fiscal_receipts_partners_search_returns_results(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $p = Partner::factory()->create(['title' => 'FISCAL Partner']);

        $json = $this->get(route('reports.fiscal-receipts.partners.search', ['q' => 'FISCAL']))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('results', $json);
        $ids = collect($json['results'])->pluck('id')->all();
        $this->assertContains($p->id, $ids);
    }
}

