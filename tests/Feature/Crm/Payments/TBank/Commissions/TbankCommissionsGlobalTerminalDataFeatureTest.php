<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use Tests\Feature\Crm\CrmTestCase;

/**
 * DataTables и index: глобальный терминал и колонки автовыплаты.
 */
final class TbankCommissionsGlobalTerminalDataFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
    }

    public function test_index_exposes_tbank_globally_connected_flag(): void
    {
        $this->seedGlobalTbank();

        $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewHas('tbankGloballyConnected', true);
    }

    public function test_index_shows_tbank_globally_disconnected_without_global_terminal(): void
    {
        \App\Models\PaymentSystem::query()->whereNull('partner_id')->where('name', 'tbank')->delete();

        $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewHas('tbankGloballyConnected', false);
    }

    public function test_data_includes_auto_payout_label_and_global_keys_flag(): void
    {
        $this->seedGlobalTbank();

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

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_partner_id' => $this->partner->id,
        ]));

        $resp->assertOk();
        $row = $resp->json('data.0');

        $this->assertTrue($row['tbank_keys_connected']);
        $this->assertTrue($row['auto_payout_enabled']);
        $this->assertSame(48, $row['auto_payout_delay_hours']);
        $this->assertSame('да, 48 ч', $row['auto_payout_label']);
    }

    public function test_data_shows_keys_disconnected_when_global_terminal_inactive(): void
    {
        $this->seedGlobalTbank([], ['is_enabled' => false]);

        TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => 'sbp',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.1,
            'payout_min_fixed' => 0,
            'platform_percent' => 2,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]);

        $resp = $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_partner_id' => $this->partner->id,
        ]));

        $resp->assertOk();
        $row = $resp->json('data.0');

        $this->assertFalse($row['tbank_keys_connected']);
        $this->assertSame('нет', $row['auto_payout_label']);
    }
}
