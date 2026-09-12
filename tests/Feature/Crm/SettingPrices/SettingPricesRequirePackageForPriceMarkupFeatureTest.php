<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка: без абонемента поле цены закрыто; ошибка пакета под селектом.
 * Карточки рисует JS (Vite «По месяцам», inline «По ученикам»).
 *
 * Падает на коде до фикса: цена открывалась при !hasAbon.
 */
final class SettingPricesRequirePackageForPriceMarkupFeatureTest extends CrmTestCase
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
        $this->assertStringContainsString('id="set-price-all-users"', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringContainsString('Без абонемента', $blade);
        $this->assertStringNotContainsString('packageSelectDisabled', $blade);
        $this->assertStringNotContainsString('modal-fullscreen', $blade);
    }

    public function test_monthly_js_keeps_price_disabled_until_package_is_chosen(): void
    {
        $js = (string) file_get_contents(resource_path('js/settings-prices.js'));
        $renderPos = strpos($js, 'function renderUsersRightColumn');
        $this->assertNotFalse($renderPos);
        $changePos = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($changePos);
        $render = substr($js, $renderPos, $changePos - $renderPos);

        $this->assertStringContainsString('} else if (isEditing && !eff && hasAbon)', $render);
        $this->assertStringContainsString('} else if (!eff && hasAbon && !canManage)', $render);
        $this->assertStringNotContainsString('!canManage || !hasAbon', $render);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $render);
        $this->assertStringContainsString('data-abon-established', $render);
        $this->assertStringContainsString('aria-label="Абонемент"', $render);

        $enablePos = strpos($render, '} else if (!eff && hasAbon && !canManage)');
        $this->assertNotFalse($enablePos);
        $beforeEnable = substr($render, 0, $enablePos);
        $this->assertStringContainsString("let priceInputDisabled = 'disabled'", $beforeEnable);
    }

    public function test_users_tab_first_html_has_wrapper_and_inline_js_locks_price_without_package(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="user-prices-table-wrapper"', $html);
        $this->assertStringContainsString('id="save-user-year-prices"', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringNotContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringContainsString('if (!isFormer && !effectivePaid && hasAbon && !canManual)', $blade);
        $this->assertStringNotContainsString('!canManual || !hasAbon', $blade);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $blade);
        $this->assertStringContainsString('data-abon-established', $blade);
        $this->assertStringContainsString("url: '/admin/setting-prices/user-year-prices/save'", $blade);
        $this->assertStringNotContainsString('modal-fullscreen', $blade);
    }
}
