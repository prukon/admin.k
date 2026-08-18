<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\Account\Concerns\AccountOwnPasswordUpdateTestHelpers;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к смене своего пароля в ЛК: гость, без прав, с правами, чужой пользователь не меняется.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountOwnPasswordUpdateAccessFeatureTest extends CrmTestCase
{
    use AccountOwnPasswordUpdateTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_is_denied_when_changing_own_password(): void
    {
        $this->setKnownPassword($this->user);
        Auth::logout();

        foreach (['web', 'json'] as $mode) {
            $response = $mode === 'json'
                ? $this->putJson($this->passwordUpdateUrl(), ['password' => 'new-pass-88'])
                : $this->put($this->passwordUpdateUrl(), [
                    '_token'   => csrf_token(),
                    'password' => 'new-pass-88',
                ]);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость [{$mode}] → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode(), "Гость [{$mode}] не должен сменить пароль");
        }

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));
    }

    public function test_manager_without_account_user_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('account.user.view', $this->partner);
        $this->setKnownPassword($actor);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'new-pass-88',
        ])->assertStatus(403);

        $this->put($this->passwordUpdateUrl(), [
            '_token'   => csrf_token(),
            'password' => 'new-pass-88',
        ])->assertStatus(403);

        $this->assertTrue(Hash::check('current-pass-8', $actor->fresh()->password));
    }

    public function test_user_with_account_user_view_can_change_own_password(): void
    {
        $this->actingAs($this->user);
        $this->setKnownPassword($this->user);

        $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'new-pass-88',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('new-pass-88', $this->user->fresh()->password));
    }

    public function test_admin_with_account_user_view_can_change_own_password(): void
    {
        $this->asAdmin();
        $this->grantAccountUserView($this->user);
        $this->setKnownPassword($this->user);

        $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'admin-pass-9',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('admin-pass-9', $this->user->fresh()->password));
    }

    public function test_changing_own_password_does_not_change_another_user(): void
    {
        $this->actingAs($this->user);
        $this->setKnownPassword($this->user, 'own-pass-88');

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'password'   => 'other-pass-8',
        ]);

        $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'changed-pass9',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('changed-pass9', $this->user->fresh()->password));
        $this->assertTrue(Hash::check('other-pass-8', $other->fresh()->password));
    }

    public function test_foreign_partner_user_changing_password_does_not_touch_current_partner_user(): void
    {
        $this->setKnownPassword($this->user, 'own-pass-88');
        $this->setKnownPassword($this->foreignUser, 'foreign-pass8');
        $this->grantAccountUserView($this->foreignUser);

        $this->actingAs($this->foreignUser);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed'      => true,
        ]);

        $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'foreign-new9',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('foreign-new9', $this->foreignUser->fresh()->password));
        $this->assertTrue(Hash::check('own-pass-88', $this->user->fresh()->password));
    }

    public function test_password_endpoints_never_return_500_or_empty_success_for_wrong_methods(): void
    {
        $this->actingAs($this->user);
        $this->setKnownPassword($this->user);

        foreach ($this->passwordUpdateHttpMethods() as $item) {
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

                if ($item['method'] === 'PUT') {
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
