<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-own-password-index и parents-and-family-cabinet §4.3.1
 * должны совпадать с фактическим UX: тот же пароль → 422 errors.password
 * под #error-message, не toast успеха и не админский skip.
 */
final class AccountOwnPasswordDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_own_password_field_error_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-own-password-index"', $html);
        $start = strpos($html, 'id="account-own-password-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-draft-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/account-settings/user/edit', $chunk);
        $this->assertStringContainsString('PUT /account-settings/user/password', $chunk);
        $this->assertStringContainsString('account.user.password.update', $chunk);
        $this->assertStringContainsString('account.user.view', $chunk);
        $this->assertStringContainsString('AccountUpdatePasswordRequest', $chunk);
        $this->assertStringContainsString('#change-pass-wrap', $chunk);
        $this->assertStringContainsString('#error-message', $chunk);
        $this->assertStringContainsString('inline-block', $chunk);
        $this->assertStringContainsString('errors.password', $chunk);
        $this->assertStringContainsString('Новый пароль совпадает с текущим.', $chunk);
        $this->assertStringContainsString('Hash::check', $chunk);
        $this->assertStringContainsString('user.password_changed', $chunk);
        $this->assertStringContainsString('Пароль успешно изменен.', $chunk);
        $this->assertStringContainsString('window.showToast', $chunk);
        $this->assertStringContainsString('showErrorModal', $chunk);
        $this->assertStringContainsString('myDocuments.blade.php', $chunk);
        $this->assertStringContainsString('parents-and-family-cabinet#account-own-password', $chunk);
        $this->assertStringContainsString('AccountOwnPasswordUpdateAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('не</b> админская смена', $chunk);
        $this->assertStringContainsString('users.password.update', $chunk);
        $this->assertStringContainsString('Throttle 5/мин здесь <b>нет</b>', $chunk);

        $this->assertStringNotContainsString('lastAppliedPasswordByUserId', $chunk);
        $this->assertStringNotContainsString('POST /admin/user/{id}/update-password</code>, право только', $chunk);
        $this->assertStringNotContainsString('showSuccessModal</code>. UI — текст в', $chunk);
        $this->assertStringNotContainsString('myDocuments.blade.php</code>) этот UX уже', $chunk);
    }

    public function test_parents_cabinet_page_matches_own_password_contract(): void
    {
        $html = $this->docFile('parents-and-family-cabinet.html');

        $this->assertStringContainsString('id="account-own-password"', $html);
        $this->assertStringContainsString('/doc#account-own-password-index', $html);
        $start = strpos($html, 'id="account-own-password"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-sex"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('AccountUpdatePasswordRequest', $chunk);
        $this->assertStringContainsString('account.user.view', $chunk);
        $this->assertStringContainsString('#change-pass-wrap', $chunk);
        $this->assertStringContainsString('#error-message', $chunk);
        $this->assertStringContainsString('inline-block', $chunk);
        $this->assertStringContainsString('errors.password', $chunk);
        $this->assertStringContainsString('Новый пароль совпадает с текущим.', $chunk);
        $this->assertStringContainsString('Пароль успешно изменен.', $chunk);
        $this->assertStringContainsString('showErrorModal', $chunk);
        $this->assertStringContainsString('myDocuments.blade.php', $chunk);
        $this->assertStringContainsString('AccountOwnPasswordUpdateAccessFeatureTest', $chunk);
        $this->assertStringContainsString('POST /admin/user/{id}/update-password', $chunk);
        $this->assertStringNotContainsString('lastAppliedPasswordByUserId', $chunk);
        $this->assertStringNotContainsString('users.password.update</code> обязательно', $chunk);
    }

    public function test_catalog_and_controller_title_mention_own_password_field_error(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="account-own-password-index"', $index);
        $this->assertStringContainsString('/doc#account-own-password-index', $index);
        $this->assertStringContainsString('свой пароль в ЛК', $index);
        $this->assertStringContainsString('свой пароль в ЛК (тот же → 422 под полем)', $controller);
        $this->assertStringContainsString("'parents-and-family-cabinet'", $controller);
    }

    public function test_live_code_matches_documented_own_password_rules(): void
    {
        $root = dirname(__DIR__, 3);
        $request = (string) file_get_contents($root.'/app/Http/Requests/User/AccountUpdatePasswordRequest.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/AccountController.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/users.blade.php');
        $routes = (string) file_get_contents($root.'/routes/web.php');

        $this->assertStringContainsString('class AccountUpdatePasswordRequest', $request);
        $this->assertStringContainsString("Hash::check(\$newPassword, \$stored)", $request);
        $this->assertStringContainsString("'password', 'Новый пароль совпадает с текущим.'", $request);
        $this->assertStringContainsString("'max:255'", $request);

        $this->assertStringContainsString('function updatePassword(AccountUpdatePasswordRequest $request)', $controller);
        $this->assertStringContainsString('AuditEvent::UserPasswordChanged', $controller);

        $applyStart = strpos($blade, 'function applyPasswordBtn');
        $applyEnd = strpos($blade, 'function cancelChangePasswordBtn');
        $this->assertNotFalse($applyStart);
        $this->assertNotFalse($applyEnd);
        $apply = substr($blade, $applyStart, $applyEnd - $applyStart);
        $this->assertStringContainsString('errors.password', $apply);
        $this->assertStringContainsString('errorMessage.textContent = msg', $apply);
        $this->assertStringContainsString("window.showToast('Пароль успешно изменен.', 'success')", $apply);
        $this->assertStringNotContainsString('showErrorModal', $apply);
        $this->assertStringNotContainsString('lastAppliedPasswordByUserId', $apply);

        $this->assertStringContainsString("style.display = 'inline-block'", $blade);
        $this->assertStringContainsString('id="error-message"', $blade);
        $this->assertStringContainsString('id="change-pass-wrap"', $blade);

        $this->assertStringContainsString("can:account.user.view", $routes);
        $this->assertStringContainsString("account-settings/user/password", $routes);
        $this->assertStringContainsString("name('password.update')", $routes);
        $this->assertDoesNotMatchRegularExpression(
            "/account-settings\\/user\\/password'[\\s\\S]{0,180}throttle:5,1/",
            $routes,
            'У своего пароля в ЛК нет throttle 5/мин — он только у админского POST'
        );
    }

    public function test_other_password_docs_do_not_claim_admin_contract_for_own_password(): void
    {
        $index = $this->docFile('index.html');
        $cabinet = $this->docFile('parents-and-family-cabinet.html');
        $staff = $this->docFile('admin-role-staff.html');

        $ownStart = strpos($index, 'id="account-own-password-index"');
        $ownEnd = strpos($index, 'id="chat-draft-index"');
        $this->assertNotFalse($ownStart);
        $this->assertNotFalse($ownEnd);
        $own = substr($index, $ownStart, $ownEnd - $ownStart);
        $this->assertStringContainsString('без <code>errors.password</code>', $own);
        $this->assertStringContainsString('errors.password', $own);

        $this->assertStringContainsString('без <code>errors.password</code>', $staff);
        $this->assertStringContainsString('/doc#account-own-password-index', $cabinet);
        $this->assertStringNotContainsString(
            'JSON 422 с <code>message</code>, без <code>errors.password</code> для своего пароля в ЛК',
            $index
        );
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
