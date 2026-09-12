<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка: карточки учеников рисует JS; селект абонемента у оплаченного не disabled;
 * ошибка пакета под селектом на обеих вкладках.
 */
final class SettingPricesFlexibleReplaceMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);
    }

    public function test_monthly_first_html_loads_vite_module_and_has_no_user_cards(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('setting-prices-user-card', $html);
        $this->assertStringNotContainsString('setting-prices-monthly-package-error', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringNotContainsString('packageSelectDisabled', $blade);
        $this->assertStringNotContainsString('renderUsersRightColumn', $blade);
    }

    public function test_monthly_js_keeps_package_select_enabled_for_paid_current_member(): void
    {
        $js = (string) file_get_contents(resource_path('js/settings-prices.js'));

        $this->assertStringContainsString("packageSelectDisabled = ''", $js);
        $this->assertStringNotContainsString("packageSelectDisabled = eff ? 'disabled' : ''", $js);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $js);
        $this->assertStringContainsString('data-effective-paid', $js);
        $this->assertStringContainsString('aria-label="Абонемент"', $js);

        $renderPos = strpos($js, 'function renderUsersRightColumn');
        $this->assertNotFalse($renderPos);
        $changePos = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($changePos);
        $renderChunk = substr($js, $renderPos, $changePos - $renderPos);
        $this->assertStringContainsString("if (!isFormer)", $renderChunk);
        $this->assertStringContainsString("packageSelectDisabled = ''", $renderChunk);
        $this->assertStringContainsString('} else if (!eff && hasAbon && !canManage)', $renderChunk);
    }

    public function test_users_tab_inline_markup_enables_package_select_when_paid_and_shows_field_error(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="user-prices-table-wrapper"', $html);
        $this->assertStringContainsString('id="save-user-year-prices"', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringContainsString("const packageDisabledAttr = isFormer ? 'disabled' : ''", $blade);
        $this->assertStringNotContainsString('effectivePaid ? \'disabled\'', $blade);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $blade);
        $this->assertStringContainsString("data-effective-paid=\"' + (effectivePaid ? '1' : '0')", $blade);
        $this->assertStringContainsString('aria-label="Абонемент"', $blade);
        $this->assertStringNotContainsString('resources/js/settings-prices.js', $blade);
        $this->assertStringNotContainsString('modal-fullscreen', $blade);
    }

    public function test_users_tab_without_set_prices_view_is_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.users'))->assertForbidden();
        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();
    }
}
