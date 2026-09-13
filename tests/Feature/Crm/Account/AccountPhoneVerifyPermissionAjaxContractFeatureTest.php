<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Services\SmsRuService;
use Illuminate\Support\Facades\Hash;

/**
 * AJAX-контракт send/confirm: 422 под полями, 403 без права, SMS-ошибка на phone.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-phone-verify
 */
final class AccountPhoneVerifyPermissionAjaxContractFeatureTest extends AccountPhoneVerifyPermissionTestCase
{
    public function test_ajax_send_without_permission_returns_403(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_ajax_send_invalid_phone_returns_422_on_phone_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '123',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Некорректный номер. Формат 79XXXXXXXXX.');
    }

    public function test_ajax_send_empty_phone_returns_422_on_phone_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);

        $this->postJson($this->sendCodeUrl($this->user), [], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Укажите номер телефона.');
    }

    public function test_ajax_send_gateway_error_returns_422_on_phone_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->andReturn(
                'SMS error: Вы не подключили данного оператора на данном отправителе [204]'
            );
        });

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.phone.0',
                SmsRuService::USER_ERROR_OPERATOR_NOT_CONNECTED
            );
    }

    public function test_ajax_send_with_permission_returns_200(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn(true);
        });

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '+7 (900) 111-22-33',
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_ajax_confirm_wrong_code_returns_422_on_code_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $this->user->forceFill([
            'two_factor_phone_pending' => '+'.$phoneDigits,
            'phone_change_new_code' => Hash::make('111111'),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => $phoneDigits,
            'code' => '222222',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Неверный код.');
    }

    public function test_ajax_confirm_invalid_code_format_returns_422_on_code_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => 'ab',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Введите корректный код.');
    }

    public function test_ajax_confirm_pending_mismatch_returns_422_on_phone_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'two_factor_phone_pending' => '+79009998877',
            'phone_change_new_code' => Hash::make('111111'),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => '111111',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Этот номер не ожидается к подтверждению.');
    }

    public function test_ajax_confirm_without_permission_returns_403(): void
    {
        $this->revokePermissionFromRole('user');
        $this->actingAs($this->user);

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => '123456',
        ], $this->accountAjaxHeaders())->assertForbidden();
    }

    public function test_ajax_send_already_verified_same_number_does_not_call_gateway(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $this->user->forceFill([
            'phone' => '+'.$phoneDigits,
            'phone_verified_at' => now(),
        ])->save();
        $this->mockSmsSendNever();

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => $phoneDigits,
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('alreadyVerified', true);
    }

    public function test_ajax_send_already_verified_different_number_sends_sms(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => now(),
        ])->save();
        $this->mockSmsSendOk();

        $response = $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '79009998877',
        ], $this->accountAjaxHeaders());
        $response->assertOk()->assertJsonPath('success', true);

        $this->assertNull($response->json('alreadyVerified'));
    }

    public function test_ajax_send_normalizes_eight_prefix_phone(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->mockSmsSendOk();

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => '89001112233',
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('+79001112233', $this->user->fresh()->two_factor_phone_pending);
    }

    public function test_ajax_confirm_expired_code_returns_422_on_code_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $this->withPendingPhoneCode($this->user, $phoneDigits, '111111', now()->subMinute());

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => $phoneDigits,
            'code' => '111111',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Код истёк. Запросите новый.');
    }

    public function test_ajax_confirm_when_code_was_not_requested_returns_422_on_code_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $this->user->forceFill([
            'two_factor_phone_pending' => '+'.$phoneDigits,
            'phone_change_new_code' => null,
            'phone_change_new_expires_at' => null,
        ])->save();

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => $phoneDigits,
            'code' => '111111',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Код не запрошен.');
    }

    public function test_ajax_confirm_code_longer_than_eight_digits_returns_422_on_code_field(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => '79001112233',
            'code' => '123456789',
        ], $this->accountAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Введите корректный код.');
    }

    public function test_ajax_confirm_with_permission_returns_verified_at(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $phoneDigits = '79001112233';
        $code = '654321';
        $this->withPendingPhoneCode($this->user, $phoneDigits, $code);

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => $phoneDigits,
            'code' => $code,
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'verified_at']);

        $fresh = $this->user->fresh();
        $this->assertNotNull($fresh->phone_verified_at);
        $this->assertSame('+'.$phoneDigits, $fresh->phone);
    }
}
