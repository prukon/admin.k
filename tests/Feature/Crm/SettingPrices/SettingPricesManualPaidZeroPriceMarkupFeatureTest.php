<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * Разметка: карандаш при 0 ₽ остаётся (нужен, чтобы снять ошибочную отметку);
 * ошибка нулевой оплаты — под всей строкой карточки (не в колонке суммы);
 * модалка комментария стандартной ширины.
 *
 * Карточки «По месяцам» рисует Vite; «По ученикам» — inline JS после year-prices.
 */
final class SettingPricesManualPaidZeroPriceMarkupFeatureTest extends SettingPricesManualPaidZeroPriceTestCase
{
    public function test_monthly_first_html_has_no_cards_and_standard_comment_modal(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('setting-prices-user-card', $html);
        $this->assertStringNotContainsString('setting-prices-monthly-price-error', $html);
        $this->assertStringContainsString('id="manualUserPricePaidModal"', $html);

        $start = strpos($html, 'id="manualUserPricePaidModal"');
        $this->assertNotFalse($start);
        $chunk = substr($html, $start, 2500);
        $this->assertStringContainsString('class="modal-dialog modal-dialog-centered', $chunk);
        $this->assertStringNotContainsString('modal-xl', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@include('includes.modal.manualUserPricePaidModal')", $blade);
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringNotContainsString('postManualPaid', $blade);
    }

    public function test_monthly_pencil_depends_on_package_not_on_positive_price(): void
    {
        $js = (string) file_get_contents(resource_path('js/settings-prices.js'));
        $renderPos = strpos($js, 'function renderUsersRightColumn');
        $this->assertNotFalse($renderPos);
        $changePos = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($changePos);
        $render = substr($js, $renderPos, $changePos - $renderPos);

        $this->assertStringContainsString('if (!isFormer && canManage && uid && hasAbon)', $render);
        $this->assertStringNotContainsString('price > 0', $render);
        $this->assertStringNotContainsString('price_cents > 0', $render);
        $this->assertStringNotContainsString('up.price > 0', $render);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $render);
        $this->assertStringContainsString('user-price-manual-edit', $render);
        $this->assertErrorsSitAfterMonthlyRow($render);
    }

    public function test_users_tab_pencil_at_zero_needs_row_and_package_not_positive_price(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="user-prices-table-wrapper"', $html);
        $this->assertStringContainsString('id="manualUserPricePaidModal"', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringContainsString('if (canManual && hasRow && hasAbon)', $blade);
        $this->assertStringNotContainsString('item.price > 0', $blade);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $blade);
        $this->assertStringNotContainsString('modal-fullscreen', $blade);
        $this->assertStringNotContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertErrorsSitAfterMonthlyRow($blade);

        $css = (string) file_get_contents(resource_path('css/style.css'));
        $cssStart = strpos($css, '#right_bar .wrap-users .setting-prices-monthly-package-error');
        $this->assertNotFalse($cssStart);
        $cssChunk = substr($css, $cssStart, 280);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $cssChunk);
        $this->assertStringContainsString('max-width: 100%', $cssChunk);
        $this->assertStringContainsString('overflow-wrap: anywhere', $cssChunk);
    }

    /**
     * Ошибки пакета/цены — после .setting-prices-monthly-row, не в колонке суммы:
     * иначе длинный 422 раздувает колонку и селект оплаты уезжает вправо.
     */
    private function assertErrorsSitAfterMonthlyRow(string $source): void
    {
        $cardStart = strpos($source, 'const userBlock = `');
        if ($cardStart === false) {
            $cardStart = strpos($source, 'html += \'<div class="setting-prices-user-card');
        }
        $this->assertNotFalse($cardStart);
        $priceErrPos = strpos($source, 'setting-prices-monthly-price-error', $cardStart);
        $this->assertNotFalse($priceErrPos);
        $card = substr($source, $cardStart, ($priceErrPos + 80) - $cardStart);

        $rowPos = strpos($card, 'setting-prices-monthly-row');
        $statusPos = strpos($card, 'setting-prices-monthly-status flex-shrink-0');
        $packageErrPos = strpos($card, 'setting-prices-monthly-package-error');
        $priceErrInCard = strpos($card, 'setting-prices-monthly-price-error');
        $this->assertNotFalse($rowPos);
        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($packageErrPos);
        $this->assertNotFalse($priceErrInCard);
        $this->assertLessThan($statusPos, $rowPos);
        $this->assertLessThan($packageErrPos, $statusPos);
        $this->assertLessThan($priceErrInCard, $packageErrPos);

        $priceColStart = strpos($card, 'setting-prices-monthly-price flex-shrink-0');
        $this->assertNotFalse($priceColStart);
        $this->assertLessThan($statusPos, $priceColStart);
        $priceCol = substr($card, $priceColStart, $statusPos - $priceColStart);
        $this->assertStringNotContainsString('setting-prices-monthly-price-error', $priceCol);
        $this->assertStringNotContainsString('setting-prices-monthly-package-error', $priceCol);
    }

    public function test_year_prices_keep_zero_row_and_allow_manual_paid_manage(): void
    {
        $this->seedZeroUnpaidMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_manage_manual_paid', true);

        $october = collect($response->json('months'))->firstWhere('new_month', self::MONTH_DATE);
        $this->assertIsArray($october);
        $this->assertTrue((bool) $october['has_price_row']);
        $this->assertSame(0, (int) $october['price']);
        $this->assertFalse((bool) $october['effective_is_paid']);
        $this->assertNotNull($october['lesson_package_id']);
    }

    public function test_year_prices_without_manual_paid_manage_hides_flag(): void
    {
        $this->seedZeroUnpaidMonth();
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->grantLessonPackageTypePermissions($actor, ['fixed', 'flexible', 'no_schedule']);
        $this->actingAs($actor);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
            ])
            ->assertOk()
            ->assertJsonPath('can_manage_manual_paid', false);
    }

    public function test_monthly_team_price_keeps_zero_row_with_package(): void
    {
        $this->seedZeroUnpaidMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => self::MONTH_LABEL,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_manage_manual_paid', true);

        $row = collect($response->json('usersPrice'))->firstWhere('user_id', $this->student->id);
        $this->assertIsArray($row);
        $this->assertSame(0, (int) $row['price']);
        $this->assertFalse((bool) $row['effective_is_paid']);
        $this->assertSame((int) $this->package->id, (int) $row['lesson_package_id']);
    }
}
