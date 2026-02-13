<?php

namespace Tests\Feature\Crm;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentSystemControllerTest extends CrmTestCase
{
    private const ABILITY_VIEW = 'settings-paymentSystems-view';
    private const PERM_VIEW    = 'settings.paymentSystems.view';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // НЕ role_id=1, иначе Gate::before всё разрешит.
        $role = $this->createRole();

        $this->user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
        ]);

        $this->actingAs($this->user);
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

    protected function permissionIdByName(string $permissionName): int
    {
        $id = DB::table('permissions')->where('name', $permissionName)->value('id');
        $this->assertNotNull(
            $id,
            "Permission [{$permissionName}] не найден в таблице permissions. Проверь, что PermissionSeeder запускается в тестах."
        );

        return (int) $id;
    }

    protected function grantPermissionToRoleForPartner(int $roleId, int $partnerId, string $permissionName): void
    {
        $permissionId = $this->permissionIdByName($permissionName);

        $exists = DB::table('permission_role')->where([
            'partner_id'    => $partnerId,
            'role_id'       => $roleId,
            'permission_id' => $permissionId,
        ])->exists();

        if (!$exists) {
            DB::table('permission_role')->insert([
                'partner_id'    => $partnerId,
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_VIEW);

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

        $resp = $this->get(route('admin.setting.paymentSystem'));

        $this->assertTrue(
            in_array($resp->getStatusCode(), [302, 400, 401, 403], true),
            'Ожидался статус 302/400/401/403 для пользователя без partner_id из-за SetPartner'
        );
    }
}