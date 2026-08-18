<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc и setting-prices-custom-payments §3.3 должны совпадать с UI:
 * update доп. платежа — AJAX + общий #kidsMainToast, не #priceToast и не alert.
 */
final class CustomPaymentsToastDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_custom_payment_update_shared_toast(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="success-toast-instead-of-modal-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-sidebar-stability-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('setting-prices-custom-payments.js', $chunk);
        $this->assertStringContainsString('Дополнительный платеж успешно создан.', $chunk);
        $this->assertStringContainsString('Изменения сохранены.', $chunk);
        $this->assertStringContainsString('#kidsMainToast', $chunk);
        $this->assertStringContainsString('#priceToast', $chunk);
        $this->assertStringContainsString('не локальный', $chunk);
        $this->assertStringContainsString('CustomPaymentsToastDocumentationContractTest', $chunk);
    }

    public function test_custom_payments_doc_describes_update_ajax_and_shared_toast(): void
    {
        $html = $this->docFile('setting-prices-custom-payments.html');
        $start = strpos($html, 'id="ui"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="store-validation"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString("window.showToast('Изменения сохранены.', 'success')", $chunk);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $chunk);
        $this->assertStringContainsString('#kidsMainToast', $chunk);
        $this->assertStringContainsString('#priceToast', $chunk);
        $this->assertStringContainsString('не <code>alert</code>', $chunk);
        $this->assertStringContainsString('Дополнительный платеж удалён.', $chunk);
        $this->assertStringContainsString('CustomPaymentsToastDocumentationContractTest', $html);
    }

    public function test_both_js_paths_match_documented_update_toast(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root.'/resources/js/setting-prices-custom-payments.js',
            $root.'/public/js/setting-prices-custom-payments.js',
        ];

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString("method: 'PUT'", $content);
            $this->assertStringContainsString("window.showToast('Изменения сохранены.', 'success')", $content);
            $this->assertStringContainsString("window.showToast('Дополнительный платеж успешно создан.', 'success')", $content);
            $this->assertStringContainsString("window.showToast('Дополнительный платеж удалён.', 'success')", $content);
            $this->assertStringNotContainsString('priceToast', $content);
            $this->assertStringNotContainsString('showSuccessModal', $content);
            $this->assertStringNotContainsString('alert(', $content);
        }
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
