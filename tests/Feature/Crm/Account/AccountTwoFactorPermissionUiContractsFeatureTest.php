<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

/**
 * JS-контракт SMS-2FA в ЛК: два submit-пути (#userUpdateForm), ошибки под видимым полем.
 *
 * UX-баг до фикса: jQuery `[name=two_factor_enabled]` попадал в hidden 0, и 422
 * рисовалась не под чекбоксом.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-two-factor
 */
final class AccountTwoFactorPermissionUiContractsFeatureTest extends AccountTwoFactorPermissionTestCase
{
    public function test_ajax_submit_prevents_native_post_and_patches_json(): void
    {
        $ajax = $this->updateUserDataJs();

        $this->assertStringContainsString('.off(\'submit\')', $ajax);
        $offPos = strpos($ajax, '.off(\'submit\')');
        $onPos = strpos($ajax, '.on(\'submit\'');
        $this->assertNotFalse($offPos);
        $this->assertNotFalse($onPos);
        $this->assertLessThan($onPos, $offPos, 'updateUserData должен снять прежний submit (клиентская 2FA-проверка) до AJAX');

        $this->assertStringContainsString('e.preventDefault()', $ajax);
        $this->assertStringContainsString("type: 'PATCH'", $ajax);
        $this->assertStringContainsString("headers: {'Accept': 'application/json'}", $ajax);
        $this->assertStringContainsString('$form.serialize()', $ajax);
        $this->assertStringContainsString('location.reload()', $ajax);
        $this->assertStringContainsString('xhr.status === 422', $ajax);
    }

    public function test_ajax_422_attaches_error_to_visible_two_factor_checkbox_not_hidden(): void
    {
        $ajax = $this->updateUserDataJs();
        $errorStart = strpos($ajax, 'Object.keys(errors).forEach');
        $this->assertNotFalse($errorStart);
        $error = substr($ajax, $errorStart);

        $this->assertStringContainsString(
            '$form.find(\'[name="\' + safe + \'"]:not([type="hidden"])\')',
            $error,
            '422 по two_factor_enabled должна цепляться к чекбоксу, не к hidden 0'
        );
        $this->assertStringContainsString('$form.find(\'#\' + field)', $error);
        $visiblePos = strpos($error, ':not([type="hidden"])');
        $idPos = strpos($error, '$form.find(\'#\' + field)');
        $fallbackPos = strrpos($error, '$form.find(\'[name="\' + safe + \'"]\')');
        $this->assertNotFalse($visiblePos);
        $this->assertNotFalse($idPos);
        $this->assertNotFalse($fallbackPos);
        $this->assertLessThan($idPos, $visiblePos);
        $this->assertLessThan($fallbackPos, $idPos);
        $this->assertStringContainsString('invalid-feedback', $error);
        $this->assertStringContainsString('insertAfter($input)', $error);
    }

    public function test_client_two_factor_submit_path_detects_admin_by_role_name_not_numeric_id(): void
    {
        $js = $this->accountUsersBlade();
        $start = strpos($js, '// 2fa:');
        $end = strpos($js, 'function updateUserData()');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($js, $start, $end - $start);

        $this->assertStringContainsString('$user->hasRole(\'admin\')', $chunk);
        $this->assertStringNotContainsString('role_id === 10', $chunk);
        $this->assertStringContainsString('$(\'#two_factor_enabled\')', $chunk);
        $this->assertStringContainsString('$(\'#userUpdateForm\').on(\'submit\'', $chunk);
    }

    public function test_forced_admin_lock_in_blade_uses_has_role_admin(): void
    {
        $blade = $this->accountUsersBlade();
        $start = strpos($blade, '@can(\'account.user.two_factor.update\')');
        $end = strpos($blade, '@endcan', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($blade, $start, $end - $start);

        $this->assertStringContainsString('$user->hasRole(\'admin\')', $chunk);
        $this->assertStringNotContainsString('role_id === 10', $chunk);
        $this->assertStringContainsString('$forcedForThisUser', $chunk);
        $this->assertStringContainsString('value="1"', $chunk);
        $this->assertStringContainsString('value="0"', $chunk);
    }

    private function accountUsersBlade(): string
    {
        $path = resource_path('views/account/users.blade.php');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function updateUserDataJs(): string
    {
        $js = $this->accountUsersBlade();
        $start = strpos($js, 'function updateUserData()');
        $end = strpos($js, 'changePasswordBtn();');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        return substr($js, $start, $end - $start);
    }
}
