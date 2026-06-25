<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\PaymentSystem;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayout;
use Tests\Feature\Crm\CrmTestCase;

class TbankCommissionsAutoPayoutStatsTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
    }

    public function test_index_displays_auto_payout_30d_stats_per_rule_with_partner(): void
    {
        $rule = TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.1,
            'payout_min_fixed' => 0,
            'platform_percent' => 2,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]);

        $this->seedGlobalTbank();

        TinkoffPayout::create([
            'payment_id' => 1,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-stats',
            'amount' => 5000,
            'status' => 'COMPLETED',
            'source' => 'auto',
            'created_at' => now()->subDays(5),
        ]);

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'filter_partner_id' => $this->partner->id,
        ]));

        $resp->assertOk();
        $row = $resp->json('data.0');
        $this->assertSame(1, $row['payouts_30d_count']);
        $this->assertStringContainsString('source=auto', (string) $row['payouts_30d_url']);
        $this->assertStringContainsString('partner_id='.$this->partner->id, (string) $row['payouts_30d_url']);
    }

    public function test_index_displays_zero_auto_payouts_when_none_for_partner(): void
    {
        TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.1,
            'payout_min_fixed' => 0,
            'platform_percent' => 2,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]);

        $this->seedGlobalTbank();

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'filter_partner_id' => $this->partner->id,
        ]));

        $resp->assertOk();
        $row = $resp->json('data.0');
        $this->assertSame(0, $row['payouts_30d_count']);
        $this->assertStringContainsString('source=auto', (string) $row['payouts_30d_url']);
        $this->assertStringContainsString('partner_id='.$this->partner->id, (string) $row['payouts_30d_url']);
    }

    public function test_edit_displays_auto_payout_30d_stats_for_rule_partner(): void
    {
        $rule = TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.1,
            'payout_min_fixed' => 0,
            'platform_percent' => 2,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]);

        $this->seedGlobalTbank();

        TinkoffPayout::create([
            'payment_id' => 2,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-edit',
            'amount' => 3000,
            'status' => 'COMPLETED',
            'source' => 'auto',
            'created_at' => now()->subDays(2),
        ]);

        $resp = $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]));

        $resp->assertOk();
        $resp->assertSee('За 30 дн.: 1 автовыплат');
    }
}
