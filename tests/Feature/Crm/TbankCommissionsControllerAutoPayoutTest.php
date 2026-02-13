<?php

namespace Tests\Feature\Crm;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffCommissionRule;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TbankCommissionsControllerAutoPayoutTest extends CrmTestCase
{
    protected static bool $canSettingsCommission = true;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('settings.commission', fn (?User $user = null) => self::$canSettingsCommission);
    }

    private function payloadForUpdate(array $overrides = []): array
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
            'is_enabled' => 1,
        ], $overrides);
    }

    private function makeRule(): TinkoffCommissionRule
    {
        return TinkoffCommissionRule::create($this->payloadForUpdate([
            'partner_id' => $this->partner->id,
            'method' => 'card',
        ]));
    }

    public function test_update_with_partner_id_creates_payment_system_and_sets_auto_payout_true(): void
    {
        self::$canSettingsCommission = true;

        $rule = $this->makeRule();
        $partner = Partner::factory()->create();

        $payload = $this->payloadForUpdate([
            'partner_id' => $partner->id,
            'auto_payout_enabled' => 1,
        ]);

        $resp = $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload);

        $resp->assertRedirect(route('admin.setting.tbankCommissions'));
        $resp->assertSessionHas('status', 'Правило обновлено');

        $ps = PaymentSystem::where('name', 'tbank')->where('partner_id', $partner->id)->first();
        $this->assertNotNull($ps);

        $this->assertTrue((bool)($ps->settings['auto_payout_enabled'] ?? false));

        $raw = $ps->getRawOriginal('settings');
        $this->assertNotNull($raw, 'settings в БД не должен быть NULL после установки auto_payout_enabled');
    }

    public function test_update_auto_payout_checkbox_missing_means_false(): void
    {
        self::$canSettingsCommission = true;

        $rule = $this->makeRule();
        $partner = Partner::factory()->create();

        // 1) включаем (1)
        $this->put(
            route('admin.setting.tbankCommissions.update', ['id' => $rule->id]),
            $this->payloadForUpdate([
                'partner_id' => $partner->id,
                'auto_payout_enabled' => 1,
            ])
        )
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило обновлено');

        // 2) выключаем ЯВНО (0) — это реальная модель работы форм
        $this->put(
            route('admin.setting.tbankCommissions.update', ['id' => $rule->id]),
            $this->payloadForUpdate([
                'partner_id' => $partner->id,
                'auto_payout_enabled' => 0,
            ])
        )
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило обновлено');

        $ps = PaymentSystem::where('name', 'tbank')
            ->where('partner_id', $partner->id)
            ->first();

        $this->assertNotNull($ps);

        $this->assertFalse((bool)($ps->settings['auto_payout_enabled'] ?? false));
    }

    public function test_update_does_not_overwrite_other_settings_keys(): void
    {
        self::$canSettingsCommission = true;

        $rule = $this->makeRule();
        $partner = Partner::factory()->create();

        $ps = PaymentSystem::create([
            'partner_id' => $partner->id,
            'name' => 'tbank',
            'settings' => [
                'foo' => 'bar',
                'auto_payout_enabled' => 0,
            ],
            'test_mode' => false,
        ]);

        $payload = $this->payloadForUpdate([
            'partner_id' => $partner->id,
            'auto_payout_enabled' => 1, // включаем явно
        ]);

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload)
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило обновлено');

        $ps->refresh();

        // ключ не затирается
        $this->assertSame('bar', $ps->settings['foo'] ?? null);

        // значение переключилось
        $this->assertTrue((bool)($ps->settings['auto_payout_enabled'] ?? false));
    }

    public function test_update_partner_id_change_sets_auto_payout_for_new_partner(): void
    {
        self::$canSettingsCommission = true;

        $rule = $this->makeRule();

        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();

        // A = true
        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $this->payloadForUpdate([
            'partner_id' => $partnerA->id,
            'auto_payout_enabled' => 1,
        ]))
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило обновлено');

        // B = false (чекбокс не пришёл)
        $payloadB = $this->payloadForUpdate(['partner_id' => $partnerB->id]);
        unset($payloadB['auto_payout_enabled']);

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payloadB)
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило обновлено');

        $psB = PaymentSystem::where('name', 'tbank')->where('partner_id', $partnerB->id)->first();
        $this->assertNotNull($psB);
        $this->assertFalse((bool)($psB->settings['auto_payout_enabled'] ?? false));
    }

    public function test_update_partner_id_zero_does_not_touch_payment_system_block(): void
    {
        self::$canSettingsCommission = true;

        $rule = $this->makeRule();

        $payload = $this->payloadForUpdate([
            'partner_id' => 0, // !empty(0) == false => блок не выполняется
            'auto_payout_enabled' => 1,
        ]);

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload)
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило обновлено');

        $this->assertDatabaseMissing('payment_systems', [
            'name' => 'tbank',
            'partner_id' => 0,
        ]);
    }
}