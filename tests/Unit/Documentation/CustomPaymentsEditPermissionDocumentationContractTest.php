<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#custom-payments-edit-permission-index совпадает с живым UX:
 * manualPaid.manage скрывает только селект статуса, не кнопку «Редактировать».
 */
final class CustomPaymentsEditPermissionDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_edit_without_manual_paid_without_contradicting_monthly_tabs(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="custom-payments-edit-permission-index"', $html);
        $start = strpos($html, 'id="custom-payments-edit-permission-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="users-contract-create-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/custom-payments', $chunk);
        $this->assertStringContainsString('setPrices.manualPaid.manage', $chunk);
        $this->assertStringContainsString('«Редактировать»</b>. Оно скрывает только селект', $chunk);
        $this->assertStringContainsString('custom-payment-edit-is-paid-wrap', $chunk);
        $this->assertStringContainsString('__customPaymentsCanManualPaid', $chunk);
        $this->assertStringContainsString('setPrices.customPayments.view', $chunk);
        $this->assertStringContainsString('errors.is_paid', $chunk);
        $this->assertStringContainsString('POST /admin/setting-prices/manual-paid', $chunk);
        $this->assertStringContainsString('По месяцам', $chunk);
        $this->assertStringContainsString('public/js/setting-prices-custom-payments.js', $chunk);
        $this->assertStringContainsString('CustomPaymentsEditPermissionFeatureTest', $chunk);
        $this->assertStringContainsString('CustomPaymentsEditPermissionDocumentationContractTest', $chunk);
        $this->assertStringContainsString('setting-prices-custom-payments#permissions', $chunk);
        $this->assertStringNotContainsString('кнопку «Редактировать» в таблице (флаг', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_keep_monthly_pencil_separate(): void
    {
        $custom = $this->docFile('setting-prices-custom-payments.html');
        $monthly = $this->docFile('setting-prices-monthly-users.html');
        $partners = $this->docFile('partners-permissions.html');
        $payments = $this->docFile('payments.html');

        $this->assertStringContainsString('/doc#custom-payments-edit-permission-index', $custom);
        $this->assertStringContainsString('Без права кнопка «Редактировать» видна', $custom);
        $this->assertStringContainsString('PUT update (сумма/описание); DELETE destroy', $custom);
        $this->assertStringContainsString('errors.is_paid', $custom);

        $this->assertStringContainsString('/doc#custom-payments-edit-permission-index', $monthly);
        $this->assertStringContainsString('Карандаш ручной оплаты месяца', $monthly);
        $this->assertStringContainsString('скрывает только селект статуса, не кнопку «Редактировать»', $monthly);

        $this->assertStringContainsString('/doc#custom-payments-edit-permission-index', $partners);
        $this->assertStringContainsString('редактирование суммы/описания, удаление неоплаченного', $partners);
        $this->assertStringContainsString('селект статуса доп. платежа (не кнопка «Редактировать»)', $partners);

        $this->assertStringContainsString('/doc#custom-payments-edit-permission-index', $payments);
        $this->assertStringContainsString('Update/delete — <code>setPrices.customPayments.view</code>', $payments);
        $this->assertStringContainsString('не кнопка «Редактировать»', $payments);
    }

    public function test_catalog_and_controller_title_mention_status_select_only(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="custom-payments-edit-permission-index"', $index);
        $this->assertStringContainsString('/doc#custom-payments-edit-permission-index', $index);
        $this->assertStringContainsString('manualPaid.manage</code> только селект статуса оплаты', $index);

        $this->assertStringContainsString(
            'manualPaid.manage (только селект статуса)',
            $controller
        );
    }

    public function test_both_js_paths_match_documented_edit_button_not_gated(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root.'/resources/js/setting-prices-custom-payments.js',
            $root.'/public/js/setting-prices-custom-payments.js',
        ];

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $js = (string) file_get_contents($path);

            $actionsStart = strpos($js, "key: 'actions'");
            $this->assertNotFalse($actionsStart, $path);
            $actionsBlock = substr($js, $actionsStart, 700);
            $this->assertStringContainsString('data-custom-payment-action="edit"', $actionsBlock, $path);
            $this->assertStringNotContainsString('__customPaymentsCanManualPaid', $actionsBlock, $path);
            $this->assertStringContainsString("paidWrap.style.display = canManual ? '' : 'none'", $js, $path);
            $this->assertStringContainsString('var isPaid = canManual', $js, $path);
        }
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
