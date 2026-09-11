<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка: у оплаченного месяца без абона селект доступен, сумма нет;
 * ошибка пакета под селектом. Карточки рисует JS.
 */
final class SettingPricesPaidEmptyPrepaidAttachMarkupFeatureTest extends CrmTestCase
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

    public function test_monthly_first_html_has_no_user_cards_and_loads_vite(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('setting-prices-user-card', $html);
        $this->assertStringNotContainsString('setting-prices-monthly-package-error', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringContainsString('Без абонемента', $blade);
        $this->assertStringNotContainsString('packageSelectDisabled', $blade);
    }

    public function test_monthly_js_enables_package_select_for_paid_month_even_without_package(): void
    {
        $js = (string) file_get_contents(resource_path('js/settings-prices.js'));
        $renderPos = strpos($js, 'function renderUsersRightColumn');
        $this->assertNotFalse($renderPos);
        $changePos = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($changePos);
        $render = substr($js, $renderPos, $changePos - $renderPos);

        $formerPos = strpos($render, 'if (!isFormer)');
        $this->assertNotFalse($formerPos);
        $afterFormer = substr($render, $formerPos, 700);
        $this->assertStringContainsString("packageSelectDisabled = ''", $afterFormer);
        $enablePos = strpos($afterFormer, "packageSelectDisabled = ''");
        $this->assertNotFalse($enablePos);
        $beforeEnable = substr($afterFormer, 0, $enablePos);
        $this->assertStringNotContainsString('hasAbon', $beforeEnable);
        $this->assertStringNotContainsString('eff ?', $beforeEnable);
        $this->assertStringContainsString('} else if (!eff && (!canManage || !hasAbon))', $render);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $render);
        $this->assertStringContainsString('data-effective-paid', $render);
        $this->assertStringContainsString('data-abon-established', $render);
        $this->assertStringContainsString('aria-label="Абонемент"', $render);
    }

    public function test_users_tab_enables_package_select_when_paid_without_package_and_keeps_price_locked(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="user-prices-table-wrapper"', $html);
        $this->assertStringContainsString('id="save-user-year-prices"', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringContainsString("const packageDisabledAttr = isFormer ? 'disabled' : ''", $blade);
        $this->assertStringNotContainsString("effectivePaid ? 'disabled'", $blade);
        $this->assertStringContainsString('if (!isFormer && !effectivePaid && (!canManual || !hasAbon))', $blade);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $blade);
        $this->assertStringContainsString("data-effective-paid=\"' + (effectivePaid ? '1' : '0')", $blade);
        $this->assertStringNotContainsString('modal-fullscreen', $blade);
    }
}
