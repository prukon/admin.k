<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\PaymentSystem;
use App\Models\Setting;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI вкладки «Выплаты T‑Bank» в разделе партнёров: toolbar, фильтры, DataTables, колонки.
 */
final class TbankPayoutsPageFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->grantPayoutsManage((int) $this->user->role_id);
    }

    public function test_index_passes_toolbar_totals_and_renders_payouts_ui(): void
    {
        TinkoffPayout::query()->create([
            'payment_id'                => null,
            'partner_id'                => $this->partner->id,
            'deal_id'                   => 'ui-totals-' . uniqid(),
            'amount'                    => 10000,
            'net_amount'                => 9000,
            'is_final'                  => false,
            'status'                    => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run'               => null,
            'completed_at'              => null,
        ]);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'payouts')
            ->assertViewHas('toolbarTotals')
            ->assertSee('partnersSectionTabs', false)
            ->assertSee('role="tab">Выплаты T‑Bank</a>', false)
            ->assertSee('id="tbankPayoutsToolbarTotals"', false)
            ->assertSee('id="tbankPayoutsPaymentsStat"', false)
            ->assertSee('id="tbankPayoutsPayoutsStat"', false)
            ->assertSee('id="tbankPayoutsPlatformStat"', false)
            ->assertSee('id="tbankPayoutsFiltersToggle"', false)
            ->assertSee('id="tbankPayoutsFiltersCollapse"', false)
            ->assertSee('id="tbank-payouts-filters"', false)
            ->assertSee('id="filter-status"', false)
            ->assertSee('id="filter-source"', false)
            ->assertSee('id="columnsDropdown"', false)
            ->assertSee('id="payouts-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('/admin/tinkoff/payouts/data', false)
            ->assertSee('/admin/tinkoff/payouts/total', false);
    }

    public function test_index_includes_partners_section_shell_title(): void
    {
        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertSee('>Партнеры</h4>', false);
    }

    public function test_datatable_returns_expected_json_structure(): void
    {
        TinkoffPayout::query()->create([
            'payment_id'                => null,
            'partner_id'                => $this->partner->id,
            'deal_id'                   => 'ui-dt-' . uniqid(),
            'amount'                    => 200,
            'is_final'                  => false,
            'status'                    => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run'               => null,
            'completed_at'              => null,
        ]);

        $this->getJson('/admin/tinkoff/payouts/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);
    }

    public function test_total_endpoint_returns_formatted_and_raw_amounts(): void
    {
        $this->getJson('/admin/tinkoff/payouts/total')
            ->assertOk()
            ->assertJsonStructure([
                'payments_total_formatted',
                'payments_total_raw',
                'payouts_total_formatted',
                'payouts_total_raw',
                'platform_fee_total_formatted',
                'platform_fee_total_raw',
            ]);
    }

    public function test_columns_settings_can_be_read_and_saved(): void
    {
        $this->getJson('/admin/tinkoff/payouts/columns-settings')
            ->assertOk();

        $this->postJson('/admin/tinkoff/payouts/columns-settings', [
            'columns' => [
                'status'  => true,
                'partner' => false,
                'net'     => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_payers_search_returns_select2_results(): void
    {
        $this->getJson('/admin/tinkoff/payouts/payers-search?q=')
            ->assertOk()
            ->assertJsonStructure(['results']);
    }

    public function test_index_shows_auto_payout_info_when_enabled(): void
    {
        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 24,
        ]);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertSee('Автовыплаты', false)
            ->assertSee('включены', false);
    }

    public function test_index_shows_scheduled_interval_from_setting(): void
    {
        Setting::setTinkoffPayoutScheduledIntervalMinutes(20);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertSee('каждые 20 мин', false);
    }

    public function test_index_shows_overdue_scheduled_block(): void
    {
        TinkoffPayout::query()->create([
            'payment_id'                => null,
            'partner_id'                => $this->partner->id,
            'deal_id'                   => 'ui-overdue-' . uniqid(),
            'amount'                    => 300,
            'is_final'                  => false,
            'status'                    => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run'               => now()->subHour(),
            'completed_at'              => null,
        ]);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertSee('Просроченные отложенные выплаты', false)
            ->assertSee('Просроченные отложенные выплаты: 1', false);
    }

    private function grantPayoutsManage(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('tbank.payouts.manage'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
