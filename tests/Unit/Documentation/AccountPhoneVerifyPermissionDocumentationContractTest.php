<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-phone-verify-permission-index совпадает с ЛК и каталогом прав.
 */
final class AccountPhoneVerifyPermissionDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_account_phone_verify_permission(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-phone-verify-permission-index"', $html);
        $start = strpos($html, 'id="account-phone-verify-permission-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-send-failure-message-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('account.user.phone.verify', $chunk);
        $this->assertStringContainsString('/account-settings/user/edit', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('user</code>', $chunk);
        $this->assertStringContainsString('admin</code>', $chunk);
        $this->assertStringContainsString('trainer', $chunk);
        $this->assertStringContainsString('phoneSendCode', $chunk);
        $this->assertStringContainsString('phoneConfirmCode', $chunk);
        $this->assertStringContainsString('AccountUserPhoneSendCodeRequest', $chunk);
        $this->assertStringContainsString('errors.phone', $chunk);
        $this->assertStringContainsString('errors.code', $chunk);
        $this->assertStringContainsString('2026_09_13_004500_add_account_user_phone_verify_permission.php', $chunk);
        $this->assertStringContainsString('parents-and-family-cabinet#account-phone-verify', $chunk);
        $this->assertStringContainsString('AccountPhoneVerifyPermissionAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountPhoneVerifyPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('AccountPhoneVerifyPermissionMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('AccountPhoneVerifyPermissionUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString(
            'BladeInlineJsSyntaxTest::test_account_user_phone_verify_inline_script_is_valid_javascript',
            $chunk
        );
        $this->assertStringContainsString('account.user.view', $chunk);
        $this->assertStringContainsString('account.user.phone.update', $chunk);
        $this->assertStringContainsString('settings-roles-custom', $chunk);
        $this->assertStringContainsString('alreadyVerified', $chunk);
        $this->assertStringContainsString('чужой партнёр', $chunk);
        $this->assertStringContainsString('updateVerifyUI', $chunk);
        $this->assertStringContainsString('phone_verified_at', $chunk);
        $this->assertStringContainsString('Номер подтверждён. Смена запрещена.', $chunk);
        $this->assertStringContainsString('PhoneChangeController', $chunk);
        $this->assertStringContainsString('/security/phone', $chunk);
        $this->assertStringContainsString('verified_at', $chunk);
    }

    public function test_parents_cabinet_page_documents_phone_verify_permission(): void
    {
        $html = $this->docFile('parents-and-family-cabinet.html');

        $this->assertStringContainsString('id="account-phone-verify"', $html);
        $this->assertStringContainsString('/doc#account-phone-verify-permission-index', $html);
        $start = strpos($html, 'id="account-phone-verify"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="family-cabinet"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('account.user.phone.verify', $chunk);
        $this->assertStringContainsString('AccountUserPhoneSendCodeRequest', $chunk);
        $this->assertStringContainsString('AccountUserPhoneConfirmCodeRequest', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('id="verify-phone-btn"', $chunk);
        $this->assertStringContainsString('errors.phone', $chunk);
        $this->assertStringContainsString('errors.code', $chunk);
        $this->assertStringContainsString('AccountPhoneVerifyPermissionAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountPhoneVerifyPermissionMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('AccountPhoneVerifyPermissionUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString(
            'BladeInlineJsSyntaxTest::test_account_user_phone_verify_inline_script_is_valid_javascript',
            $chunk
        );
        $this->assertStringContainsString('2026_09_13_004500_add_account_user_phone_verify_permission.php', $chunk);
        $this->assertStringContainsString('account.user.phone.update', $chunk);
        $this->assertStringContainsString('alreadyVerified', $chunk);
        $this->assertStringContainsString('updateVerifyUI', $chunk);
        $this->assertStringContainsString('phone_verified_at', $chunk);
        $this->assertStringContainsString('Номер подтверждён. Смена запрещена.', $chunk);
        $this->assertStringContainsString('PhoneChangeController', $chunk);
        $this->assertStringContainsString('/security/phone', $chunk);
        $this->assertStringContainsString('verified_at', $chunk);
    }

    public function test_catalog_and_controller_title_mention_phone_verify_permission(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $partners = $this->docFile('partners-permissions.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $rolesCustom = $this->docFile('settings-roles-custom.html');

        $this->assertStringContainsString('id="account-phone-verify-permission-index"', $index);
        $this->assertStringContainsString('/doc#account-phone-verify-permission-index', $index);
        $this->assertStringContainsString('SMS-подтверждение телефона в ЛК (account.user.phone.verify)', $controller);
        $this->assertStringContainsString('account.user.phone.verify', $partners);
        $this->assertStringContainsString('не</b> входит в базовые роли', $partners);
        $this->assertStringContainsString('account.user.phone.verify', $groups);
        $this->assertStringContainsString('2026_09_13_004500_add_account_user_phone_verify_permission.php', $groups);
        $this->assertStringContainsString('account.user.phone.verify', $rolesCustom);
        $this->assertStringContainsString('/doc#account-phone-verify-permission-index', $rolesCustom);
    }

    public function test_live_code_matches_documented_phone_verify_permission(): void
    {
        $root = dirname(__DIR__, 3);
        $seeder = (string) file_get_contents($root.'/database/seeders/PermissionSeeder.php');
        $config = (string) file_get_contents($root.'/config/role_base_permissions.php');
        $hints = (string) file_get_contents($root.'/config/permission_capability_hints.php');
        $gate = (string) file_get_contents($root.'/app/Providers/AuthServiceProvider.php');
        $sendRequest = (string) file_get_contents($root.'/app/Http/Requests/Account/AccountUserPhoneSendCodeRequest.php');
        $confirmRequest = (string) file_get_contents($root.'/app/Http/Requests/Account/AccountUserPhoneConfirmCodeRequest.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/AccountController.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/users.blade.php');
        $routes = (string) file_get_contents($root.'/routes/web.php');
        $migration = (string) file_get_contents(
            $root.'/database/migrations/2026_09_13_004500_add_account_user_phone_verify_permission.php'
        );

        $this->assertStringContainsString("'name' => 'account.user.phone.verify'", $seeder);
        $this->assertStringContainsString("'is_visible' => 0", $seeder);
        $this->assertStringContainsString("'sort_order' => 61", $seeder);

        $this->assertSame(
            0,
            preg_match_all("/^\\s+'account\\.user\\.phone\\.verify',/m", $config)
        );
        $this->assertStringContainsString("// 'account.user.phone.verify'", $config);

        $this->assertStringContainsString("'account.user.phone.verify' => [", $hints);
        $this->assertStringContainsString('admin — ученик той же школы', $hints);
        $this->assertStringContainsString("Gate::define('account.user.phone.verify'", $gate);
        $this->assertStringContainsString("hasPermission('account.user.phone.verify')", $gate);
        $this->assertStringContainsString('(int) $actor->partner_id === (int) $target->partner_id', $gate);

        $this->assertStringContainsString('class AccountUserPhoneSendCodeRequest', $sendRequest);
        $this->assertStringContainsString('class AccountUserPhoneConfirmCodeRequest', $confirmRequest);

        $this->assertStringContainsString('AccountUserPhoneSendCodeRequest', $controller);
        $this->assertStringContainsString('AccountUserPhoneConfirmCodeRequest', $controller);
        $this->assertStringContainsString('SmsRuService::userFacingErrorMessage', $controller);

        $this->assertStringContainsString("@can('account.user.phone.verify')", $blade);
        $this->assertStringContainsString("can('account.user.phone.verify')", $blade);
        $this->assertStringContainsString('id="verify-phone-btn"', $blade);
        $this->assertStringContainsString('setPhoneVerifyError', $blade);
        $this->assertStringNotContainsString('$phone.is(\':disabled\')', $blade);

        $this->assertStringContainsString("->name('phoneSendCode')", $routes);
        $this->assertStringContainsString("->name('phoneConfirmCode')", $routes);
        $this->assertStringContainsString("can:account.user.view", $routes);

        $this->assertStringNotContainsString('BACKFILL_ROLE_NAMES', $migration);
        $this->assertStringContainsString("'is_visible'          => 0", $migration);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
