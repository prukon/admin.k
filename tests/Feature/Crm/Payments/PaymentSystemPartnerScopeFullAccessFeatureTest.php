<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Настройки → Платёжные системы: доступ к странице и API,
 * STRICT_CURRENT — данные только текущего партнёра.
 */
final class PaymentSystemPartnerScopeFullAccessFeatureTest extends CrmTestCase
{
    private const PERM_VIEW = 'settings.paymentSystems.view';
    private const PERM_ROBOKASSA = 'payment.method.robokassa';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_cannot_access_payment_systems_endpoints(): void
    {
        Auth::logout();

        $ps = PaymentSystem::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'robokassa',
        ]);

        foreach ($this->allSectionRoutesPayload($ps->id) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_permission_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->actingAs($denied);

        $ps = PaymentSystem::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'robokassa',
        ]);

        foreach ($this->allSectionRoutesPayload($ps->id) as $item) {
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
                "Без settings.paymentSystems.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_permissions_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->grantPermission($actor, self::PERM_VIEW);
        $this->grantPermission($actor, self::PERM_ROBOKASSA);
        $this->actingAs($actor);

        $this->get(route('admin.setting.paymentSystem'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'paymentSystem');

        $this->postJson(route('payment-systems.store'), [
            'name'           => 'robokassa',
            'merchant_login' => 'scope_login',
            'password1'      => 'p1',
            'password2'      => 'p2',
            'test_mode'      => 1,
        ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->getJson(route('payment-systems.show', ['name' => 'robokassa']))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $ps = PaymentSystem::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'robokassa')
            ->firstOrFail();

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $ps->id]))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_index_page_does_not_expose_foreign_partner_titles(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->grantPermission($actor, self::PERM_VIEW);
        $this->actingAs($actor);

        $foreignTitle = 'ForeignPaySchool_' . uniqid('', true);
        $this->foreignPartner->update(['title' => $foreignTitle]);

        PaymentSystem::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'robokassa',
        ]);

        $this->get(route('admin.setting.paymentSystem'))
            ->assertOk()
            ->assertDontSee($foreignTitle, false);
    }

    public function test_index_view_has_only_current_partner_payment_systems(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->grantPermission($actor, self::PERM_VIEW);
        $this->actingAs($actor);

        PaymentSystem::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'robokassa',
        ]);
        PaymentSystem::factory()->robokassa()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->get(route('admin.setting.paymentSystem'))
            ->assertOk()
            ->assertViewHas('paymentSystems', function ($collection) {
                return $collection->every(
                    fn ($item) => (int) $item->partner_id === (int) $this->partner->id
                );
            });
    }

    public function test_store_always_writes_to_current_partner_not_session_mismatch(): void
    {
        $this->asSuperadmin();
        $this->grantPermission($this->user, self::PERM_VIEW);
        $this->grantPermission($this->user, self::PERM_ROBOKASSA);

        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->postJson(route('payment-systems.store'), [
                'name'           => 'robokassa',
                'merchant_login' => 'super_login',
            ])
            ->assertOk();

        $this->assertDatabaseHas('payment_systems', [
            'partner_id' => $this->partner->id,
            'name'       => 'robokassa',
        ]);
        $this->assertDatabaseMissing('payment_systems', [
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'robokassa',
        ]);
    }

    public function test_show_returns_global_tbank_regardless_of_partner_context(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->grantPermission($actor, self::PERM_VIEW);
        $this->grantPermission($actor, 'payment.method.tbankCard');
        $this->actingAs($actor);

        $this->seedGlobalTbank(['terminal_key' => 'global-key']);

        $this->getJson(route('payment-systems.show', ['name' => 'tbank']))
            ->assertOk()
            ->assertJsonPath('data.terminal_key', 'global-key');
    }

    public function test_destroy_returns_403_for_other_partner_payment_system(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_VIEW, $this->partner);
        $this->grantPermission($actor, self::PERM_VIEW);
        $this->actingAs($actor);

        $foreignPs = PaymentSystem::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'robokassa',
        ]);

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $foreignPs->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('payment_systems', ['id' => $foreignPs->id]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(int $paymentSystemId): array
    {
        return [
            ['method' => 'GET', 'url' => route('admin.setting.paymentSystem')],
            [
                'method'  => 'POST',
                'url'     => route('payment-systems.store'),
                'data'    => ['name' => 'robokassa', 'merchant_login' => 'x'],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('payment-systems.show', ['name' => 'robokassa']),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'DELETE',
                'url'     => route('payment-systems.destroy', ['payment_system' => $paymentSystemId]),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
        ];
    }

    private function grantPermission(User $user, string $permissionName, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
