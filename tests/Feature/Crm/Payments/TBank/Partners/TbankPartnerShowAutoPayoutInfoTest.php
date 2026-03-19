<?php

namespace Tests\Feature\Crm\Payments\TBank\Partners;

use App\Models\PaymentSystem;
use App\Models\Setting;
use App\Models\TinkoffPayout;
use Tests\Feature\Crm\CrmTestCase;

class TbankPartnerShowAutoPayoutInfoTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    public function test_show_displays_auto_payout_enabled_when_payment_system_has_flag(): void
    {
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => ['auto_payout_enabled' => true],
        ]);

        $resp = $this->get('/admin/tinkoff/partners/' . $this->partner->id);

        $resp->assertOk();
        $resp->assertSee('Автовыплата');
        $resp->assertSee('вкл');
    }

    public function test_show_displays_auto_payout_disabled_when_no_flag(): void
    {
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => ['auto_payout_enabled' => false],
        ]);

        $resp = $this->get('/admin/tinkoff/partners/' . $this->partner->id);

        $resp->assertOk();
        $resp->assertSee('выкл');
    }

    public function test_show_displays_scheduled_interval_from_setting(): void
    {
        Setting::setTinkoffPayoutScheduledIntervalMinutes(15);

        $resp = $this->get('/admin/tinkoff/partners/' . $this->partner->id);

        $resp->assertOk();
        $resp->assertSee('каждые 15 мин');
    }

    public function test_show_displays_auto_payout_count_and_last_at_for_last_30_days(): void
    {
        TinkoffPayout::create([
            'payment_id' => 1,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-1',
            'amount' => 1000,
            'status' => 'COMPLETED',
            'source' => 'auto',
            'created_at' => now()->subDays(5),
        ]);
        TinkoffPayout::create([
            'payment_id' => 2,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-2',
            'amount' => 2000,
            'status' => 'COMPLETED',
            'source' => 'auto',
            'created_at' => now()->subDays(1),
        ]);

        $resp = $this->get('/admin/tinkoff/partners/' . $this->partner->id);

        $resp->assertOk();
        $resp->assertSee('За 30 дн.: 2 автовыплат');
    }

    public function test_show_displays_zero_auto_payouts_when_none_in_30_days(): void
    {
        $resp = $this->get('/admin/tinkoff/partners/' . $this->partner->id);

        $resp->assertOk();
        $resp->assertSee('За 30 дн.: 0 автовыплат');
    }
}
