<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Без account.user.phone.verify: кнопки нет, send/confirm → 403.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-phone-verify
 */
final class AccountPhoneVerifyPermissionAccessFeatureTest extends AccountPhoneVerifyPermissionTestCase
{
    public function test_gate_denies_without_permission(): void
    {
        $this->assertFalse(Gate::forUser($this->user)->allows(self::PERMISSION));
        $this->assertFalse(Gate::forUser($this->user)->allows('verify-phone', $this->user));
    }

    public function test_student_edit_page_hides_verify_button_by_default(): void
    {
        $this->actingAs($this->user);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);
        $this->assertStringNotContainsString('id="phoneCodeModal"', $html);
    }

    public function test_guest_is_redirected_and_cannot_send_or_confirm_code(): void
    {
        Auth::logout();

        $this->get(route('account.user.edit'))
            ->assertRedirect(route('login'));

        $web = $this->post($this->sendCodeUrl($this->user), [
            '_token' => csrf_token(),
            'phone' => '79001112233',
        ]);
        $this->assertContains($web->getStatusCode(), [302, 401, 419]);
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());

        $json = $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders());
        $this->assertContains($json->getStatusCode(), [401, 403]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());

        $confirm = $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => '123456',
        ], $this->accountAjaxHeaders());
        $this->assertContains($confirm->getStatusCode(), [401, 403]);
        $this->assertNotSame(200, $confirm->getStatusCode());
    }

    public function test_manager_without_account_user_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('account.user.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('account.user.edit'))->assertForbidden();
        $this->postJson($this->sendCodeUrl($actor), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())->assertForbidden();
        $this->postJson($this->confirmCodeUrl($actor), [
            'phone' => '79001112233',
            'code' => '123456',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_phone_verify_wrong_methods_never_return_500_or_empty_success(): void
    {
        $this->actingAs($this->user);

        foreach ($this->phoneVerifyWrongMethods($this->user) as $item) {
            foreach ([true, false] as $asJson) {
                if ($asJson) {
                    $response = $this->json($item['method'], $item['url'], [
                        'phone' => '79001112233',
                        'code' => '123456',
                    ]);
                } else {
                    $payload = [
                        'phone' => '79001112233',
                        'code' => '123456',
                    ];
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
    }

    public function test_student_cannot_send_or_confirm_without_permission(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())->assertForbidden();

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => '123456',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_admin_cannot_send_without_permission(): void
    {
        $this->revokePermissionFromRole('admin');
        $this->asAdmin();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_trainer_edit_page_hides_verify_without_permission(): void
    {
        $this->revokePermissionFromRole('trainer');
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);
        $this->assertStringNotContainsString('id="phoneCodeModal"', $html);
    }

    public function test_custom_role_without_permission_cannot_send_code(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERMISSION, $this->partner);
        $this->grantAccountUserView($actor);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);

        $this->postJson($this->sendCodeUrl($actor), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_student_cannot_confirm_another_users_phone_even_with_permission(): void
    {
        $this->grantPermissionToRole('user');
        $other = $this->createUserWithRole('user', $this->partner);
        $this->actingAs($this->user);

        $this->postJson($this->sendCodeUrl($other), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_trainer_cannot_send_code_for_another_same_partner_user(): void
    {
        $this->grantPermissionToRole('trainer');
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $other = $this->createUserWithRole('user', $this->partner);
        $this->actingAs($trainer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson($this->sendCodeUrl($other), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_admin_cannot_send_code_for_another_partners_user(): void
    {
        $this->grantPermissionToRole('admin');
        $this->asAdmin();
        $this->mockSmsSendNever();

        $response = $this->postJson($this->sendCodeUrl($this->foreignUser), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Админ не должен подтверждать телефон чужой школы');
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_guest_cannot_confirm_code(): void
    {
        Auth::logout();

        $json = $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => '123456',
        ], $this->accountAjaxHeaders());
        $this->assertContains($json->getStatusCode(), [401, 403]);
        $this->assertNotSame(200, $json->getStatusCode());
    }
}
