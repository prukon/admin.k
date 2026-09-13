<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Services\SmsRuService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Native POST send/confirm: 403 без права, 422 под полями, не 500.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-phone-verify
 */
final class AccountPhoneVerifyPermissionNonAjaxSafetyNetFeatureTest extends AccountPhoneVerifyPermissionTestCase
{
    public function test_native_post_send_without_permission_is_forbidden(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);

        $response = $this->from(route('account.user.edit'))
            ->post($this->sendCodeUrl($this->user), [
                'phone' => '79001112233',
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
    }

    public function test_native_post_send_invalid_phone_returns_422_on_phone_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);

        $response = $this->from(route('account.user.edit'))
            ->post($this->sendCodeUrl($this->user), [
                'phone' => '12',
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Некорректный телефон не должен быть успехом');
        if ($response->getStatusCode() === 422) {
            $response->assertJsonPath('errors.phone.0', 'Некорректный номер. Формат 79XXXXXXXXX.');
        } else {
            $response->assertStatus(302);
            $response->assertSessionHasErrors('phone');
        }
    }

    public function test_native_post_send_with_permission_returns_json_200(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->andReturn(true);
        });

        $response = $this->from(route('account.user.edit'))
            ->post($this->sendCodeUrl($this->user), [
                'phone' => '79001112233',
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
        }
    }

    public function test_native_post_confirm_wrong_code_returns_422_on_code_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $this->user->forceFill([
            'two_factor_phone_pending' => '+'.$phoneDigits,
            'phone_change_new_code' => Hash::make('111111'),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->from(route('account.user.edit'))
            ->post($this->confirmCodeUrl($this->user), [
                'phone' => $phoneDigits,
                'code' => '000000',
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Неверный код не должен быть успехом');
        if ($response->getStatusCode() === 422) {
            $response->assertJsonPath('errors.code.0', 'Неверный код.');
        } else {
            $response->assertStatus(302);
            $response->assertSessionHasErrors('code');
        }
    }

    public function test_guest_cannot_post_send_code(): void
    {
        Auth::logout();

        $this->post($this->sendCodeUrl($this->user), [
            'phone' => '79001112233',
        ])->assertRedirect();
    }

    public function test_guest_cannot_post_confirm_code(): void
    {
        Auth::logout();

        $this->post($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => '123456',
        ])->assertRedirect();
    }

    public function test_native_post_confirm_with_permission_updates_phone_and_is_not_empty_200(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $code = '654321';
        $this->withPendingPhoneCode($this->user, $phoneDigits, $code);

        $response = $this->from(route('account.user.edit'))
            ->post($this->confirmCodeUrl($this->user), [
                'phone' => $phoneDigits,
                'code' => $code,
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
            $this->assertNotEmpty($response->json('verified_at'));
        }

        $fresh = $this->user->fresh();
        $this->assertNotNull($fresh->phone_verified_at);
        $this->assertSame('+'.$phoneDigits, $fresh->phone);
    }

    public function test_native_post_confirm_expired_code_returns_422_on_code_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $this->withPendingPhoneCode($this->user, $phoneDigits, '111111', now()->subMinute());

        $response = $this->from(route('account.user.edit'))
            ->post($this->confirmCodeUrl($this->user), [
                'phone' => $phoneDigits,
                'code' => '111111',
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        if ($response->getStatusCode() === 422) {
            $response->assertJsonPath('errors.code.0', 'Код истёк. Запросите новый.');
        } else {
            $response->assertStatus(302);
            $response->assertSessionHasErrors('code');
        }
    }

    public function test_native_post_send_already_verified_same_number_does_not_call_gateway(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $this->user->forceFill([
            'phone' => '+'.$phoneDigits,
            'phone_verified_at' => now(),
        ])->save();
        $this->mockSmsSendNever();

        $response = $this->from(route('account.user.edit'))
            ->post($this->sendCodeUrl($this->user), [
                'phone' => $phoneDigits,
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('alreadyVerified', true);
        }
    }
}
