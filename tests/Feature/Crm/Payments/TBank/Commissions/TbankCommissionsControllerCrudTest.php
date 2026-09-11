<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use Tests\Feature\Crm\CrmTestCase;

class TbankCommissionsControllerCrudTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
    }

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

            // опционально
            'min_fixed' => 0,
            'is_enabled' => 1,
            'auto_payout_delay_hours' => 0,
        ], $overrides);
    }

    public function test_index_opens_and_has_expected_view_data(): void
    {
        $resp = $this->get(route('admin.setting.tbankCommissions'));
        $resp->assertStatus(200);

        $resp->assertViewIs('admin.setting.index');
        $resp->assertViewHas('activeTab', 'tbankCommissions');
        $resp->assertViewHas('mode', 'list');

        $resp->assertViewHas('partners');
        $resp->assertViewHas('tbankGloballyConnected');
        $resp->assertViewHas('autoPayoutStatsByPartnerId');
    }

    public function test_create_redirects_to_list_with_open_create_query(): void
    {
        $resp = $this->get(route('admin.setting.tbankCommissions.create'));
        $resp->assertStatus(302);
        $resp->assertRedirect(route('admin.setting.tbankCommissions', ['open_create' => 1]));
    }

    public function test_store_creates_rule_and_redirects_with_status(): void
    {
        $payload = $this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'sbp',
            'is_enabled' => 1,
        ]);

        $resp = $this->post(route('admin.setting.tbankCommissions.store'), $payload);

        $resp->assertStatus(302);
        $resp->assertRedirect(route('admin.setting.tbankCommissions'));
        $resp->assertSessionHas('status', 'Правило создано');

        $this->assertDatabaseHas('tinkoff_commission_rules', [
            'partner_id' => $this->partner->id,
            'method' => 'sbp',
        ]);
    }

    public function test_store_sets_is_enabled_false_when_checkbox_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['is_enabled']);

        $resp = $this->post(route('admin.setting.tbankCommissions.store'), $payload);
        $resp->assertStatus(302);

        $rule = TinkoffCommissionRule::latest('id')->first();
        $this->assertNotNull($rule);

        $this->assertFalse((bool)$rule->is_enabled);
    }

    public function test_store_sets_min_fixed_default_zero_when_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['min_fixed']);

        $resp = $this->post(route('admin.setting.tbankCommissions.store'), $payload);
        $resp->assertStatus(302);

        $rule = TinkoffCommissionRule::latest('id')->first();
        $this->assertNotNull($rule);

        $this->assertSame(0.0, (float)$rule->min_fixed);
    }

    public function test_store_validation_required_fields(): void
    {
        $payload = $this->validPayload();
        unset($payload['acquiring_percent']);

        $resp = $this->post(route('admin.setting.tbankCommissions.store'), $payload);
        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['acquiring_percent']);
    }

    public function test_store_validation_method_enum(): void
    {
        $payload = $this->validPayload([
            'method' => 'bad',
        ]);

        $resp = $this->post(route('admin.setting.tbankCommissions.store'), $payload);
        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['method']);
    }

    public function test_edit_non_ajax_redirects_to_list_with_edit_query(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
        ]));

        $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]))
            ->assertStatus(302)
            ->assertRedirect(route('admin.setting.tbankCommissions', ['edit' => $rule->id]));
    }

    public function test_edit_ajax_returns_json_payload(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 12,
            'platform_percent' => 4.5,
        ]));

        $this->getJson(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]))
            ->assertOk()
            ->assertJsonPath('id', $rule->id)
            ->assertJsonPath('partner_id', $this->partner->id)
            ->assertJsonPath('partner_title', $this->partner->title)
            ->assertJsonPath('method', 'card')
            ->assertJsonPath('method_label', 'Карты')
            ->assertJsonPath('platform_percent', 4.5)
            ->assertJsonPath('auto_payout_enabled', true)
            ->assertJsonPath('auto_payout_delay_hours', 12)
            ->assertJsonStructure([
                'acquiring_percent',
                'acquiring_min_fixed',
                'payout_percent',
                'payout_min_fixed',
                'platform_min_fixed',
                'is_enabled',
                'tbank_globally_connected',
                'payouts_30d_count',
                'payouts_30d_last_at',
                'payouts_30d_url',
            ]);
    }

    public function test_update_ajax_validation_returns_422_with_field_errors(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
        ]));

        $payload = $this->validPayload();
        unset($payload['auto_payout_delay_hours']);

        $this->putJson(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auto_payout_delay_hours']);
    }

    public function test_update_ajax_succeeds_and_returns_json(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'platform_percent' => 3.0,
        ]));

        $this->putJson(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $this->validPayload([
            'method' => 'sbp',
            'platform_percent' => 8.1,
            'auto_payout_delay_hours' => 6,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Правило обновлено');

        $rule->refresh();
        $this->assertSame('card', $rule->method);
        $this->assertSame((int) $this->partner->id, (int) $rule->partner_id);
        $this->assertSame(8.1, (float) $rule->platform_percent);
    }

    public function test_update_keeps_scope_of_global_rule_and_does_not_require_auto_payout_field(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => null,
            'method' => null,
            'platform_percent' => 3.0,
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]));

        $payload = $this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'tpay',
            'platform_percent' => 9.9,
            'is_enabled' => 0,
        ]);
        unset($payload['auto_payout_enabled']);
        unset($payload['auto_payout_delay_hours']);

        $resp = $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload);

        $resp->assertStatus(302);
        $resp->assertRedirect(route('admin.setting.tbankCommissions'));
        $resp->assertSessionHas('status', 'Правило обновлено');

        $rule->refresh();
        $this->assertNull($rule->partner_id);
        $this->assertNull($rule->method);
        $this->assertSame(9.9, (float) $rule->platform_percent);
        $this->assertFalse((bool) $rule->is_enabled);
        $this->assertFalse((bool) $rule->auto_payout_enabled);
    }

    public function test_update_ignores_spoofed_partner_id_and_method(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'platform_percent' => 3.0,
        ]));

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $this->validPayload([
            'partner_id' => $this->foreignPartner->id,
            'method' => 'tpay',
            'platform_percent' => 5.5,
            'auto_payout_delay_hours' => 3,
        ]))
            ->assertRedirect(route('admin.setting.tbankCommissions'));

        $rule->refresh();
        $this->assertSame((int) $this->partner->id, (int) $rule->partner_id);
        $this->assertSame('card', $rule->method);
        $this->assertSame(5.5, (float) $rule->platform_percent);
    }

    public function test_destroy_deletes_rule(): void
    {
        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
        ]));

        $resp = $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => $rule->id]));
        $resp->assertStatus(302);
        $resp->assertSessionHas('status', 'Правило удалено');

        $this->assertDatabaseMissing('tinkoff_commission_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_edit_update_destroy_missing_id_returns_404(): void
    {
        $this->get(route('admin.setting.tbankCommissions.edit', ['id' => 999999]))->assertStatus(404);

        $payload = $this->validPayload(['partner_id' => null]);
        $this->put(route('admin.setting.tbankCommissions.update', ['id' => 999999]), $payload)->assertStatus(404);

        $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => 999999]))
            ->assertStatus(302);
    }
}

