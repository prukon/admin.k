<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\Partner;
use App\Models\TinkoffCommissionRule;
use Tests\Feature\Crm\CrmTestCase;

class TbankCommissionsControllerAutoPayoutTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
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
            'auto_payout_delay_hours' => 48,
        ], $overrides);
    }

    public function test_store_with_partner_id_saves_auto_payout_fields(): void
    {
        $payload = $this->payloadForUpdate([
            'method' => 'sbp',
            'auto_payout_enabled' => 1,
            'auto_payout_delay_hours' => 12,
        ]);

        $this->post(route('admin.setting.tbankCommissions.store'), $payload)
            ->assertRedirect(route('admin.setting.tbankCommissions'))
            ->assertSessionHas('status', 'Правило создано');

        $rule = TinkoffCommissionRule::where('partner_id', $this->partner->id)
            ->where('method', 'sbp')
            ->first();

        $this->assertNotNull($rule);
        $this->assertTrue($rule->auto_payout_enabled);
        $this->assertSame(12, (int) $rule->auto_payout_delay_hours);
    }

    public function test_store_with_partner_id_without_delay_hours_returns_validation_error(): void
    {
        $payload = $this->payloadForUpdate(['method' => 'tpay']);
        unset($payload['auto_payout_delay_hours']);

        $this->post(route('admin.setting.tbankCommissions.store'), $payload)
            ->assertStatus(302)
            ->assertSessionHasErrors('auto_payout_delay_hours');
    }

    public function test_index_create_modal_includes_auto_payout_fields(): void
    {
        $this->get(route('admin.setting.tbankCommissions', ['open_create' => 1]))
            ->assertOk()
            ->assertSee('id="tbank-auto-payout-create-block"', false)
            ->assertSee('id="tbank_create_auto_payout_delay_hours"', false)
            ->assertSee('Задержка после оплаты (часы)', false);
    }

    private function makeRule(): TinkoffCommissionRule
    {
        return TinkoffCommissionRule::create($this->payloadForUpdate([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]));
    }

    public function test_update_with_partner_id_sets_auto_payout_on_rule(): void
    {
        $rule = $this->makeRule();
        $partner = Partner::factory()->create();

        $payload = $this->payloadForUpdate([
            'partner_id' => $partner->id,
            'auto_payout_enabled' => 1,
            'auto_payout_delay_hours' => 24,
        ]);

        $resp = $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload);

        $resp->assertRedirect(route('admin.setting.tbankCommissions'));
        $resp->assertSessionHas('status', 'Правило обновлено');

        $rule->refresh();
        $this->assertTrue($rule->auto_payout_enabled);
        $this->assertSame(24, (int) $rule->auto_payout_delay_hours);
    }

    public function test_update_auto_payout_checkbox_missing_means_false(): void
    {
        $rule = $this->makeRule();
        $partner = Partner::factory()->create();

        $this->put(
            route('admin.setting.tbankCommissions.update', ['id' => $rule->id]),
            $this->payloadForUpdate([
                'partner_id' => $partner->id,
                'auto_payout_enabled' => 1,
                'auto_payout_delay_hours' => 12,
            ])
        )->assertRedirect(route('admin.setting.tbankCommissions'));

        $this->put(
            route('admin.setting.tbankCommissions.update', ['id' => $rule->id]),
            $this->payloadForUpdate([
                'partner_id' => $partner->id,
                'auto_payout_enabled' => 0,
                'auto_payout_delay_hours' => 12,
            ])
        )->assertRedirect(route('admin.setting.tbankCommissions'));

        $rule->refresh();
        $this->assertFalse($rule->auto_payout_enabled);
        $this->assertSame(12, (int) $rule->auto_payout_delay_hours);
    }

    public function test_update_requires_delay_hours_for_partner_rule(): void
    {
        $rule = $this->makeRule();
        $partner = Partner::factory()->create();

        $payload = $this->payloadForUpdate([
            'partner_id' => $partner->id,
            'auto_payout_enabled' => 1,
        ]);
        unset($payload['auto_payout_delay_hours']);

        $this->from(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]))
            ->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload)
            ->assertSessionHasErrors('auto_payout_delay_hours');
    }

    public function test_update_partner_id_change_sets_auto_payout_for_new_partner_rule(): void
    {
        $rule = $this->makeRule();

        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $this->payloadForUpdate([
            'partner_id' => $partnerA->id,
            'auto_payout_enabled' => 1,
            'auto_payout_delay_hours' => 6,
        ]))->assertRedirect(route('admin.setting.tbankCommissions'));

        $payloadB = $this->payloadForUpdate(['partner_id' => $partnerB->id, 'auto_payout_delay_hours' => 0]);
        unset($payloadB['auto_payout_enabled']);

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payloadB)
            ->assertRedirect(route('admin.setting.tbankCommissions'));

        $rule->refresh();
        $this->assertFalse($rule->auto_payout_enabled);
        $this->assertSame(0, (int) $rule->auto_payout_delay_hours);
    }

    public function test_update_partner_id_zero_resets_auto_payout_on_rule(): void
    {
        $rule = $this->makeRule();

        $payload = $this->payloadForUpdate([
            'partner_id' => 0,
            'auto_payout_enabled' => 1,
            'auto_payout_delay_hours' => 48,
        ]);

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload)
            ->assertRedirect(route('admin.setting.tbankCommissions'));

        $rule->refresh();
        $this->assertNull($rule->partner_id);
        $this->assertFalse($rule->auto_payout_enabled);
        $this->assertSame(0, (int) $rule->auto_payout_delay_hours);
    }
}
