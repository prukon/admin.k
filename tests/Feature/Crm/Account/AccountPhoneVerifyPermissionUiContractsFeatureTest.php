<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

/**
 * JS-контракт подтверждения телефона: ошибки под полями, кнопка при disabled.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-phone-verify
 */
final class AccountPhoneVerifyPermissionUiContractsFeatureTest extends AccountPhoneVerifyPermissionTestCase
{
    public function test_blade_gates_button_and_modal_with_phone_verify_permission(): void
    {
        $blade = $this->accountUsersBlade();

        $this->assertStringContainsString("auth()->user()->can('account.user.phone.verify')", $blade);
        $this->assertStringContainsString("@can('account.user.phone.verify')", $blade);
        $this->assertStringContainsString('id="verify-phone-btn"', $blade);
        $this->assertStringContainsString('id="phoneCodeModal"', $blade);
        $this->assertStringContainsString('id="phone-verify-error"', $blade);
        $this->assertStringContainsString('@error(\'phone\')', $blade);
    }

    public function test_update_verify_ui_does_not_hide_button_when_phone_is_disabled(): void
    {
        $js = $this->phoneVerifyJs();

        $this->assertStringContainsString('function updateVerifyUI()', $js);
        $this->assertStringNotContainsString('$phone.is(\':disabled\')', $js);
        $this->assertStringContainsString('isComplete()', $js);
        $this->assertStringContainsString('showBtn()', $js);
    }

    public function test_send_and_confirm_attach_422_to_phone_and_code_fields(): void
    {
        $js = $this->phoneVerifyJs();

        $this->assertStringContainsString('function fieldError(xhr, field, fallback)', $js);
        $this->assertStringContainsString("json.errors[field][0]", $js);
        $this->assertStringContainsString('setPhoneVerifyError(fieldError(xhr, \'phone\'', $js);
        $this->assertStringContainsString('setCodeError(fieldError(xhr, \'code\'', $js);
        $this->assertStringContainsString('$phoneErr.removeClass(\'d-none\').text(msg)', $js);
        $this->assertStringNotContainsString('alert(msg)', $js);
    }

    public function test_send_click_and_resend_are_two_ajax_paths_and_both_use_field_errors(): void
    {
        $js = $this->phoneVerifyJs();

        $this->assertStringContainsString('$verifyBtn.on(\'click\'', $js);
        $this->assertStringContainsString('$resend.on(\'click\'', $js);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($js, "route('account.user.phoneSendCode'"),
            'Кнопка «Подтвердить» и «Отправить код ещё раз» должны бить в phoneSendCode'
        );
        $this->assertStringContainsString('setPhoneVerifyError(fieldError(xhr, \'phone\'', $js);
        $this->assertStringContainsString('setCodeError(fieldError(xhr, \'phone\'', $js);
        $this->assertStringNotContainsString('alert(msg)', $js);
    }

    public function test_update_verify_ui_hides_button_when_incomplete_or_verified_not_when_disabled(): void
    {
        $js = $this->phoneVerifyJs();
        $start = strpos($js, 'function updateVerifyUI()');
        $this->assertNotFalse($start);
        $fnEnd = strpos($js, '// События Inputmask', $start);
        $this->assertNotFalse($fnEnd);
        $fn = substr($js, $start, $fnEnd - $start);

        $this->assertStringNotContainsString('$phone.is(\':disabled\')', $fn);
        $this->assertStringNotContainsString(':disabled', $fn);
        $this->assertStringContainsString('if (isVerified())', $fn);
        $this->assertStringContainsString('hideBtn()', $fn);
        $this->assertStringContainsString('if (isComplete())', $fn);
        $this->assertStringContainsString('showBtn()', $fn);
        $verifiedPos = strpos($fn, 'if (isVerified())');
        $completePos = strpos($fn, 'if (isComplete())');
        $this->assertNotFalse($verifiedPos);
        $this->assertNotFalse($completePos);
        $this->assertLessThan($completePos, $verifiedPos);
    }

    public function test_confirm_success_updates_status_without_page_reload(): void
    {
        $js = $this->phoneVerifyJs();
        $start = strpos($js, '$confirm.on(\'click\'');
        $this->assertNotFalse($start);
        $chunk = substr($js, $start);

        $this->assertStringContainsString('$codeModal.modal(\'hide\')', $chunk);
        $this->assertStringContainsString("attr('data-verified', '1')", $chunk);
        $this->assertStringContainsString('hideBtn()', $chunk);
        $this->assertStringContainsString('$phone.prop(\'disabled\', true)', $chunk);
        $this->assertStringContainsString('phone-edit-locked-note', $chunk);
        $this->assertStringContainsString('setCodeError(fieldError(xhr, \'code\'', $chunk);
        $this->assertStringNotContainsString('location.reload()', $chunk);
        $this->assertStringNotContainsString('alert(msg)', $chunk);
    }

    public function test_client_rejects_incomplete_phone_and_short_code_before_ajax(): void
    {
        $js = $this->phoneVerifyJs();

        $this->assertStringContainsString("setPhoneVerifyError('Некорректный номер. Формат 79XXXXXXXXX.')", $js);
        $this->assertStringContainsString("setCodeError('Введите корректный код.')", $js);
        $this->assertStringContainsString('/^7\\d{10}$/', $js);
        $this->assertStringContainsString('/^\\d{4,8}$/', $js);
    }

    private function accountUsersBlade(): string
    {
        $path = resource_path('views/account/users.blade.php');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function phoneVerifyJs(): string
    {
        $js = $this->accountUsersBlade();
        $start = strpos($js, 'var $verifyBtn = $(\'#verify-phone-btn\')');
        $this->assertNotFalse($start);
        $end = strpos($js, '@endcan', $start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        return substr($js, $start, $end - $start);
    }
}
