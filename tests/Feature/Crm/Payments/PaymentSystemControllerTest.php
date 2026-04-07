<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class PaymentSystemControllerTest extends CrmTestCase
{
    private const PERM_VIEW = 'settings.paymentSystems.view';

    /** Право на настройку/использование способа «Робокасса» (см. PaymentSystemController::authorizePaymentSystemMethod). */
    private const PERM_ROBOKASSA_METHOD = 'payment.method.robokassa';

    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        // По умолчанию заходим пользователем БЕЗ права PERM_VIEW, чтобы P0 тесты были честными.
        $this->actor = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->actingAs($this->actor);
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    /* ============================================================
     | Helpers
     |============================================================ */

    protected function createRole(): Role
    {
        return Role::query()->create([
            'name'       => 'test-role-'.uniqid(),
            'label'      => 'Test role',
            'is_sistem'  => 0,
            'is_visible' => 1,
            'order_by'   => 0,
        ]);
    }

    protected function grantPermissionToRoleForPartner(int $roleId, int $partnerId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partnerId,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId($permissionName),
        ]);
    }

    /* ============================================================
     | P0 — can middleware
     |============================================================ */

    public function test_index_forbidden_without_permission(): void
    {
        $this->get(route('admin.setting.paymentSystem'))->assertStatus(403);
    }

    public function test_store_forbidden_without_permission(): void
    {
        $this->postJson(route('payment-systems.store'), [
            'name' => 'robokassa',
            'merchant_login' => 'login',
        ])->assertStatus(403);
    }

    /**
     * Страница платёжных систем доступна по settings.paymentSystems.view, но сохранение Robokassa требует ещё payment.method.robokassa.
     */
    public function test_store_robokassa_forbidden_without_method_permission_even_with_settings_view(): void
    {
        $role = $this->createRole();
        $this->grantPermissionToRoleForPartner($role->id, $this->partner->id, self::PERM_VIEW);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
        ]);

        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson(route('payment-systems.store'), [
            'name'           => 'robokassa',
            'merchant_login' => 'login',
        ])->assertStatus(403);
    }

    public function test_show_forbidden_without_permission(): void
    {
        $this->getJson(route('payment-systems.show', ['name' => 'tbank']))->assertStatus(403);
    }

    public function test_destroy_forbidden_without_permission(): void
    {
        $ps = PaymentSystem::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'tbank',
        ]);

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $ps->id]))
            ->assertStatus(403);
    }

    /* ============================================================
     | P0 — изоляция (с правом)
     |============================================================ */

    public function test_index_returns_only_current_partner_payment_systems(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);

        $otherPartner = Partner::factory()->create();

        PaymentSystem::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'robokassa',
        ]);

        PaymentSystem::factory()->create([
            'partner_id' => $otherPartner->id,
            'name'       => 'tbank',
        ]);

        $response = $this->get(route('admin.setting.paymentSystem'));

        $response->assertStatus(200);
        $response->assertViewHas('paymentSystems', function ($collection) {
            return $collection->every(fn($item) => (int)$item->partner_id === (int)$this->partner->id);
        });
    }

    public function test_show_returns_404_for_other_partner_even_with_permission(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);

        $otherPartner = Partner::factory()->create();

        PaymentSystem::factory()->create([
            'partner_id' => $otherPartner->id,
            'name'       => 'tbank',
            'settings'   => ['terminal_key' => 'x'],
            'test_mode'  => 1,
        ]);

        $this->getJson(route('payment-systems.show', ['name' => 'tbank']))
            ->assertStatus(404);
    }

    public function test_destroy_forbidden_for_other_partner_even_with_permission(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);

        $otherPartner = Partner::factory()->create();

        $ps = PaymentSystem::factory()->create([
            'partner_id' => $otherPartner->id,
            'name'       => 'robokassa',
        ]);

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $ps->id]))
            ->assertStatus(403);

        $this->assertDatabaseHas('payment_systems', ['id' => $ps->id]);
    }

    /* ============================================================
     | P1 — store (с правом)
     |============================================================ */

    public function test_store_creates_robokassa_settings(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_ROBOKASSA_METHOD);

        $payload = [
            'name'           => 'robokassa',
            'merchant_login' => 'login',
            'password1'      => 'p1',
            'password2'      => 'p2',
            'password3'      => 'p3',
            'test_mode'      => 1,
        ];

        $this->postJson(route('payment-systems.store'), $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('payment_systems', [
            'partner_id' => $this->partner->id,
            'name'       => 'robokassa',
            'test_mode'  => 1,
        ]);
    }

    public function test_store_updates_existing_record_not_duplicate(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);

        PaymentSystem::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'tbank',
        ]);

        $this->postJson(route('payment-systems.store'), [
            'name'         => 'tbank',
            'terminal_key' => 'term1',
        ])->assertStatus(200);

        $this->assertSame(
            1,
            PaymentSystem::query()
                ->where('partner_id', $this->partner->id)
                ->where('name', 'tbank')
                ->count()
        );
    }

    public function test_store_keeps_old_password3_if_not_provided(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_ROBOKASSA_METHOD);

        $ps = PaymentSystem::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'robokassa',
            'settings'   => ['password3' => 'old_secret'],
        ]);

        $this->postJson(route('payment-systems.store'), [
            'name'           => 'robokassa',
            'merchant_login' => 'new_login',
        ])->assertStatus(200);

        $ps->refresh();
        $this->assertSame('old_secret', $ps->settings['password3'] ?? null);
    }

    public function test_store_saves_tbank_eacq_and_e2c_settings(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);

        $payload = [
            'name'               => 'tbank',
            'terminal_key'       => 'eacq_term',
            'token_password'     => 'eacq_token',
            'e2c_terminal_key'   => 'e2c_term',
            'e2c_token_password' => 'e2c_token',
        ];

        $this->postJson(route('payment-systems.store'), $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $row = PaymentSystem::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'tbank')
            ->firstOrFail();

        $this->assertSame('eacq_term', $row->settings['terminal_key'] ?? null);
        $this->assertSame('eacq_token', $row->settings['token_password'] ?? null);
        $this->assertSame('e2c_term', $row->settings['e2c_terminal_key'] ?? null);
        $this->assertSame('e2c_token', $row->settings['e2c_token_password'] ?? null);
    }

    public function test_store_validation_fails_without_name(): void
    {
        $this->grantPermissionToRoleForPartner($this->actor->role_id, $this->partner->id, self::PERM_VIEW);

        $this->post(route('payment-systems.store'), [])
            ->assertStatus(302)
            ->assertSessionHasErrors('name');
    }

    public function test_user_without_partner_is_blocked_by_set_partner(): void
    {
        $role = $this->createRole();
        $this->grantPermissionToRoleForPartner($role->id, $this->partner->id, self::PERM_VIEW);

        $userWithoutPartner = User::factory()->create([
            'partner_id' => null,
            'role_id'    => $role->id,
        ]);

        $this->actingAs($userWithoutPartner);
        $this->withSession([]);

        $resp = $this->get(route('admin.setting.paymentSystem'));

        $this->assertTrue(
            in_array($resp->getStatusCode(), [302, 400, 401, 403], true),
            'Ожидался статус 302/400/401/403 для пользователя без partner_id из-за SetPartner'
        );
    }
}