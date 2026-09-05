<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#tbank-acquiring-platform-index совпадает с обычным эквайрингом:
 * кошелёк и абонплата по platformPayments.method.*, отдельный вебхук, QR без tbankSBP.
 */
final class TbankAcquiringDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_tbank_acquiring_platform_sales(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="tbank-acquiring-platform-index"', $html);
        $start = strpos($html, 'id="tbank-acquiring-platform-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="legal-entities-podpislon-key-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('name=tbank_acquiring', $chunk);
        $this->assertStringContainsString('не через мультирасчёты', $chunk);
        $this->assertStringContainsString('CreateDealWithType', $chunk);
        $this->assertStringContainsString('без ShopCode', $chunk);
        $this->assertStringContainsString('Vat: null', $chunk);
        $this->assertStringContainsString('partners.email', $chunk);
        $this->assertStringContainsString('T‑Банк (эквайринг)', $chunk);
        $this->assertStringContainsString('e2c_*', $chunk);
        $this->assertStringContainsString('superadmin', $chunk);
        $this->assertStringContainsString('TbankAcquiringTerminalConfig', $chunk);
        $this->assertStringContainsString('/partner-wallet', $chunk);
        $this->assertStringContainsString('payment_method', $chunk);
        $this->assertStringContainsString('yookassa', $chunk);
        $this->assertStringContainsString('10 ₽', $chunk);
        $this->assertStringContainsString('Пополнение баланса KidsCRM', $chunk);
        $this->assertStringContainsString('source=platform', $chunk);
        $this->assertStringContainsString('Вернуться в кошелёк', $chunk);
        $this->assertStringContainsString('/partner-payment/recharge', $chunk);
        $this->assertStringContainsString('payment/service/tinkoff-sbp', $chunk);
        $this->assertStringContainsString('К истории оплаты сервиса', $chunk);
        $this->assertStringContainsString('/partner-payment/history', $chunk);
        $this->assertStringContainsString('Оплата доступа KidsCRM', $chunk);
        $this->assertStringContainsString('/webhooks/tinkoff/acquiring', $chunk);
        $this->assertStringContainsString('channel=acquiring', $chunk);
        $this->assertStringContainsString('partnerWallet.view', $chunk);
        $this->assertStringContainsString('servicePayments.view', $chunk);
        $this->assertStringContainsString('platformPayments.method.tbankSbp', $chunk);
        $this->assertStringContainsString('platformPayments.method.yookassa', $chunk);
        $this->assertStringContainsString('Оплата платформы', $chunk);
        $this->assertStringContainsString('payment.method.tbankSBP', $chunk);
        $this->assertStringContainsString('source=marketplace', $chunk);
        $this->assertStringContainsString('TbankAcquiringPlatformPaymentsFeatureTest', $chunk);
        $this->assertStringContainsString('TbankAcquiringAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TbankAcquiringAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('TbankAcquiringNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('TbankAcquiringUxFeatureTest', $chunk);
        $this->assertStringContainsString('TbankAcquiringQrAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodUxFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TbankAcquiringDocumentationContractTest', $chunk);
        $this->assertStringContainsString('platform-payments-methods-index', $chunk);
        $this->assertStringContainsString('partner-wallet#tbank-sbp', $chunk);
        $this->assertStringContainsString('settings-payment-systems#tbank-acquiring', $chunk);

        $this->assertStringNotContainsString('абонемент родителей', $chunk);
        $this->assertStringNotContainsString('CreateDealWithType</code> для кошелька', $chunk);
        $this->assertStringNotContainsString('мультирасчётный вебхук подтверждает acquiring', $chunk);
        $this->assertStringNotContainsString('QR acquiring требует tbankSBP', $chunk);
    }

    public function test_related_doc_pages_link_acquiring_announcement(): void
    {
        $wallet = $this->docFile('partner-wallet.html');
        $service = $this->docFile('partner-service-payments.html');
        $systems = $this->docFile('settings-payment-systems.html');
        $reports = $this->docFile('reports-admin.html');
        $tbank = $this->docFile('tbank.html');
        $index = $this->docFile('index.html');

        foreach ([$wallet, $service, $systems, $reports, $tbank, $index] as $html) {
            $this->assertStringContainsString('/doc#tbank-acquiring-platform-index', $html);
        }

        $this->assertStringContainsString('id="tbank-sbp"', $wallet);
        $this->assertStringContainsString('id="tbank-acquiring"', $systems);
        $this->assertStringContainsString('source=platform', $reports);
        $this->assertStringContainsString('8.11', $tbank);
        $this->assertStringContainsString('не этот терминал', $tbank);
    }

    public function test_page_titles_mention_acquiring_terminal(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('tbank_acquiring', $controller);
        $this->assertStringContainsString('ЮKassa и T‑Bank СБП', $controller);
        $this->assertStringContainsString('T‑Bank СБП / ЮKassa по platformPayments.method.*', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
