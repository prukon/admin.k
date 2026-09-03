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
        $end = strpos($html, 'id="partner-service-payment-isolation-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/partner-wallet', $chunk);
        $this->assertStringContainsString('Partner::first()', $chunk);
        $this->assertStringContainsString('PartnerContext', $chunk);
        $this->assertStringContainsString('STRICT_CURRENT', $chunk);
        $this->assertStringContainsString('wallet_balance_cents', $chunk);
        $this->assertStringContainsString('CreatePartnerWalletTopupRequest', $chunk);
        $this->assertStringContainsString('Нельзя пополнить кошелёк другой школы.', $chunk);
        $this->assertStringContainsString('guardPartnerAccess()', $chunk);
        $this->assertStringContainsString('в одном запросе после 422 до guard очередь не доходит', $chunk);
        $this->assertStringContainsString('partnerWallet.view', $chunk);
        $this->assertStringContainsString('/partner-wallet/success', $chunk);
        $this->assertStringContainsString('/partner-wallet/webhook', $chunk);
        $this->assertStringContainsString('/webhook/yookassa', $chunk);
        $this->assertStringContainsString('partner_wallet_topup', $chunk);
        $this->assertStringContainsString('number_format(..., 0)', $chunk);
        $this->assertStringContainsString('сумма ≥ 1 ₽', $chunk);
        $this->assertStringContainsString('session errors', $chunk);
        $this->assertStringContainsString('@error', $chunk);
        $this->assertStringNotContainsString('затем <code>guardPartnerAccess()</code> (403)', $chunk);
        $this->assertStringContainsString('PartnerWalletPartnerIsolationFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletUxFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletWebhookFeatureTest', $chunk);
        $this->assertStringContainsString('ContractBillingWalletLedgerFeatureTest', $chunk);
        $this->assertStringContainsString('tx.partner_id', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('PartnerWalletDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#partner-wallet-ledger-index', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-wallet', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-wallet', $html);
        $this->assertStringContainsString('/doc#partner-wallet-isolation-index', $html);
        $this->assertStringContainsString('/doc#partner-wallet-ledger-index', $html);
    }

    public function test_doc_index_announces_wallet_ledger_and_native_field_errors(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="partner-wallet-ledger-index"', $html);
        $start = strpos($html, 'id="partner-wallet-ledger-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="school-leads-email-notifications-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/partner-wallet', $chunk);
        $this->assertStringContainsString('partner_wallet_transactions', $chunk);
        $this->assertStringContainsString('Создание договора', $chunk);
        $this->assertStringContainsString('Возврат: отзыв договора', $chunk);
        $this->assertStringContainsString('contract_create', $chunk);
        $this->assertStringContainsString('awaiting_client_fill', $chunk);
        $this->assertStringContainsString('provider=refund', $chunk);
        $this->assertStringContainsString('не бэкфиллят', $chunk);
        $this->assertStringContainsString('partner_payments', $chunk);
        $this->assertStringContainsString('data-error-for', $chunk);
        $this->assertStringContainsString('@error', $chunk);
        $this->assertStringContainsString('is-invalid', $chunk);
        $this->assertStringContainsString('old()', $chunk);
        $this->assertStringContainsString('tx.partner_id', $chunk);
        $this->assertStringContainsString('metadata.partner_id', $chunk);
        $this->assertStringContainsString('PartnerContext', $chunk);
        $this->assertStringContainsString('ContractBillingWalletLedgerFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletWebhookFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerWalletNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-wallet', $chunk);
        $this->assertStringContainsString('/docs/documentation/contracts', $chunk);
        $this->assertStringContainsString('/docs/documentation/money', $chunk);
        $this->assertStringContainsString('/doc#partner-wallet-isolation-index', $chunk);
        $this->assertStringContainsString('/doc#partner-wallet-ledger-index', $html);
    }

    public function test_partner_wallet_page_matches_code_contract(): void
    {
        $html = $this->docFile('partner-wallet.html');

        $this->assertStringContainsString('/doc#partner-wallet-isolation-index', $html);
        $this->assertStringContainsString('/doc#partner-wallet-ledger-index', $html);
        $this->assertStringContainsString('data-error-for="description"', $html);
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
        $this->assertStringContainsString('PartnerWalletWebhookFeatureTest', $html);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest::test_partner_wallet_topup_ajax_prevents_native_submit_and_shows_field_errors', $html);
        $this->assertStringContainsString('session errors[field]', $html);
        $this->assertStringContainsString('errors[field]', $html);
        $this->assertStringContainsString('data-error-for="amount"', $html);
        $this->assertStringContainsString('@error', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('Ваша организация недоступна.', $html);
        $this->assertStringContainsString('не <code>Partner::first()</code>', $html);
        $this->assertStringContainsString('PartnerWalletDocumentationContractTest', $html);
        $this->assertStringContainsString('ContractBillingWalletLedgerFeatureTest', $html);
        $this->assertStringContainsString('в одном запросе после 422', $html);
        $this->assertStringContainsString('/partner-wallet/success', $html);
        $this->assertStringContainsString('/partner-wallet/webhook', $html);
        $this->assertStringContainsString('/webhook/yookassa', $html);
        $this->assertStringContainsString('partner_wallet_topup', $html);
        $this->assertStringContainsString('YooKassaWebhookController', $html);
        $this->assertStringContainsString('Сумма должна быть не меньше 1 ₽.', $html);
        $this->assertStringContainsString('number_format(..., 0)', $html);
        $this->assertStringContainsString('number_format(..., 2)', $html);
        $this->assertStringContainsString('PartnerContext::partner()', $html);
        $this->assertStringContainsString("session('partner_id')", $html);
        $this->assertStringContainsString('Client::setAuth()', $html);
        $this->assertStringContainsString('metadata.wallet_transaction_id', $html);
        $this->assertStringContainsString('не</b> <code>STRICT_CURRENT</code>', $html);
        $this->assertStringContainsString('PartnerWalletWebhookFeatureTest', $html);
        $this->assertStringContainsString('400 vs 422', $html);
        $this->assertStringContainsString('metadata.partner_id', $html);
    }

    public function test_page_titles_include_partner_wallet(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'partner-wallet'", $controller);
        $this->assertStringContainsString('история договоров', $controller);
    }

    public function test_contracts_and_money_docs_link_wallet_ledger_announcement(): void
    {
        $contracts = $this->docFile('contracts.html');
        $money = $this->docFile('money.html');

        $this->assertStringContainsString('/doc#partner-wallet-ledger-index', $contracts);
        $this->assertStringContainsString('ContractBillingWalletLedgerFeatureTest', $contracts);
        $this->assertStringContainsString('partner_wallet_transactions', $contracts);
        $this->assertStringContainsString('/doc#partner-wallet-ledger-index', $money);
        $this->assertStringContainsString('contract_create_fee', $money);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
