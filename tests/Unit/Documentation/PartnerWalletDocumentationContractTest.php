<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * /doc и partner-wallet.html совпадают с изоляцией кошелька по PartnerContext.
 */
final class PartnerWalletDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_partner_wallet_isolation(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="partner-wallet-isolation-index"', $html);
        $start = strpos($html, 'id="partner-wallet-isolation-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="payment-notifications-sbp-link-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/partner-wallet', $chunk);
        $this->assertStringContainsString('PartnerContext', $chunk);
        $this->assertStringContainsString('STRICT_CURRENT', $chunk);
        $this->assertStringContainsString('wallet_balance_cents', $chunk);
        $this->assertStringContainsString('CreatePartnerWalletTopupRequest', $chunk);
        $this->assertStringContainsString('Нельзя пополнить кошелёк другой школы.', $chunk);
        $this->assertStringContainsString('guardPartnerAccess()', $chunk);
        $this->assertStringContainsString('partnerWallet.view', $chunk);
        $this->assertStringContainsString('PartnerWalletPartnerIsolationFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletUxFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('PartnerWalletDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-wallet', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-wallet', $html);
        $this->assertStringContainsString('/doc#partner-wallet-isolation-index', $html);
    }

    public function test_partner_wallet_page_matches_code_contract(): void
    {
        $html = $this->docFile('partner-wallet.html');

        $this->assertStringContainsString('/doc#partner-wallet-isolation-index', $html);
        $this->assertStringContainsString('STRICT_CURRENT', $html);
        $this->assertStringContainsString('PartnerContext', $html);
        $this->assertStringContainsString('wallet_balance_cents', $html);
        $this->assertStringContainsString('CreatePartnerWalletTopupRequest', $html);
        $this->assertStringContainsString('guardPartnerAccess()', $html);
        $this->assertStringContainsString('partnerWallet.view', $html);
        $this->assertStringContainsString('AdminBaseController', $html);
        $this->assertStringContainsString('PartnerWalletPartnerIsolationFeatureTest', $html);
        $this->assertStringContainsString('PartnerWalletAccessFeatureTest', $html);
        $this->assertStringContainsString('PartnerWalletAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('PartnerWalletNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('PartnerWalletUxFeatureTest', $html);
        $this->assertStringContainsString('PartnerWalletFullAccessFeatureTest', $html);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest::test_partner_wallet_topup_ajax_prevents_native_submit_and_shows_field_errors', $html);
        $this->assertStringContainsString('session errors[field]', $html);
        $this->assertStringContainsString('errors[field]', $html);
        $this->assertStringContainsString('data-error-for="amount"', $html);
        $this->assertStringContainsString('Ваша организация недоступна.', $html);
        $this->assertStringContainsString('не <code>Partner::first()</code>', $html);
        $this->assertStringContainsString('PartnerWalletDocumentationContractTest', $html);
    }

    public function test_page_titles_include_partner_wallet(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'partner-wallet'", $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
