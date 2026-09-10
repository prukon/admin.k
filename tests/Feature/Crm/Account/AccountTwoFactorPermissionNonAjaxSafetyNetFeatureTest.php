<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use Illuminate\Support\Facades\Auth;

/**
 * Non-AJAX PATCH /account-settings/user: запись в БД, не пустой 200 и не 500.
 * AccountController@update отвечает JSON даже без X-Requested-With.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/parents-and-family-cabinet.html#account-two-factor
 */
final class AccountTwoFactorPermissionNonAjaxSafetyNetFeatureTest extends AccountTwoFactorPermissionTestCase
{
    public function test_native_patch_without_permission_does_not_enable_two_factor(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $response = $this->from(route('account.user.edit'))
            ->patch(route('account.user.update'), $this->accountPayload($this->user, [
                'two_factor_enabled' => 1,
                '_token' => csrf_token(),
            ]));

        $this->assertNotSame(500, $response->status());
        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_native_patch_without_permission_does_not_disable_two_factor(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 1])->save();

        $response = $this->from(route('account.user.edit'))
            ->patch(route('account.user.update'), $this->accountPayload($this->user, [
                'two_factor_enabled' => 0,
                '_token' => csrf_token(),
            ]));

        $this->assertNotSame(500, $response->status());
        $this->assertSame(1, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_native_patch_with_permission_enables_two_factor_and_is_not_empty_200(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $response = $this->from(route('account.user.edit'))
            ->patch(route('account.user.update'), $this->accountPayload($this->user, [
                'two_factor_enabled' => 1,
                '_token' => csrf_token(),
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            'Успех non-AJAX должен сохранить 2FA (JSON 200 или redirect 302)'
        );

        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('message', 'Пользователь успешно обновлен');
        }

        if ($response->getStatusCode() === 302) {
            $response->assertRedirect(route('account.user.edit'));
        }

        $this->assertSame(1, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_native_patch_with_permission_disables_two_factor(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 1])->save();

        $response = $this->from(route('account.user.edit'))
            ->patch(route('account.user.update'), $this->accountPayload($this->user, [
                'two_factor_enabled' => 0,
                '_token' => csrf_token(),
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
        }

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_native_enable_without_phone_returns_422_on_phone_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => null,
            'phone_verified_at' => null,
            'two_factor_enabled' => 0,
        ])->save();

        $response = $this->from(route('account.user.edit'))
            ->patch(route('account.user.update'), [
                'name' => $this->user->name,
                'lastname' => $this->user->lastname,
                'two_factor_enabled' => 1,
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Включение 2FA без телефона не должно быть успехом');
        $response->assertStatus(422);
        $response->assertJsonPath(
            'errors.phone.0',
            'Телефон обязателен для включения 2FA и должен быть формата 79XXXXXXXXX'
        );

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_native_non_boolean_two_factor_string_is_normalized_and_does_not_500(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $response = $this->from(route('account.user.edit'))
            ->patch(route('account.user.update'), $this->accountPayload($this->user, [
                'two_factor_enabled' => 'maybe',
                '_token' => csrf_token(),
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
        }

        $this->assertSame(
            1,
            (int) $this->user->fresh()->two_factor_enabled,
            'AccountUpdateRequest нормализует непустое значение к 1 до boolean-правила'
        );
    }

    public function test_guest_cannot_patch_account_two_factor(): void
    {
        Auth::logout();

        $this->patch(route('account.user.update'), [
            'two_factor_enabled' => 1,
        ])->assertRedirect();
    }
}
