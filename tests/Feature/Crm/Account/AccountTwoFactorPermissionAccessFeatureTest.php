<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use Illuminate\Support\Facades\Auth;

/**
 * Без account.user.two_factor.update: блок скрыт в ЛК, PATCH не меняет 2FA.
 * Без account.user.view — 403. Гость — redirect/401. Чужие методы — не 500.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-two-factor
 */
final class AccountTwoFactorPermissionAccessFeatureTest extends AccountTwoFactorPermissionTestCase
{
    public function test_gate_denies_without_permission(): void
    {
        $this->assertFalse(\Gate::forUser($this->user)->allows(self::PERMISSION));
    }

    public function test_student_edit_page_hides_two_factor_by_default(): void
    {
        $this->actingAs($this->user);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(self::LABEL, $html);
        $this->assertStringNotContainsString('name="two_factor_enabled"', $html);
    }

    public function test_guest_is_redirected_from_edit_page_and_cannot_patch_two_factor(): void
    {
        Auth::logout();

        $this->get(route('account.user.edit'))
            ->assertRedirect(route('login'));

        $web = $this->patch(route('account.user.update'), [
            '_token' => csrf_token(),
            'two_factor_enabled' => 1,
        ]);
        $this->assertContains($web->getStatusCode(), [302, 401, 419]);
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode(), 'Гость [web] не должен сохранить 2FA');

        $json = $this->patchJson(route('account.user.update'), [
            'two_factor_enabled' => 1,
        ], $this->accountAjaxHeaders());
        $this->assertContains($json->getStatusCode(), [401, 403]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode(), 'Гость [json] не должен сохранить 2FA');
    }

    public function test_manager_without_account_user_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('account.user.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->withVerifiedPhone($actor);
        $actor->forceFill(['two_factor_enabled' => 0])->save();

        $this->get(route('account.user.edit'))->assertForbidden();

        $this->patchJson(route('account.user.update'), $this->accountPayload($actor, [
            'two_factor_enabled' => true,
        ]), $this->accountAjaxHeaders())->assertForbidden();

        $this->patch(route('account.user.update'), $this->accountPayload($actor, [
            'two_factor_enabled' => 1,
            '_token' => csrf_token(),
        ]))->assertForbidden();

        $this->assertSame(0, (int) $actor->fresh()->two_factor_enabled);
    }

    public function test_account_user_update_wrong_methods_never_return_500_or_empty_success(): void
    {
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        foreach ($this->accountUserUpdateWrongMethods() as $item) {
            foreach ([true, false] as $asJson) {
                if ($asJson) {
                    $response = $this->json($item['method'], $item['url'], [
                        'two_factor_enabled' => 1,
                    ]);
                } else {
                    $payload = ['two_factor_enabled' => 1];
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
                $this->assertContains($status, [404, 405], "{$label} → {$status}");
                $this->assertNotSame(200, $status, "{$label} не должен быть пустым 200");
            }
        }

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_student_edit_page_hides_two_factor_without_permission(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(self::LABEL, $html);
        $this->assertStringNotContainsString('id="two_factor_enabled"', $html);
        $this->assertStringNotContainsString('name="two_factor_enabled"', $html);
    }

    public function test_admin_edit_page_hides_two_factor_without_permission(): void
    {
        $this->revokePermissionFromRole('admin');
        $this->asAdmin();

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(self::LABEL, $html);
        $this->assertStringNotContainsString('id="two_factor_enabled"', $html);
        $this->assertStringNotContainsString('name="two_factor_enabled"', $html);
    }

    public function test_trainer_edit_page_hides_two_factor_without_permission(): void
    {
        $this->revokePermissionFromRole('trainer');
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(self::LABEL, $html);
        $this->assertStringNotContainsString('name="two_factor_enabled"', $html);
    }

    public function test_student_cannot_enable_two_factor_without_permission(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_student_cannot_disable_two_factor_without_permission(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 1])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_admin_cannot_enable_two_factor_without_permission(): void
    {
        $this->revokePermissionFromRole('admin');
        $this->asAdmin();
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_custom_role_without_permission_cannot_toggle_two_factor(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERMISSION, $this->partner);
        $this->grantAccountUserView($actor);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->withVerifiedPhone($actor);
        $actor->forceFill(['two_factor_enabled' => 0])->save();

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString(self::LABEL, $html);
        $this->assertStringNotContainsString('name="two_factor_enabled"', $html);

        $this->patchAccountJson($this->accountPayload($actor, [
            'two_factor_enabled' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, (int) $actor->fresh()->two_factor_enabled);
    }
}
