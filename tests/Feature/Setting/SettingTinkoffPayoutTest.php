<?php

namespace Tests\Feature\Setting;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTinkoffPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tinkoff.payouts.auto_payout_delay_hours' => 48]);
        config(['tinkoff.payouts.scheduled_interval_minutes' => 10]);
    }

    public function test_getInt_returns_default_when_no_row(): void
    {
        $this->assertSame(0, Setting::getInt('unknown_key', 0, null));
        $this->assertSame(99, Setting::getInt('unknown_key', 99, null));
    }

    public function test_getInt_returns_value_from_text_column_when_row_exists(): void
    {
        Setting::query()->create([
            'name' => 'test_int_setting',
            'partner_id' => null,
            'text' => '48',
        ]);

        $this->assertSame(48, Setting::getInt('test_int_setting', 0, null));
    }

    public function test_setInt_creates_row_then_getInt_returns_value(): void
    {
        $this->assertTrue(Setting::setInt('payout_delay_test', 24, null));
        $this->assertSame(24, Setting::getInt('payout_delay_test', 0, null));
    }

    public function test_setInt_updates_existing_row(): void
    {
        Setting::setInt('payout_interval_test', 10, null);
        Setting::setInt('payout_interval_test', 15, null);
        $this->assertSame(15, Setting::getInt('payout_interval_test', 0, null));
    }

    public function test_getTinkoffPayoutAutoDelayHours_returns_config_default_when_no_db_row(): void
    {
        Setting::query()->where('name', 'tinkoff_payout_auto_delay_hours')->delete();
        $this->assertSame(48, Setting::getTinkoffPayoutAutoDelayHours());
    }

    public function test_getTinkoffPayoutAutoDelayHours_returns_db_value_when_row_exists(): void
    {
        Setting::setTinkoffPayoutAutoDelayHours(24);
        $this->assertSame(24, Setting::getTinkoffPayoutAutoDelayHours());
    }

    public function test_setTinkoffPayoutAutoDelayHours_persists_and_get_returns_value(): void
    {
        Setting::setTinkoffPayoutAutoDelayHours(72);
        $this->assertSame(72, Setting::getTinkoffPayoutAutoDelayHours());
    }

    public function test_getTinkoffPayoutScheduledIntervalMinutes_returns_config_default_when_no_db_row(): void
    {
        Setting::query()->where('name', 'tinkoff_payout_scheduled_interval_minutes')->delete();
        $this->assertSame(10, Setting::getTinkoffPayoutScheduledIntervalMinutes());
    }

    public function test_getTinkoffPayoutScheduledIntervalMinutes_returns_db_value_when_row_exists(): void
    {
        Setting::setTinkoffPayoutScheduledIntervalMinutes(15);
        $this->assertSame(15, Setting::getTinkoffPayoutScheduledIntervalMinutes());
    }

    public function test_setTinkoffPayoutScheduledIntervalMinutes_persists_and_get_returns_value(): void
    {
        Setting::setTinkoffPayoutScheduledIntervalMinutes(30);
        $this->assertSame(30, Setting::getTinkoffPayoutScheduledIntervalMinutes());
    }
}
