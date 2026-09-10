<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-two-factor-permission-index совпадает с ЛК и каталогом прав.
 */
final class AccountTwoFactorPermissionDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_account_two_factor_permission(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-two-factor-permission-index"', $html);
        $start = strpos($html, 'id="account-two-factor-permission-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="payment-vitrina-sbp-only-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('account.user.two_factor.update', $chunk);
        $this->assertStringContainsString('/account-settings/user/edit', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('user</code>,', $chunk);
        $this->assertStringContainsString('admin</code>', $chunk);
        $this->assertStringContainsString('trainer', $chunk);
        $this->assertStringContainsString('PATCH /account-settings/user', $chunk);
        $this->assertStringContainsString('two_factor_enabled', $chunk);
        $this->assertStringContainsString('force_2fa_admins', $chunk);
        $this->assertStringContainsString('2026_09_10_034100_add_account_user_two_factor_update_permission.php', $chunk);
        $this->assertStringContainsString('2026_09_10_044800_revoke_account_user_two_factor_update_from_roles.php', $chunk);
        $this->assertStringContainsString('parents-and-family-cabinet#account-two-factor', $chunk);
        $this->assertStringContainsString('AccountTwoFactorPermissionAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountTwoFactorPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('AccountTwoFactorPermissionMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('AccountTwoFactorPermissionUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString(
            'BladeInlineJsSyntaxTest::test_account_user_two_factor_ajax_submit_is_valid_javascript_and_attaches_errors_to_visible_field',
            $chunk
        );
        $this->assertStringContainsString('hasRole(\'admin\')', $chunk);
        $this->assertStringContainsString('account.user.view', $chunk);
        $this->assertStringContainsString('EnsureTwoFactorIsVerified', $chunk);
        $this->assertStringContainsString('settings.force2fa.admins', $chunk);
        $this->assertStringContainsString('Кастомные роли', $chunk);
        $this->assertStringContainsString(':not([type=hidden])', $chunk);
        $this->assertStringContainsString('settings-roles-custom', $chunk);
    }

    public function test_parents_cabinet_page_documents_two_factor_permission(): void
    {
        $html = $this->docFile('parents-and-family-cabinet.html');

        $this->assertStringContainsString('id="account-two-factor"', $html);
        $this->assertStringContainsString('/doc#account-two-factor-permission-index', $html);
        $start = strpos($html, 'id="account-two-factor"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="family-cabinet"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('account.user.two_factor.update', $chunk);
        $this->assertStringContainsString('AccountUpdateRequest', $chunk);
        $this->assertStringContainsString('two_factor_enabled', $chunk);
        $this->assertStringContainsString('force_2fa_admins', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('AccountTwoFactorPermissionAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountTwoFactorPermissionMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('AccountTwoFactorPermissionUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString(
            'BladeInlineJsSyntaxTest::test_account_user_two_factor_ajax_submit_is_valid_javascript_and_attaches_errors_to_visible_field',
            $chunk
        );
        $this->assertStringContainsString('2026_09_10_034100_add_account_user_two_factor_update_permission.php', $chunk);
        $this->assertStringContainsString('2026_09_10_044800_revoke_account_user_two_factor_update_from_roles.php', $chunk);
        $this->assertStringContainsString('hasRole(\'admin\')', $chunk);
        $this->assertStringContainsString(':not([type=hidden])', $chunk);
        $this->assertStringContainsString('EnsureTwoFactorIsVerified', $chunk);
        $this->assertStringContainsString('account.user.view', $chunk);
        $this->assertStringContainsString('Кастомные роли', $chunk);
        $this->assertStringContainsString('settings.force2fa.admins', $chunk);
    }

    public function test_catalog_and_controller_title_mention_two_factor_permission(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $partners = $this->docFile('partners-permissions.html');
        $groups = $this->docFile('settings-permission-groups.html');

        $this->assertStringContainsString('id="account-two-factor-permission-index"', $index);
        $this->assertStringContainsString('/doc#account-two-factor-permission-index', $index);
        $this->assertStringContainsString('SMS-2FA в ЛК (account.user.two_factor.update)', $controller);
        $this->assertStringContainsString('account.user.two_factor.update', $partners);
        $this->assertStringContainsString('не</b> входит в базовые роли', $partners);
        $this->assertStringContainsString('account.user.two_factor.update', $groups);
        $this->assertStringContainsString('2026_09_10_034100_add_account_user_two_factor_update_permission.php', $groups);
        $this->assertStringContainsString('2026_09_10_044800_revoke_account_user_two_factor_update_from_roles.php', $groups);
        $rolesCustom = $this->docFile('settings-roles-custom.html');
        $this->assertStringContainsString('account.user.two_factor.update', $rolesCustom);
        $this->assertStringContainsString('/doc#account-two-factor-permission-index', $rolesCustom);
    }

    public function test_live_code_matches_documented_two_factor_permission(): void
    {
        $root = dirname(__DIR__, 3);
        $seeder = (string) file_get_contents($root.'/database/seeders/PermissionSeeder.php');
        $config = (string) file_get_contents($root.'/config/role_base_permissions.php');
        $hints = (string) file_get_contents($root.'/config/permission_capability_hints.php');
        $gate = (string) file_get_contents($root.'/app/Providers/AuthServiceProvider.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/User/AccountUpdateRequest.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/AccountController.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/users.blade.php');
        $migration = (string) file_get_contents(
            $root.'/database/migrations/2026_09_10_034100_add_account_user_two_factor_update_permission.php'
        );

        $this->assertStringContainsString("'name' => 'account.user.two_factor.update'", $seeder);
        $this->assertStringContainsString("'is_visible' => 0", $seeder);
        $this->assertStringContainsString("'sort_order' => 62", $seeder);

        $this->assertSame(
            0,
            preg_match_all("/^\\s+'account\\.user\\.two_factor\\.update',/m", $config)
        );
        $this->assertStringContainsString("// 'account.user.two_factor.update'", $config);

        $this->assertStringContainsString("'account.user.two_factor.update' => [", $hints);
        $this->assertStringContainsString("Gate::define('account.user.two_factor.update'", $gate);

        $this->assertStringContainsString("can('account.user.two_factor.update')", $request);
        $this->assertStringContainsString("offsetUnset('two_factor_enabled')", $request);

        $this->assertStringContainsString("Gate::allows('account.user.two_factor.update')", $controller);

        $this->assertStringContainsString("@can('account.user.two_factor.update')", $blade);
        $this->assertStringContainsString('Двухфакторная аутентификация (SMS)', $blade);
        $this->assertStringContainsString('$user->hasRole(\'admin\')', $blade);
        $this->assertStringNotContainsString('(int)$user->role_id === 10', $blade);
        $this->assertStringContainsString(':not([type="hidden"])', $blade);

        $this->assertStringNotContainsString('BACKFILL_ROLE_NAMES', $migration);
        $this->assertStringContainsString("'is_visible'          => 0", $migration);

        $revoke = (string) file_get_contents(
            $root.'/database/migrations/2026_09_10_044800_revoke_account_user_two_factor_update_from_roles.php'
        );
        $this->assertStringContainsString("where('permission_id', \$permissionId)->delete()", $revoke);
        $this->assertStringContainsString("'is_visible' => 0", $revoke);

        $middleware = (string) file_get_contents($root.'/app/Http/Middleware/EnsureTwoFactorIsVerified.php');
        $this->assertStringContainsString('$isAdmin  = ((int)$user->role_id === 10);', $middleware);
        $this->assertStringContainsString("\$isAdminRole    = (\$targetRoleName === 'admin');", $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
