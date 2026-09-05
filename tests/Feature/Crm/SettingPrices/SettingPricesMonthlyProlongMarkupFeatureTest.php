<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Кнопка и модалка пролонгации только на вкладке «По месяцам».
 */
final class SettingPricesMonthlyProlongMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    public function test_monthly_tab_has_prolong_button_and_standard_modal(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="setting-prices-prolong-btn"', $html);
        $this->assertStringContainsString('Пролонгировать на следующий месяц', $html);
        $this->assertStringContainsString('id="setting-prices-prolong-modal"', $html);
        $this->assertStringContainsString('data-preview-url="'.route('setting-prices.prolong-month.preview').'"', $html);
        $this->assertStringContainsString('data-apply-url="'.route('setting-prices.prolong-month.apply').'"', $html);
        $this->assertStringContainsString('data-bs-toggle="modal"', $html);
        $this->assertStringContainsString('data-bs-target="#setting-prices-prolong-modal"', $html);
        $this->assertStringContainsString('data-error-for="selectedDate"', $html);

        $start = strpos($html, 'id="setting-prices-prolong-modal"');
        $this->assertNotFalse($start);
        $chunk = substr($html, $start, 4000);
        $this->assertStringContainsString('class="modal-dialog"', $chunk);
        $this->assertStringContainsString('cell-edit-modal', $chunk);
        $this->assertStringContainsString('schedule-modal-content', $chunk);
        $this->assertStringContainsString('cell-edit-context', $chunk);
        $this->assertStringContainsString('cell-edit-modal__footer', $chunk);
        $this->assertStringContainsString('btn-outline-secondary', $chunk);
        $this->assertStringContainsString('Отмена', $chunk);
        $this->assertStringNotContainsString('modal-xl', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);
        $this->assertStringContainsString('id="setting-prices-prolong-skip-hint-tpl"', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('fa fa-info-circle', $html);
        $this->assertStringContainsString('id="setting-prices-prolong-confirm"', $html);
        $this->assertMatchesRegularExpression(
            '/id="setting-prices-prolong-confirm"[^>]*\bdisabled\b/',
            $chunk
        );
        $this->assertStringContainsString('Загрузка превью…', $chunk);
        $this->assertStringNotContainsString('Будет пролонгировано', $html);
        $this->assertStringNotContainsString('$.ajax', $chunk);
        $this->assertStringNotContainsString('fetch(', $chunk);

        $historyPos = strpos($html, 'id="logs"');
        $btnPos = strpos($html, 'id="setting-prices-prolong-btn"');
        $this->assertNotFalse($historyPos);
        $this->assertNotFalse($btnPos);
        $this->assertLessThan($btnPos, $historyPos);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@vite(['resources/css/schedule.css'])", $blade);
        $this->assertStringContainsString("@include('partials.ui.tooltip-hint'", $blade);
        $this->assertStringNotContainsString('initMonthProlong', $blade);
    }

    public function test_users_tab_does_not_have_prolong_button(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="setting-prices-prolong-btn"', $html);
        $this->assertStringNotContainsString('id="setting-prices-prolong-modal"', $html);
        $this->assertStringNotContainsString('id="setting-prices-prolong-skip-hint-tpl"', $html);
    }

    public function test_custom_payments_tab_does_not_have_prolong_button(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('setPrices.customPayments.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->get(route('admin.settingPrices.customPayments'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="setting-prices-prolong-btn"', $html);
        $this->assertStringNotContainsString('id="setting-prices-prolong-modal"', $html);
        $this->assertStringNotContainsString('id="setting-prices-prolong-skip-hint-tpl"', $html);
    }
}
