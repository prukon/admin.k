<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\PaymentSystem;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankPayoutsIndexAutoInfoTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);
        $permId = $this->permissionId('tbank.payouts.manage');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_displays_auto_payout_status_when_partner_has_it_enabled(): void
    {
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => ['auto_payout_enabled' => true],
        ]);

        $resp = $this->get('/admin/tinkoff/payouts');

        $resp->assertOk();
        $resp->assertSee('Автовыплаты');
        $resp->assertSee('включены');
    }

    public function test_index_displays_scheduled_interval_from_setting(): void
    {
        Setting::setTinkoffPayoutScheduledIntervalMinutes(15);

        $resp = $this->get('/admin/tinkoff/payouts');

        $resp->assertOk();
        $resp->assertSee('каждые 15 мин');
    }

    public function test_index_displays_nobody_when_no_partner_has_auto_payout(): void
    {
        PaymentSystem::query()->where('name', 'tbank')->delete();
        $resp = $this->get('/admin/tinkoff/payouts');

        $resp->assertOk();
        $resp->assertSee('ни у кого не включены');
    }
}
