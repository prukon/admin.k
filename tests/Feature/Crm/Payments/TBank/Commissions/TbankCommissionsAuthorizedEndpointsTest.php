<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к странице и эндпоинтам комиссий Т‑Банк для авторизованных пользователей с правом settings.commission.
 */
class TbankCommissionsAuthorizedEndpointsTest extends CrmTestCase
{
    /**
     * Суперадмин: страница, JSON data, редирект create, CRUD и настройки выплат возвращают успешные ответы.
     */
    public function test_superadmin_all_tbank_commission_endpoints_succeed(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('admin.setting.tbankCommissions'))->assertOk();

        $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('admin.setting.tbankCommissions.create'))
            ->assertRedirect(route('admin.setting.tbankCommissions', ['open_create' => 1]));

        $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_scheduled_interval_minutes' => 10,
        ])->assertRedirect(route('admin.setting.tbankCommissions'));

        $payload = $this->validRulePayload();

        $this->post(route('admin.setting.tbankCommissions.store'), $payload)
            ->assertRedirect(route('admin.setting.tbankCommissions'));

        $rule = TinkoffCommissionRule::where('partner_id', $this->partner->id)->first();
        $this->assertNotNull($rule);

        $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]))->assertOk();

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), array_merge($payload, [
            'method' => 'sbp',
        ]))->assertRedirect(route('admin.setting.tbankCommissions'));

        $r2 = TinkoffCommissionRule::create($this->validRulePayload(['method' => 'tpay']));

        $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => $r2->id]))
            ->assertRedirect();
    }

    /**
     * Пользователь с ролью admin и явно выданным permission settings.commission получает 200 на страницу и data.
     */
    public function test_admin_with_settings_commission_permission_gets_ok_on_index_and_data(): void
    {
        $permId = $this->permissionId('settings.commission');
        $adminRoleId = $this->roleId('admin');
        $now = now();

        DB::table('permission_role')->insertOrIgnore([[
            'partner_id' => $this->partner->id,
            'role_id' => $adminRoleId,
            'permission_id' => $permId,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);

        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('admin.setting.tbankCommissions'))->assertOk();

        $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 5,
        ]))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRulePayload(array $overrides = []): array
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
