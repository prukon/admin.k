<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Покрытие UI и API списка комиссий Т‑Банк (DataTables, колонки, модалки, фильтры).
 */
class TbankCommissionsFeatureCoverageTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
    }

    public function test_list_page_includes_datatable_modals_and_header_buttons(): void
    {
        $resp = $this->get(route('admin.setting.tbankCommissions'));

        $resp->assertOk();
        $resp->assertSee('id="tbank-commissions-table"', false);
        $resp->assertSee('id="tbankPayoutSettingsModal"', false);
        $resp->assertSee('id="tbankCommissionCreateModal"', false);
        $resp->assertSee('Настройки выплат', false);
        $resp->assertSee('Добавить комиссию', false);
        $resp->assertSee('Правила комиссий и выплат', false);
    }

    public function test_data_endpoint_returns_json_with_all_datatable_row_keys(): void
    {
        TinkoffCommissionRule::create($this->rulePayload());

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $resp->assertOk();
        $resp->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                [
                    'partner_cell',
                    'method',
                    'acquiring_html',
                    'payout_html',
                    'platform_html',
                    'auto_payout_html',
                    'payouts_30d_html',
                    'enabled_html',
                    'actions_html',
                ],
            ],
        ]);

        $row = $resp->json('data.0');
        $this->assertStringContainsString('Изменить', $row['actions_html']);
    }

    public function test_data_respects_filter_partner_id(): void
    {
        TinkoffCommissionRule::create($this->rulePayload(['partner_id' => $this->partner->id, 'method' => 'card']));
        TinkoffCommissionRule::create($this->rulePayload(['partner_id' => $this->foreignPartner->id, 'method' => 'sbp']));

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'filter_partner_id' => $this->partner->id,
        ]));

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertStringContainsString('card', (string) $resp->json('data.0.method'));
    }

    public function test_data_respects_filter_method(): void
    {
        TinkoffCommissionRule::create($this->rulePayload(['partner_id' => $this->partner->id, 'method' => 'card']));
        TinkoffCommissionRule::create($this->rulePayload(['partner_id' => $this->partner->id, 'method' => 'sbp']));

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'filter_partner_id' => $this->partner->id,
            'filter_method' => 'sbp',
        ]));

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('sbp', $resp->json('data.0.method'));
    }

    public function test_data_global_rule_shows_dash_in_auto_payout_and_payouts_30d_columns(): void
    {
        TinkoffCommissionRule::create($this->rulePayload(['partner_id' => null, 'method' => null]));

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
        ]));

        $resp->assertOk();
        $row = $resp->json('data.0');
        $this->assertSame('—', $row['auto_payout_html']);
        $this->assertSame('—', $row['payouts_30d_html']);
    }

    public function test_data_search_by_method_value_returns_row(): void
    {
        TinkoffCommissionRule::create($this->rulePayload(['method' => 'sbp']));

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'search' => ['value' => 'sbp', 'regex' => false],
        ]));

        $resp->assertOk();
        $this->assertGreaterThanOrEqual(1, $resp->json('recordsFiltered'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 1.2,
            'payout_min_fixed' => 0,
            'platform_percent' => 3.0,
            'platform_min_fixed' => 0,
            'min_fixed' => 0,
            'is_enabled' => true,
        ], $overrides);
    }
}
