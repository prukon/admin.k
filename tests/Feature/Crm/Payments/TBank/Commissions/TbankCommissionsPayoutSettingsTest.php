<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\Setting;
use App\Models\TinkoffCommissionRule;
use Tests\Feature\Crm\CrmTestCase;

class TbankCommissionsPayoutSettingsTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
    }

    public function test_index_displays_payout_settings_block_with_current_values(): void
    {
        Setting::setTinkoffPayoutAutoDelayHours(24);
        Setting::setTinkoffPayoutScheduledIntervalMinutes(15);

        $resp = $this->get(route('admin.setting.tbankCommissions'));

        $resp->assertOk();
        $resp->assertSee('Глобальные настройки выплат Т‑Банк');
        $resp->assertSee('name="payout_auto_delay_hours"', false);
        $resp->assertSee('name="payout_scheduled_interval_minutes"', false);
        $resp->assertSee('value="24"', false);
        $resp->assertSee('value="15"', false);
    }

    public function test_updatePayoutSettings_validates_delay_hours_range(): void
    {
        $resp = $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_auto_delay_hours' => 1000,
            'payout_scheduled_interval_minutes' => 10,
            '_token' => csrf_token(),
        ]);

        $resp->assertSessionHasErrors('payout_auto_delay_hours');
    }

    public function test_updatePayoutSettings_validates_interval_minutes_range(): void
    {
        $resp = $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_auto_delay_hours' => 48,
            'payout_scheduled_interval_minutes' => 0,
            '_token' => csrf_token(),
        ]);

        $resp->assertSessionHasErrors('payout_scheduled_interval_minutes');
    }

    public function test_updatePayoutSettings_saves_to_settings_table_and_redirects(): void
    {
        $resp = $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_auto_delay_hours' => 24,
            'payout_scheduled_interval_minutes' => 15,
            '_token' => csrf_token(),
        ]);

        $resp->assertRedirect(route('admin.setting.tbankCommissions'));
        $resp->assertSessionHas('status');

        $this->assertSame(24, Setting::getTinkoffPayoutAutoDelayHours());
        $this->assertSame(15, Setting::getTinkoffPayoutScheduledIntervalMinutes());
    }

    public function test_updatePayoutSettings_requires_settings_commission_permission(): void
    {
        $this->asAdmin();
        $resp = $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_auto_delay_hours' => 48,
            'payout_scheduled_interval_minutes' => 10,
            '_token' => csrf_token(),
        ]);

        $resp->assertStatus(403);
    }
}
