<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#password-reset-unique-index совпадает с password-reset.html
 * и PAGE_TITLES.
 */
final class PasswordResetDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_password_reset_unique_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="password-reset-unique-index"', $html);
        $start = strpos($html, 'id="password-reset-unique-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-manual-paid-zero-price-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('POST /password/email', $chunk);
        $this->assertStringContainsString('password_reset_tokens', $chunk);
        $this->assertStringContainsString('UniqueConstraintViolationException', $chunk);
        $this->assertStringContainsString('1062', $chunk);
        $this->assertStringContainsString('RESET_LINK_SENT', $chunk);
        $this->assertStringContainsString('не 500', $chunk);
        $this->assertStringContainsString('disabled', $chunk);
        $this->assertStringContainsString('setTimeout(0)', $chunk);
        $this->assertStringContainsString('SendPasswordResetLinkRequest', $chunk);
        $this->assertStringContainsString('Забыл пароль.', $chunk);
        $this->assertStringContainsString('guest', $chunk);
        $this->assertStringContainsString('OpsMonitor', $chunk);
        $this->assertStringContainsString('password.update', $chunk);
        $this->assertStringContainsString('/docs/documentation/password-reset', $chunk);
        $this->assertStringContainsString('PasswordResetAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PasswordResetAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('PasswordResetNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PasswordResetMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('PasswordResetUxFeatureTest', $chunk);
        $this->assertStringContainsString('PasswordResetFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('PasswordResetDocumentationContractTest', $chunk);
        $this->assertStringNotContainsString('users.email unique', $chunk);
        $this->assertStringContainsString('/doc#password-reset-unique-index', $html);
    }

    public function test_password_reset_page_matches_code_contract(): void
    {
        $html = $this->docFile('password-reset.html');
        $this->assertStringContainsString('/doc#password-reset-unique-index', $html);
        $this->assertStringContainsString('password.email', $html);
        $this->assertStringContainsString('password_reset_tokens', $html);
        $this->assertStringContainsString('UniqueConstraintViolationException', $html);
        $this->assertStringContainsString('SendPasswordResetLinkRequest', $html);
        $this->assertStringContainsString('ForgotPasswordController::sendResetLinkEmail', $html);
        $this->assertStringContainsString('Password::RESET_LINK_SENT', $html);
        $this->assertStringContainsString('Укажите email.', $html);
        $this->assertStringContainsString('Мы отправили вам ссылку для сброса пароля на ваш email.', $html);
        $this->assertStringContainsString('Забыл пароль.', $html);
        $this->assertStringContainsString("middleware('guest')", $html);
        $this->assertStringContainsString('max:255', $html);
        $this->assertStringContainsString('password.update', $html);
        $this->assertStringContainsString('/login', $html);
        $this->assertStringContainsString('Ваш пароль был сброшен!', $html);
        $this->assertStringContainsString('data-submitting', $html);
        $this->assertStringContainsString('setTimeout', $html);
        $this->assertStringContainsString('#forgot-password-submit', $html);
        $this->assertStringContainsString('не</b> unique на <code>users.email</code>', $html);
        $this->assertStringContainsString('PasswordResetAccessFeatureTest', $html);
        $this->assertStringContainsString('PasswordResetAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('PasswordResetNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('PasswordResetMarkupFeatureTest', $html);
        $this->assertStringContainsString('PasswordResetUxFeatureTest', $html);
        $this->assertStringContainsString('PasswordResetFullAccessFeatureTest', $html);
        $this->assertStringContainsString('test_forgot_password_blade_disables_submit_and_is_not_ajax', $html);
        $this->assertStringContainsString('test_forgot_password_disabled_is_inside_setTimeout_so_native_post_is_not_cancelled', $html);
        $this->assertStringContainsString('test_guest_opens_reset_form_instead_of_login_redirect', $html);
        $this->assertStringContainsString('test_signed_in_user_without_crm_permission_is_not_forbidden', $html);
        $this->assertStringContainsString('OpsMonitor', $html);
        $this->assertStringNotContainsString('REDIS', $html);
    }

    public function test_documentation_controller_lists_password_reset_page(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'password-reset'", $controller);
        $this->assertStringContainsString('password_reset_tokens', $controller);
        $this->assertStringContainsString('SendPasswordResetLinkRequest', $controller);
        $this->assertStringContainsString('setTimeout(0)', $controller);
        $this->assertStringContainsString('Забыл пароль.', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
