<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Services\SmsRuService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

/**
 * С правом account.user.phone.verify: кнопка в ЛК, send/confirm 200.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-phone-verify
 */
final class AccountPhoneVerifyPermissionFullAccessFeatureTest extends AccountPhoneVerifyPermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->grantVerifyToBaseRoles();
        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->andReturn(true);
        });
    }

    public function test_gate_allows_with_permission(): void
    {
        $this->assertTrue(Gate::forUser($this->user)->allows(self::PERMISSION));
        $this->assertTrue(Gate::forUser($this->user)->allows('verify-phone', $this->user));
    }

    public function test_student_edit_page_shows_verify_button_with_permission(): void
    {
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('id="verify-phone-btn"', false)
            ->assertSee('id="phoneCodeModal"', false)
            ->assertSee('Подтвердить', false);
    }

    public function test_admin_edit_page_shows_verify_button_with_permission(): void
    {
        $this->asAdmin();
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('id="verify-phone-btn"', false);
    }

    public function test_trainer_edit_page_shows_verify_button_with_permission(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $trainer->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();
        $this->actingAs($trainer);

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('id="verify-phone-btn"', false);
    }

    public function test_student_can_send_and_confirm_with_permission(): void
    {
        $this->actingAs($this->user);
        $phoneDigits = '79001234567';

        $this->postJson($this->sendCodeUrl($this->user), [
            'phone' => $phoneDigits,
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $code = '654321';
        $this->user->forceFill([
            'two_factor_phone_pending' => '+'.$phoneDigits,
            'phone_change_new_code' => Hash::make($code),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->postJson($this->confirmCodeUrl($this->user), [
            'phone' => $phoneDigits,
            'code' => $code,
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $this->user->fresh();
        $this->assertNotNull($fresh->phone_verified_at);
        $this->assertSame('+'.$phoneDigits, $fresh->phone);
    }

    public function test_verify_button_shows_without_phone_update_when_number_is_present(): void
    {
        $this->revokePhoneUpdateFromRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $this->assertFalse(Gate::forUser($this->user)->allows('account.user.phone.update'));
        $this->assertTrue(Gate::forUser($this->user)->allows(self::PERMISSION));

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $this->assertStringContainsString('id="verify-phone-btn"', $html);
        $this->assertStringContainsString('Нет прав на', $html);
        $this->assertStringContainsString('изменение телефона', $html);
        $this->assertStringContainsString('disabled', $this->phoneInputTag($html));
    }

    public function test_superadmin_sees_verify_button_without_role_permission(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('id="verify-phone-btn"', false);
    }

    public function test_admin_can_send_and_confirm_for_same_partner_student(): void
    {
        $student = $this->createUserWithRole('user', $this->partner);
        $this->asAdmin();
        $phoneDigits = '79001234567';
        $code = '112233';

        $this->postJson($this->sendCodeUrl($student), [
            'phone' => $phoneDigits,
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withPendingPhoneCode($student, $phoneDigits, $code);

        $this->postJson($this->confirmCodeUrl($student), [
            'phone' => $phoneDigits,
            'code' => $code,
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $student->fresh();
        $this->assertNotNull($fresh->phone_verified_at);
        $this->assertSame('+'.$phoneDigits, $fresh->phone);
    }

    public function test_custom_role_with_permission_can_send_code(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERMISSION, $this->partner);
        $this->grantAccountUserView($actor);
        $this->grantPermissionToActor($actor);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson($this->sendCodeUrl($actor), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_trainer_can_send_code_for_own_profile(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson($this->sendCodeUrl($trainer), [
            'phone' => '79001112233',
        ], $this->accountAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
