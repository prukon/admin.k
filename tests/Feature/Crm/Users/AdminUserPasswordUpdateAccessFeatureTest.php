<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\AdminUserPasswordUpdateTestHelpers;

/**
 * Доступ к ручной смене пароля: гость, без прав, с правами, чужой партнёр.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUserPasswordUpdateAccessFeatureTest extends CrmTestCase
{
    use AdminUserPasswordUpdateTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureArrayCacheForThrottle();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_is_denied_when_changing_admin_password(): void
    {
        $target = $this->makePasswordTarget();
        Auth::logout();

        foreach (['web', 'json'] as $mode) {
            $response = $mode === 'json'
                ? $this->postJson($this->passwordUpdateUrl($target), ['password' => 'new-pass-88'])
                : $this->post($this->passwordUpdateUrl($target), [
                    '_token'   => csrf_token(),
                    'password' => 'new-pass-88',
                ]);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость [{$mode}] → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }

        $target->refresh();
        $this->assertTrue(Hash::check('current-pass-8', $target->password));
    }

    public function test_manager_without_password_update_permission_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('users.password.update', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [
            'password' => 'new-pass-88',
        ])->assertStatus(403);

        $this->post($this->passwordUpdateUrl($target), [
            '_token'   => csrf_token(),
            'password' => 'new-pass-88',
        ])->assertStatus(403);

        $target->refresh();
        $this->assertTrue(Hash::check('current-pass-8', $target->password));
    }

    public function test_manager_without_users_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->grantPasswordUpdate($actor);

        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [
            'password' => 'new-pass-88',
        ])->assertStatus(403);
    }

    public function test_admin_with_password_permission_can_change_password(): void
    {
        $this->asAdmin();
        $this->grantPasswordChangeAccess($this->user);

        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [
            'password' => 'new-pass-88',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $target->refresh();
        $this->assertTrue(Hash::check('new-pass-88', $target->password));
    }

    public function test_foreign_partner_user_is_not_found_when_changing_password(): void
    {
        $this->asAdmin();
        $this->grantPasswordChangeAccess($this->user);

        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->adminRoleId(),
            'password'   => 'current-pass-8',
        ]);

        $this->postJson($this->passwordUpdateUrl($foreign), [
            'password' => 'new-pass-88',
        ])->assertStatus(404);

        $foreign->refresh();
        $this->assertTrue(Hash::check('current-pass-8', $foreign->password));
    }

    public function test_password_endpoints_never_return_500_or_empty_success_for_wrong_methods(): void
    {
        $this->asAdmin();
        $this->grantPasswordChangeAccess($this->user);
        $target = $this->makePasswordTarget();

        foreach ($this->passwordUpdateHttpMethods($target) as $item) {
            foreach ([true, false] as $asJson) {
                if ($asJson) {
                    $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
                } else {
                    $payload = $item['data'] ?? [];
                    if ($item['method'] !== 'GET') {
                        $payload['_token'] = csrf_token();
                    }
                    $response = $this->call(
                        $item['method'],
                        $item['url'],
                        $payload,
                        [],
                        [],
                        ['HTTP_ACCEPT' => 'text/html']
                    );
                }

                $status = $response->getStatusCode();
                $label = ($asJson ? 'JSON' : 'web')." {$item['method']} {$item['url']}";

                $this->assertNotSame(500, $status, "{$label} → 500");

                if ($item['method'] === 'POST') {
                    $this->assertContains($status, [200, 302, 422], "{$label} → {$status}");
                    if ($status === 200) {
                        $this->assertNotSame('', trim((string) $response->getContent()));
                    }
                    continue;
                }

                $this->assertContains($status, [404, 405], "{$label} → {$status}");
                $this->assertNotSame(200, $status, "{$label} не должен быть пустым 200");
            }
        }
    }
}
