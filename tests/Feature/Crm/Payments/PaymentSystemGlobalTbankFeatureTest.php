<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\PaymentSystem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Глобальный терминал T‑Bank: AJAX-контракт, non-AJAX safety-net, UI и доступ.
 */
final class PaymentSystemGlobalTbankFeatureTest extends CrmTestCase
{
    private const PERM_VIEW = 'settings.paymentSystems.view';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_index_displays_global_terminal_card_when_user_has_tbank_method_permission(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();

        $this->actingAs($actor)
            ->get(route('admin.setting.paymentSystem'))
            ->assertOk()
            ->assertSee('Терминал платформы (общий для всех партнёров)', false)
            ->assertSee('T‑Bank включён на платформе', false)
            ->assertViewHas('tbank');
    }

    public function test_index_uses_global_tbank_variable_not_legacy_per_partner_row(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $global = $this->seedGlobalTbank(['terminal_key' => 'global-only']);

        PaymentSystem::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'settings' => [
                'terminal_key' => 'legacy-partner',
                'token_password' => 'pwd',
                'e2c_terminal_key' => 'e2c',
                'e2c_token_password' => 'e2cpwd',
            ],
            'is_enabled' => true,
        ]);

        $this->actingAs($actor)
            ->get(route('admin.setting.paymentSystem'))
            ->assertOk()
            ->assertViewHas('tbank', function ($tbank) use ($global) {
                return $tbank !== null
                    && (int) $tbank->id === (int) $global->id
                    && ($tbank->settings['terminal_key'] ?? '') === 'global-only';
            });
    }

    public function test_store_ajax_returns_json_with_status_and_message(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $this->actingAs($actor);

        $payload = $this->validTbankPayload();

        $this->postJson(route('payment-systems.store'), $payload)
            ->assertOk()
            ->assertJsonStructure(['status', 'message'])
            ->assertJsonPath('status', 'success');

        $row = PaymentSystem::globalTbank();
        $this->assertNotNull($row);
        $this->assertSame('ajax-term', $row->settings['terminal_key'] ?? null);
    }

    public function test_store_non_ajax_redirects_and_saves_global_tbank(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $this->actingAs($actor);

        $payload = $this->validTbankPayload([
            'terminal_key' => 'non-ajax-term',
        ]);

        $response = $this->post(route('payment-systems.store'), $payload);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.setting.paymentSystem'));
        $response->assertSessionHas('status');

        $row = PaymentSystem::globalTbank();
        $this->assertNotNull($row);
        $this->assertNull($row->partner_id);
        $this->assertSame('non-ajax-term', $row->settings['terminal_key'] ?? null);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $this->actingAs($actor);

        $this->post(route('payment-systems.store'), ['terminal_key' => 'orphan'])
            ->assertStatus(302)
            ->assertSessionHasErrors('name');
    }

    public function test_show_ajax_returns_global_tbank_settings_and_flags(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $this->actingAs($actor);

        $this->seedGlobalTbank([
            'terminal_key' => 'show-term',
            'token_password' => 'show-pwd',
            'e2c_terminal_key' => 'e2c',
            'e2c_token_password' => 'e2cpwd',
        ], ['is_enabled' => true, 'test_mode' => true]);

        $this->getJson(route('payment-systems.show', ['name' => 'tbank']))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.terminal_key', 'show-term')
            ->assertJsonPath('is_enabled', true)
            ->assertJsonPath('test_mode', true);
    }

    public function test_show_returns_404_when_global_tbank_not_configured(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $this->actingAs($actor);

        PaymentSystem::query()->whereNull('partner_id')->where('name', 'tbank')->delete();

        $this->getJson(route('payment-systems.show', ['name' => 'tbank']))
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_destroy_global_tbank_requires_method_permission(): void
    {
        $actor = $this->userWithViewOnly();
        $this->actingAs($actor);

        $global = $this->seedGlobalTbank();

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $global->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('payment_systems', ['id' => $global->id]);
    }

    public function test_destroy_global_tbank_forbidden_for_non_superadmin_with_method_permission(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $this->actingAs($actor);

        $global = $this->seedGlobalTbank();

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $global->id]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Удаление глобального терминала T‑Bank доступно только superadmin');

        $this->assertDatabaseHas('payment_systems', ['id' => $global->id]);
    }

    public function test_destroy_global_tbank_succeeds_for_superadmin_with_method_permission(): void
    {
        $actor = $this->authorizedActorWithTbankMethod();
        $adminRoleId = DB::table('roles')->where('name', 'superadmin')->value('id');
        $actor->role_id = $adminRoleId;
        $actor->save();
        $this->actingAs($actor);

        $global = $this->seedGlobalTbank();

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $global->id]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('payment_systems', ['id' => $global->id]);
    }

    public function test_guest_cannot_access_tbank_payment_system_endpoints(): void
    {
        Auth::logout();

        $global = $this->seedGlobalTbank();

        $routes = [
            ['GET', route('admin.setting.paymentSystem')],
            ['POST', route('payment-systems.store'), ['name' => 'tbank', 'terminal_key' => 'x']],
            ['GET', route('payment-systems.show', ['name' => 'tbank'])],
            ['DELETE', route('payment-systems.destroy', ['payment_system' => $global->id])],
        ];

        foreach ($routes as $route) {
            [$method, $url] = $route;
            $data = $route[2] ?? [];
            $response = $this->call($method, $url, $data);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$method} {$url} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_settings_view_gets_403_on_all_tbank_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->actingAs($denied);

        $global = $this->seedGlobalTbank();

        foreach ($this->tbankEndpointMatrix($global->id) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без settings.paymentSystems.view: {$item['method']} {$item['url']}"
            );
        }
    }

    public function test_user_with_view_but_without_tbank_method_gets_403_on_tbank_mutations(): void
    {
        $actor = $this->userWithViewOnly();
        $this->actingAs($actor);

        $global = $this->seedGlobalTbank();

        $this->postJson(route('payment-systems.store'), $this->validTbankPayload())
            ->assertForbidden();

        $this->getJson(route('payment-systems.show', ['name' => 'tbank']))
            ->assertForbidden();

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $global->id]))
            ->assertForbidden();
    }

    public function test_store_tbank_with_sbp_method_permission_only(): void
    {
        $actor = $this->userWithOnlyPermissions([self::PERM_VIEW, 'payment.method.tbankSBP']);
        $this->actingAs($actor);

        $this->postJson(route('payment-systems.store'), $this->validTbankPayload())
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTbankPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'tbank',
            'terminal_key' => 'ajax-term',
            'token_password' => 'ajax-pwd',
            'e2c_terminal_key' => 'e2c-term',
            'e2c_token_password' => 'e2c-pwd',
            'is_enabled' => 1,
        ], $overrides);
    }

    private function authorizedActorWithTbankMethod(): User
    {
        return $this->userWithOnlyPermissions([self::PERM_VIEW, 'payment.method.tbankCard']);
    }

    private function userWithViewOnly(): User
    {
        return $this->userWithOnlyPermissions([self::PERM_VIEW]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithOnlyPermissions(array $permissions): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'test_ps_' . uniqid('', true),
            'label' => 'Test payment systems',
            'is_sistem' => 0,
            'order_by' => 0,
            'is_visible' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
        ]);

        foreach ($permissions as $permission) {
            $this->grant($user, $permission);
        }

        return $user;
    }

    private function grant(User $user, string $permission): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permission),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function tbankEndpointMatrix(int $globalId): array
    {
        return [
            ['method' => 'GET', 'url' => route('admin.setting.paymentSystem')],
            [
                'method' => 'POST',
                'url' => route('payment-systems.store'),
                'data' => $this->validTbankPayload(),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'GET',
                'url' => route('payment-systems.show', ['name' => 'tbank']),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'DELETE',
                'url' => route('payment-systems.destroy', ['payment_system' => $globalId]),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
        ];
    }
}
