<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\Setting;
use App\Models\TinkoffCommissionRule;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для форм раздела комиссий T‑Bank (store/update/payout-settings).
 */
final class TbankCommissionsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_store_non_ajax_redirects_and_creates_rule(): void
    {
        $payload = $this->validPayload(['method' => 'tpay']);

        $this->post(route('admin.setting.tbankCommissions.store'), $payload)
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило создано');

        $this->assertDatabaseHas('tinkoff_commission_rules', [
            'partner_id' => $this->partner->id,
            'method' => 'tpay',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_rule(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'platform_percent' => 3.0,
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]));

        $payload = $this->validPayload([
            'platform_percent' => 7.5,
            'auto_payout_enabled' => 1,
            'auto_payout_delay_hours' => 24,
        ]);

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload)
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило обновлено');

        $rule->refresh();
        $this->assertSame(7.5, (float) $rule->platform_percent);
        $this->assertTrue((bool) $rule->auto_payout_enabled);
        $this->assertSame(24, (int) $rule->auto_payout_delay_hours);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload());

        $payload = $this->validPayload();
        unset($payload['auto_payout_delay_hours']);

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload)
            ->assertStatus(302)
            ->assertSessionHasErrors('auto_payout_delay_hours');
    }

    public function test_payout_settings_non_ajax_redirects_and_saves_interval(): void
    {
        $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_scheduled_interval_minutes' => 20,
        ])
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status');

        $this->assertSame(20, Setting::getTinkoffPayoutScheduledIntervalMinutes());
    }

    public function test_payout_settings_non_ajax_validation_failure_redirects_with_errors(): void
    {
        $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_scheduled_interval_minutes' => 0,
        ])
            ->assertStatus(302)
            ->assertSessionHasErrors('payout_scheduled_interval_minutes');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
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
            'auto_payout_enabled' => 0,
            'auto_payout_delay_hours' => 0,
        ], $overrides);
    }
}
