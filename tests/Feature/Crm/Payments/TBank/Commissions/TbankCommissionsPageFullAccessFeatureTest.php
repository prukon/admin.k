<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к /admin/settings/tbank-commissions и связанным эндпоинтам
 * (settings.commission → успешный ответ, без права → 403).
 */
final class TbankCommissionsPageFullAccessFeatureTest extends CrmTestCase
{
    private TinkoffCommissionRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->rule = TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 1.2,
            'payout_min_fixed' => 0,
            'platform_percent' => 3.0,
            'platform_min_fixed' => 0,
            'min_fixed' => 0,
            'is_enabled' => true,
        ]);
    }

    public function test_index_page_returns_200_with_settings_view_and_toolbar(): void
    {
        $this->asSuperadmin();

        $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'tbankCommissions')
            ->assertViewHas('mode', 'list')
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('>Настройки выплат</span>', false)
            ->assertSee('>Добавить комиссию</span>', false)
            ->assertSee('>Фильтры</span>', false)
            ->assertSee('id="tbank-commissions-table"', false);
    }

    public function test_superadmin_all_tbank_commissions_endpoints_succeed(): void
    {
        $this->asSuperadmin();

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_user_with_settings_commission_can_access_all_section_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('settings.commission', $this->partner);
        $this->grantSettingsCommission($actor);
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_tbank_commissions_routes_return_403_without_settings_commission(): void
    {
        $this->asAdmin();

        $ruleId = $this->rule->id;
        $payload = $this->validRulePayload();

        $this->get(route('admin.setting.tbankCommissions'))->assertForbidden();
        $this->getJson(route('admin.setting.tbankCommissions.data', ['draw' => 1]))->assertForbidden();
        $this->get(route('admin.setting.tbankCommissions.create'))->assertForbidden();
        $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $ruleId]))->assertForbidden();

        $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_auto_delay_hours' => 48,
            'payout_scheduled_interval_minutes' => 10,
        ])->assertForbidden();

        $this->post(route('admin.setting.tbankCommissions.store'), $payload)->assertForbidden();

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $ruleId]), $payload)
            ->assertForbidden();

        $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => $ruleId]))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_any_tbank_commissions_endpoint(): void
    {
        Auth::logout();

        $ruleId = $this->rule->id;
        $payload = $this->validRulePayload();

        $endpoints = [
            fn () => $this->get(route('admin.setting.tbankCommissions')),
            fn () => $this->getJson(route('admin.setting.tbankCommissions.data', ['draw' => 1])),
            fn () => $this->get(route('admin.setting.tbankCommissions.create')),
            fn () => $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $ruleId])),
            fn () => $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
                'payout_auto_delay_hours' => 48,
                'payout_scheduled_interval_minutes' => 10,
            ]),
            fn () => $this->post(route('admin.setting.tbankCommissions.store'), $payload),
            fn () => $this->put(route('admin.setting.tbankCommissions.update', ['id' => $ruleId]), $payload),
            fn () => $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => $ruleId])),
        ];

        foreach ($endpoints as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403, 419], 'Unexpected status: ' . $status);
        }
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(): void
    {
        $this->get(route('admin.setting.tbankCommissions'))->assertOk();

        $this->get(route('admin.setting.tbankCommissions', [
            'filter_partner_id' => $this->partner->id,
            'filter_method' => 'card',
        ]))->assertOk();

        $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.setting.tbankCommissions.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'filter_partner_id' => $this->partner->id,
            'filter_method' => 'card',
        ]))->assertOk();

        $this->get(route('admin.setting.tbankCommissions.create'))
            ->assertRedirect(route('admin.setting.tbankCommissions', ['open_create' => 1]));

        $this->get(route('admin.setting.tbankCommissions', ['open_create' => 1]))
            ->assertOk();

        $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_auto_delay_hours' => 48,
            'payout_scheduled_interval_minutes' => 10,
        ])->assertRedirect(route('admin.setting.tbankCommissions'));

        $storePayload = $this->validRulePayload(['method' => 'sbp']);

        $this->post(route('admin.setting.tbankCommissions.store'), $storePayload)
            ->assertRedirect(route('admin.setting.tbankCommissions'));

        $created = TinkoffCommissionRule::where('partner_id', $this->partner->id)
            ->where('method', 'sbp')
            ->first();
        $this->assertNotNull($created);

        $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $this->rule->id]))
            ->assertOk()
            ->assertViewHas('mode', 'edit');

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $this->rule->id]), array_merge(
            $this->validRulePayload(),
            ['method' => 'tpay']
        ))->assertRedirect(route('admin.setting.tbankCommissions'));

        $disposable = TinkoffCommissionRule::create($this->validRulePayload([
            'method' => 'tpay',
            'partner_id' => $this->foreignPartner->id,
        ]));

        $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => $disposable->id]))
            ->assertRedirect();
    }

    private function grantSettingsCommission(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('settings.commission'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        ], $overrides);
    }
}
