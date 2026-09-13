<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

/**
 * Blade: кнопка и модалка только при account.user.phone.verify.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-phone-verify
 */
final class AccountPhoneVerifyPermissionMarkupFeatureTest extends AccountPhoneVerifyPermissionTestCase
{
    public function test_first_open_without_permission_has_no_button_or_modal(): void
    {
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);
        $this->assertStringNotContainsString('id="phoneCodeModal"', $html);
        $this->assertStringNotContainsString('id="phone-code-input"', $html);
    }

    public function test_first_open_with_permission_and_unverified_phone_shows_button(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('id="verify-phone-btn"', $html);
        $this->assertStringContainsString('id="phoneCodeModal"', $html);
        $this->assertStringContainsString('id="phone-verify-error"', $html);
        $this->assertStringContainsString('id="phone-code-error"', $html);
        $this->assertStringNotContainsString('d-none" id="verify-phone-btn"', $html);
    }

    public function test_verified_phone_hides_button_even_with_permission(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => now(),
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);
        $this->assertStringContainsString('id="phoneCodeModal"', $html);
        $this->assertStringContainsString('Подтверждён', $html);
    }

    public function test_button_renders_when_phone_update_is_missing_and_number_exists(): void
    {
        $this->grantPermissionToRole('user');
        $this->revokePhoneUpdateFromRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $this->assertStringContainsString('id="verify-phone-btn"', $html);
        $phoneTag = $this->phoneInputTag($html);
        $this->assertStringContainsString('disabled', $phoneTag);
        $this->assertStringNotContainsString('d-none" id="verify-phone-btn"', $html);
        $this->assertStringContainsString('Нет прав на', $html);
    }

    public function test_phone_then_button_then_icon_order_on_first_open(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $phonePos = strpos($html, 'id="phone"');
        $btnPos = strpos($html, 'id="verify-phone-btn"');
        $iconPos = strpos($html, 'id="phone-verified-icon"');
        $this->assertNotFalse($phonePos);
        $this->assertNotFalse($btnPos);
        $this->assertNotFalse($iconPos);
        $this->assertLessThan($btnPos, $phonePos, 'Поле телефона должно быть до кнопки');
        $this->assertLessThan($iconPos, $btnPos, 'Кнопка должна быть до иконки статуса');
    }

    public function test_verified_phone_field_is_disabled_and_shows_lock_note(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => now(),
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $phoneTag = $this->phoneInputTag($html);
        $this->assertStringContainsString('disabled', $phoneTag);
        $this->assertStringContainsString('id="phone-edit-locked-note"', $html);
        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);
    }

    public function test_reopening_edit_page_keeps_verify_button_when_unverified(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $first = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $second = $this->get(route('account.user.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('id="verify-phone-btn"', $first);
        $this->assertStringContainsString('id="verify-phone-btn"', $second);
        $this->assertStringNotContainsString('d-none" id="verify-phone-btn"', $second);
    }

    public function test_without_permission_complete_number_does_not_force_verify_button(): void
    {
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => '+79001112233',
            'phone_verified_at' => null,
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="verify-phone-btn"', $html);
        $this->assertStringNotContainsString('id="phoneCodeModal"', $html);
    }

    public function test_empty_unverified_phone_with_permission_still_renders_button_for_js(): void
    {
        $this->grantPermissionToRole('user');
        $this->actingAs($this->user);
        $this->user->forceFill([
            'phone' => null,
            'phone_verified_at' => null,
        ])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $this->assertStringContainsString('id="verify-phone-btn"', $html);
        $this->assertStringContainsString('function isComplete()', $html);
    }
}
