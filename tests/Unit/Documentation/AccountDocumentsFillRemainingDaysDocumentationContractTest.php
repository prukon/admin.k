<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#account-documents-fill-remaining-days-index совпадает с карточкой
 * «Мои документы»: оставшиеся дни до fill_expires_at, красным при < 6.
 */
final class AccountDocumentsFillRemainingDaysDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_cabinet_fill_remaining_days(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-documents-fill-remaining-days-index"', $html);
        $start = strpos($html, 'id="account-documents-fill-remaining-days-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-require-package-for-price-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/account-settings/documents', $chunk);
        $this->assertStringContainsString('Срок подписания', $chunk);
        $this->assertStringContainsString('Осталось N дней', $chunk);
        $this->assertStringContainsString('Последний день', $chunk);
        $this->assertStringContainsString('CLIENT_FILL_REMAINING_DAYS_WARN', $chunk);
        $this->assertStringContainsString('CLIENT_FILL_REMAINING_CAPTION', $chunk);
        $this->assertStringContainsString('CLIENT_FILL_REMAINING_LAST_DAY', $chunk);
        $this->assertStringContainsString('clientFillRemainingDaysLabel', $chunk);
        $this->assertStringContainsString('shouldShowClientFillRemainingDays', $chunk);
        $this->assertStringContainsString('shouldHighlightClientFillRemainingDays', $chunk);
        $this->assertStringContainsString('fill_expires_at', $chunk);
        $this->assertStringContainsString('календарн', $chunk);
        $this->assertStringContainsString('text-danger', $chunk);
        $this->assertStringContainsString('STATUS_SIGNED', $chunk);
        $this->assertStringContainsString('copy()->startOfDay()', $chunk);
        $this->assertStringContainsString('contract-list-fill-expires-at-index', $chunk);
        $this->assertStringContainsString('contracts.fillExpiresAt.view', $chunk);
        $this->assertStringContainsString('Срок заполнения', $chunk);
        $this->assertStringContainsString('account-contract-fill §1.6', $chunk);
        $this->assertStringContainsString('AccountDocumentsFillRemainingDaysUxFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsFillRemainingDaysAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractClientFillRemainingDaysTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsFillRemainingDaysDocumentationContractTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);

        $this->assertStringNotContainsString('ttl_hours Подпислона', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $contracts = $this->docFile('contracts.html');
        $family = $this->docFile('parents-and-family-cabinet.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('id="account-documents-fill-remaining-days"', $fill);
        $this->assertStringContainsString('/doc#account-documents-fill-remaining-days-index', $fill);
        $this->assertStringContainsString('Срок подписания', $fill);
        $this->assertStringContainsString('Последний день', $fill);
        $this->assertStringContainsString('CLIENT_FILL_REMAINING_DAYS_WARN', $fill);
        $this->assertStringContainsString('clientFillRemainingDaysLabel', $fill);
        $this->assertStringContainsString('shouldShowClientFillRemainingDays', $fill);
        $this->assertStringContainsString('contracts.fillExpiresAt.view', $fill);
        $this->assertStringContainsString('contract-list-fill-expires-at', $fill);

        $this->assertStringContainsString('/doc#account-documents-fill-remaining-days-index', $contracts);
        $this->assertStringContainsString('account-documents-fill-remaining-days', $contracts);

        $this->assertStringContainsString('/doc#account-documents-fill-remaining-days-index', $family);

        $this->assertStringContainsString('/doc#account-documents-fill-remaining-days-index', $index);
        $resendStart = strpos($index, 'id="account-documents-resend-sms-index"');
        $this->assertNotFalse($resendStart);
        $this->assertStringContainsString(
            '/doc#account-documents-fill-remaining-days-index',
            $this->docFile('index.html')
        );
    }

    public function test_catalog_and_controller_title_mention_remaining_days(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#account-documents-fill-remaining-days-index', $index);
        $this->assertStringContainsString('оставшиеся дни до срока подписания', $index);
        $this->assertStringContainsString('Срок подписания', $controller);
        $this->assertStringContainsString('оставшиеся дни', $controller);
    }

    public function test_live_code_matches_documented_cabinet_remaining_days(): void
    {
        $root = dirname(__DIR__, 3);
        $model = (string) file_get_contents($root.'/app/Models/Contract.php');
        $blade = (string) file_get_contents($root.'/resources/views/account/documents.blade.php');

        $this->assertStringContainsString('CLIENT_FILL_REMAINING_DAYS_WARN = 6', $model);
        $this->assertStringContainsString("CLIENT_FILL_REMAINING_CAPTION = 'Срок подписания'", $model);
        $this->assertStringContainsString("CLIENT_FILL_REMAINING_LAST_DAY = 'Последний день'", $model);
        $this->assertStringContainsString('function shouldShowClientFillRemainingDays', $model);
        $this->assertStringContainsString('function clientFillRemainingDays', $model);
        $this->assertStringContainsString('function clientFillRemainingDaysLabel', $model);
        $this->assertStringContainsString('function shouldHighlightClientFillRemainingDays', $model);
        $this->assertStringContainsString('copy()->startOfDay()', $model);
        $this->assertStringContainsString('STATUS_SIGNED', $model);

        $this->assertStringContainsString('clientFillRemainingDaysLabel()', $blade);
        $this->assertStringContainsString('shouldHighlightClientFillRemainingDays()', $blade);
        $this->assertStringContainsString('CLIENT_FILL_REMAINING_CAPTION', $blade);
        $this->assertStringContainsString('text-danger', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
