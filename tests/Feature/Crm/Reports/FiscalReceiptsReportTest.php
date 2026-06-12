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

    /**
     * [P1] DataTables JSON: partner_title, даты Y-m-d H:i:s, поля payload для inline-actions.
     */
    public function test_fiscal_receipts_datatable_returns_expected_fields(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->partner->update(['title' => 'Fiscal Partner Test']);
        $createdAt = now()->setDate(2026, 4, 10)->setTime(11, 30, 0);

        $receipt = FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'amount' => 1500,
            'type' => 'income',
            'status' => 'processed',
            'error_code' => '42',
            'error_message' => 'Test error message',
            'request_payload' => '{"foo":"bar"}',
            'response_payload' => '{"ok":true}',
            'webhook_payload' => 'raw-webhook-body',
            'created_at' => $createdAt,
            'processed_at' => $createdAt->copy()->addHour(),
        ]);

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.fiscal-receipts.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $receipt->id);

        $this->assertIsArray($row);
        $this->assertSame('Fiscal Partner Test', $row['partner_title']);
        $this->assertSame((string) $this->partner->id, (string) $row['partner_id']);
        $this->assertSame('income', $row['type']);
        $this->assertSame('processed', $row['status']);
        $this->assertStringContainsString('foo', (string) $row['request_payload']);
        $this->assertStringContainsString('bar', (string) $row['request_payload']);
        $this->assertStringContainsString('raw-webhook-body', (string) $row['webhook_payload']);
        $this->assertSame('2026-04-10 11:30:00', $row['created_at']);
        $this->assertSame('2026-04-10 12:30:00', $row['processed_at']);
        $this->assertSame('42', (string) $row['error_code']);
        $this->assertSame('Test error message', $row['error_message']);
    }

    /**
     * [P1] Страница: KidsCrmDataTable без type custom, inline-actions и datetime.
     */
    public function test_fiscal_receipts_page_uses_preset_column_types(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.fiscal-receipts.index'))->assertOk()->getContent();

        $this->assertStringContainsString("key: 'status',", $html);
        $this->assertMatchesRegularExpression(
            "/key:\s*'status'[\s\S]{0,120}?type:\s*'badge'/",
            $html
        );
        $this->assertMatchesRegularExpression(
            "/key:\s*'request_payload'[\s\S]{0,160}?type:\s*'inline-actions'/",
            $html
        );
        $this->assertMatchesRegularExpression(
            "/key:\s*'created_at'[\s\S]{0,80}?type:\s*'datetime'/",
            $html
        );
        $this->assertStringNotContainsString("type: 'custom'", $html);
    }
}

