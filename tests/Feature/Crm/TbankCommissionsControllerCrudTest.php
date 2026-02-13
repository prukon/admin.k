<?php

namespace Tests\Feature\Crm;

use App\Models\Partner;
use App\Models\TinkoffCommissionRule;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TbankCommissionsControllerCrudTest extends CrmTestCase
{
    protected static bool $canSettingsCommission = true;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('settings.commission', fn (?User $user = null) => self::$canSettingsCommission);
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
        ], $overrides);
    }

    public function test_index_opens_and_has_expected_view_data(): void
    {
        self::$canSettingsCommission = true;

        $resp = $this->get(route('admin.setting.tbankCommissions'));
        $resp->assertStatus(200);

        $resp->assertViewIs('admin.setting.index');
        $resp->assertViewHas('activeTab', 'tbankCommissions');
        $resp->assertViewHas('mode', 'list');

        $resp->assertViewHas('rules');
        $resp->assertViewHas('partners');
        $resp->assertViewHas('autoPayoutByPartnerId');
        $resp->assertViewHas('tbankConnectedByPartnerId');
    }

    public function test_create_opens(): void
    {
        self::$canSettingsCommission = true;

        $resp = $this->get(route('admin.setting.tbankCommissions.create'));
        $resp->assertStatus(200);

        $resp->assertViewIs('admin.setting.index');
        $resp->assertViewHas('activeTab', 'tbankCommissions');
        $resp->assertViewHas('mode', 'create');
        $resp->assertViewHas('partners');
        $resp->assertViewHas('rule', null);
    }

    public function test_store_creates_rule_and_redirects_with_status(): void
    {
        self::$canSettingsCommission = true;

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
        self::$canSettingsCommission = true;

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
        self::$canSettingsCommission = true;

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
        self::$canSettingsCommission = true;

        $payload = $this->validPayload();
        unset($payload['acquiring_percent']);

        $resp = $this->post(route('admin.setting.tbankCommissions.store'), $payload);
        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['acquiring_percent']);
    }

    public function test_store_validation_method_enum(): void
    {
        self::$canSettingsCommission = true;

        $payload = $this->validPayload([
            'method' => 'bad',
        ]);

        $resp = $this->post(route('admin.setting.tbankCommissions.store'), $payload);
        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['method']);
    }

    public function test_edit_opens_for_existing_rule(): void
    {
        self::$canSettingsCommission = true;

        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
        ]));

        $resp = $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]));
        $resp->assertStatus(200);

        $resp->assertViewIs('admin.setting.index');
        $resp->assertViewHas('activeTab', 'tbankCommissions');
        $resp->assertViewHas('mode', 'edit');
        $resp->assertViewHas('rule');
        $resp->assertViewHas('partners');
        $resp->assertViewHas('autoPayoutByPartnerId');
        $resp->assertViewHas('tbankConnectedByPartnerId');
    }

    public function test_update_updates_rule_when_partner_id_null_and_does_not_require_auto_payout_field(): void
    {
        self::$canSettingsCommission = true;

        $rule = TinkoffCommissionRule::create($this->validPayload([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'platform_percent' => 3.0,
        ]));

        $payload = $this->validPayload([
            'partner_id' => null, // блок автоплаты не должен выполняться
            'method' => 'tpay',
            'platform_percent' => 9.9,
            'is_enabled' => 0,
        ]);

        // auto_payout_enabled не отправляем специально
        unset($payload['auto_payout_enabled']);

        $resp = $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $payload);

        $resp->assertStatus(302);
        $resp->assertRedirect(route('admin.setting.tbankCommissions'));
        $resp->assertSessionHas('status', 'Правило обновлено');

        $rule->refresh();
        $this->assertSame('tpay', $rule->method);
        $this->assertSame(9.9, (float)$rule->platform_percent);
        $this->assertFalse((bool)$rule->is_enabled);
    }

    public function test_destroy_deletes_rule(): void
    {
        self::$canSettingsCommission = true;

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
        self::$canSettingsCommission = true;

        $this->get(route('admin.setting.tbankCommissions.edit', ['id' => 999999]))->assertStatus(404);

        $payload = $this->validPayload(['partner_id' => null]);
        $this->put(route('admin.setting.tbankCommissions.update', ['id' => 999999]), $payload)->assertStatus(404);

        $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => 999999]))
            ->assertStatus(302);
    }
}