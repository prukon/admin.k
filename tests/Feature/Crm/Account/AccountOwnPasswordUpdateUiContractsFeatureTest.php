<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use Tests\Feature\Crm\Account\Concerns\AccountOwnPasswordUpdateTestHelpers;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка и JS-контракт смены своего пароля в ЛК:
 * скрытый блок при первом открытии, ошибка под #error-message, без showErrorModal.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountOwnPasswordUpdateUiContractsFeatureTest extends CrmTestCase
{
    use AccountOwnPasswordUpdateTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_edit_page_keeps_password_wrap_and_error_hidden_on_first_open(): void
    {
        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertStringContainsString('id="change-password-btn"', $html);
        $this->assertStringContainsString('id="apply-password-btn"', $html);
        $this->assertStringContainsString('id="cancel-change-password-btn"', $html);
        $this->assertStringContainsString('id="new-password"', $html);
        $this->assertStringContainsString('id="error-message"', $html);
        $this->assertMatchesRegularExpression(
            '/id="change-pass-wrap"[^>]*style="display:\s*none;?"/',
            $html,
            'Блок смены пароля при первом открытии страницы должен быть скрыт'
        );
        $this->assertMatchesRegularExpression(
            '/id="error-message"[^>]*style="display:\s*none;?"/',
            $html,
            'Ошибка под полем при первом открытии не должна быть видна'
        );
        $this->assertStringContainsString('Пароль должен быть не', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="error-message"[^>]*style="display:\s*block;?"/',
            $html
        );
    }

    public function test_edit_page_shows_own_password_controls_without_admin_password_permission(): void
    {
        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="change-password-btn"', $html);
        $this->assertStringContainsString('Изменить пароль', $html);
        $this->assertStringNotContainsString('Нет прав на изменение пароля', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="change-password-btn"[^>]*pe-none/',
            $html,
            'Свой пароль в ЛК не зависит от users.password.update — кнопка не должна быть заблокирована'
        );
    }

    public function test_opening_password_form_hides_previous_error_and_shows_wrap(): void
    {
        $js = $this->accountOwnPasswordJs();
        $openStart = strpos($js, 'function changePasswordBtn');
        $openEnd = strpos($js, 'function applyPasswordBtn');
        $this->assertNotFalse($openStart);
        $this->assertNotFalse($openEnd);
        $this->assertGreaterThan($openStart, $openEnd);

        $chunk = substr($js, $openStart, $openEnd - $openStart);
        $this->assertStringContainsString("change-pass-wrap').style.display = 'inline-block'", $chunk);
        $this->assertStringContainsString("error-message').style.display = 'none'", $chunk);
        $this->assertStringNotContainsString('showErrorModal', $chunk);
    }

    public function test_cancel_hides_password_wrap_and_error_without_resetting_profile_form(): void
    {
        $js = $this->accountOwnPasswordJs();
        $start = strpos($js, 'function cancelChangePasswordBtn');
        $end = strpos($js, 'function showPassword');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $chunk = substr($js, $start, $end - $start);
        $this->assertStringContainsString("change-password-btn').style.display = 'inline-block'", $chunk);
        $this->assertStringContainsString("change-pass-wrap').style.display = 'none'", $chunk);
        $this->assertStringContainsString("error-message').style.display = 'none'", $chunk);
        $this->assertStringNotContainsString('userUpdateForm', $chunk);
        $this->assertStringNotContainsString('location.reload', $chunk);
    }

    public function test_apply_js_shows_server_password_error_under_field_instead_of_success_toast(): void
    {
        $js = $this->accountOwnPasswordJs();
        $this->assertSame(
            1,
            substr_count($js, 'function applyPasswordBtn'),
            'На странице учётной записи один JS-путь смены пароля'
        );

        $start = strpos($js, 'function applyPasswordBtn');
        $end = strpos($js, 'function cancelChangePasswordBtn');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $apply = substr($js, $start, $end - $start);

        $this->assertStringContainsString("'Accept': 'application/json'", $apply);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $apply);
        $this->assertStringContainsString('method: \'PUT\'', $apply);

        $this->assertStringContainsString('newPassword.length < 8', $apply);
        $this->assertStringContainsString(
            "errorMessage.textContent = 'Пароль должен быть не менее 8 символов'",
            $apply
        );

        $successStart = strpos($apply, 'if (result.ok && result.data && result.data.success)');
        $errorStart = strpos($apply, 'var fieldErrors');
        $this->assertNotFalse($successStart);
        $this->assertNotFalse($errorStart);
        $this->assertGreaterThan($successStart, $errorStart);

        $success = substr($apply, $successStart, $errorStart - $successStart);
        $this->assertStringContainsString("change-pass-wrap').style.display = 'none'", $success);
        $this->assertStringContainsString("new-password').value = ''", $success);
        $this->assertStringContainsString("window.showToast('Пароль успешно изменен.', 'success')", $success);
        $this->assertStringNotContainsString('showSuccessModal', $success);
        $this->assertStringNotContainsString('showErrorModal', $success);

        $error = substr($apply, $errorStart);
        $this->assertStringContainsString('errors.password', $error);
        $this->assertStringContainsString('errorMessage.textContent = msg', $error);
        $this->assertStringContainsString("errorMessage.style.display = 'block'", $error);
        $this->assertStringNotContainsString("change-pass-wrap').style.display = 'none'", $error);
        $this->assertStringNotContainsString('showToast', $error);
        $this->assertStringNotContainsString('showErrorModal', $error);
        $this->assertStringNotContainsString('showSuccessModal', $error);
    }

    private function accountOwnPasswordJs(): string
    {
        $path = resource_path('views/account/users.blade.php');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
