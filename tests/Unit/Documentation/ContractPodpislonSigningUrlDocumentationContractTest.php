<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-podpislon-signing-url-index совпадает с кодом:
 * provider_signing_url, карточка CRM и кабинет, whitelist Подпислона.
 */
final class ContractPodpislonSigningUrlDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_podpislon_signing_url(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-podpislon-signing-url-index"', $html);
        $start = strpos($html, 'id="contract-podpislon-signing-url-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="school-lead-create-client-choice-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('contracts.provider_signing_url', $chunk);
        $this->assertStringContainsString('contacts[].link', $chunk);
        $this->assertStringContainsString('https://podpislon.ru/sign/pack/', $chunk);
        $this->assertStringContainsString('no_sms', $chunk);
        $this->assertStringContainsString('не</b> передаём', $chunk);
        $this->assertStringContainsString('PodpislonSigningUrl::capture()', $chunk);
        $this->assertStringContainsString('GET /client-contracts/{id}/status', $chunk);
        $this->assertStringContainsString('synced: false', $chunk);
        $this->assertStringContainsString('id="contract-provider-signing-url"', $chunk);
        $this->assertStringContainsString('Открыть ссылку из SMS', $chunk);
        $this->assertStringContainsString('Синхронизировать с Подпислон', $chunk);
        $this->assertStringContainsString('contracts §3.1.1', $chunk);
        $this->assertStringContainsString('account-contract-fill §1.3', $chunk);
        $this->assertStringContainsString('ContractPodpislonSigningUrlFeatureTest', $chunk);
        $this->assertStringContainsString('ContractPodpislonSigningUrlAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractPodpislonSigningUrlFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractPodpislonSigningUrlAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ContractPodpislonSigningUrlNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ContractPodpislonSigningUrlUxFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsPodpislonSigningUrlAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsPodpislonSigningUrlUxFeatureTest', $chunk);
        $this->assertStringContainsString('AccountDocumentsPodpislonSigningUrlNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('ContractPodpislonSigningUrlDocumentationContractTest', $chunk);
        $this->assertStringContainsString('#openSendModal', $chunk);
        $this->assertStringContainsString('showSuccessModal', $chunk);
        $this->assertStringContainsString('/doc#contract-podpislon-signing-url-index', $chunk);
        $this->assertStringContainsString('колонка списка', $chunk);
        $this->assertStringContainsString('не</b> стирают', $chunk);
        $this->assertStringContainsString('любой статус', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $contracts = $this->docFile('contracts.html');
        $fill = $this->docFile('account-contract-fill.html');

        $this->assertStringContainsString('/doc#contract-podpislon-signing-url-index', $contracts);
        $this->assertStringContainsString('id="contract-podpislon-signing-url"', $contracts);
        $this->assertStringContainsString('provider_signing_url', $contracts);
        $this->assertStringContainsString('#contract-provider-signing-url', $contracts);
        $this->assertStringContainsString('PodpislonSigningUrl::capture()', $contracts);
        $this->assertStringContainsString('список', $contracts);
        $this->assertStringContainsString('не</b> очищают', $contracts);
        $this->assertStringContainsString('ContractPodpislonSigningUrlFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractPodpislonSigningUrlAjaxContractFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractPodpislonSigningUrlNonAjaxSafetyNetFeatureTest.php', $contracts);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest.php', $contracts);

        $this->assertStringContainsString('/doc#contract-podpislon-signing-url-index', $fill);
        $this->assertStringContainsString('id="account-documents-podpislon-signing-url"', $fill);
        $this->assertStringContainsString('Открыть ссылку из SMS', $fill);
        $this->assertStringContainsString('AccountDocumentsPodpislonSigningUrlUxFeatureTest.php', $fill);
        $this->assertStringContainsString('AccountDocumentsPodpislonSigningUrlAccessFeatureTest.php', $fill);
        $this->assertStringContainsString('AccountDocumentsPodpislonSigningUrlNonAjaxSafetyNetFeatureTest.php', $fill);
        $this->assertStringContainsString('canClientOpenSigningUrl()', $fill);
        $this->assertStringContainsString('account-documents-expiry-notice', $fill);
        $this->assertStringContainsString('signed</code> и <code>revoked</code>', $fill);
    }

    public function test_catalog_and_controller_title_mention_signing_url(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-podpislon-signing-url-index', $index);
        $this->assertStringContainsString('provider_signing_url', $index);
        $this->assertStringContainsString('ссылка на подпись из SMS', $controller);
        $this->assertStringContainsString('provider_signing_url', $controller);
    }

    public function test_live_code_matches_documented_signing_url(): void
    {
        $root = dirname(__DIR__, 3);

        $migration = (string) file_get_contents(
            $root.'/database/migrations/2026_09_09_180000_add_provider_signing_url_to_contracts.php'
        );
        $this->assertStringContainsString("string('provider_signing_url')", $migration);

        $helper = (string) file_get_contents($root.'/app/Services/Signatures/PodpislonSigningUrl.php');
        $this->assertStringContainsString('podpislon\.ru/sign/pack/', $helper);
        $this->assertStringContainsString('function capture', $helper);

        $send = (string) file_get_contents($root.'/app/Services/Contracts/ContractPodpislonSendService.php');
        $this->assertStringContainsString('captureSigningUrl', $send);

        $status = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractSigningController.php');
        $this->assertStringContainsString('captureProviderSigningUrl', $status);
        $this->assertStringContainsString('$provider->getStatus($contract)', $status);

        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');
        $this->assertStringContainsString('id="contract-provider-signing-url"', $show);
        $this->assertStringContainsString('noopener noreferrer', $show);

        $cabinet = (string) file_get_contents($root.'/resources/views/account/documents.blade.php');
        $this->assertStringContainsString('Открыть ссылку из SMS', $cabinet);
        $this->assertStringContainsString('canClientOpenSigningUrl()', $cabinet);
        $this->assertStringContainsString('providerSigningUrl()', $cabinet);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
