<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

/**
 * AJAX-контракт PATCH /account-settings/user для SMS-2FA.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-two-factor
 */
final class AccountTwoFactorPermissionAjaxContractFeatureTest extends AccountTwoFactorPermissionTestCase
{
    public function test_ajax_enable_without_phone_returns_422_on_phone_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => null,
            'phone_verified_at' => null,
            'two_factor_enabled' => 0,
        ])->save();

        $this->patchAccountJson([
            'name' => $this->user->name,
            'lastname' => $this->user->lastname,
            'two_factor_enabled' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.phone.0',
                'Телефон обязателен для включения 2FA и должен быть формата 79XXXXXXXXX'
            );

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_ajax_enable_without_permission_returns_200_and_keeps_off(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Пользователь успешно обновлен');

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_ajax_enable_with_permission_returns_200_json(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Пользователь успешно обновлен');

        $this->assertSame(1, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_ajax_non_boolean_two_factor_string_is_normalized_and_does_not_500(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $response = $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => 'maybe',
        ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertSame(
            1,
            (int) $this->user->fresh()->two_factor_enabled,
            'AccountUpdateRequest нормализует непустое значение к 1 до boolean-правила'
        );
    }

    public function test_ajax_disable_with_permission_returns_200_json(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 1])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => 0,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Пользователь успешно обновлен');

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }
}
