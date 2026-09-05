<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#platform-payments-methods-index совпадает с правами способов оплаты платформы.
 */
final class PlatformPaymentsMethodsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_platform_payment_method_permissions(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="platform-payments-methods-index"', $html);
        $start = strpos($html, 'id="platform-payments-methods-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-package-auto-attendance-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('platformPayments', $chunk);
        $this->assertStringContainsString('Оплата платформы', $chunk);
        $this->assertStringContainsString('sort_order=34', $chunk);
        $this->assertStringContainsString('platformPayments.method.tbankSbp', $chunk);
        $this->assertStringContainsString('platformPayments.method.yookassa', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('PlatformPaymentMethods', $chunk);
        $this->assertStringContainsString('defaultMethod()', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('AuthServiceProvider', $chunk);
        $this->assertStringContainsString('partnerWallet.view', $chunk);
        $this->assertStringContainsString('servicePayments.view', $chunk);
        $this->assertStringContainsString('payment.method.tbankSBP', $chunk);
        $this->assertStringContainsString('не</b> открывают', $chunk);
        $this->assertStringContainsString('old(\'payment_method\', $platformPaymentDefaultMethod)', $chunk);
        $this->assertStringContainsString('Нет доступного способа оплаты.', $chunk);
        $this->assertStringContainsString('Некорректный способ оплаты.', $chunk);
        $this->assertStringContainsString('createPaymentYookassa', $chunk);
        $this->assertStringContainsString('partner.payment.tinkoff.sbp', $chunk);
        $this->assertStringContainsString('serialize()', $chunk);
        $this->assertStringContainsString('CreatePartnerWalletTopupRequest', $chunk);
        $this->assertStringContainsString('CreatePartnerServicePaymentRequest', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('role_base_permissions.php', $chunk);
        $this->assertStringContainsString('SEED_DEV_DATA', $chunk);
        $this->assertStringContainsString('channel=acquiring', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodUxFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PlatformPaymentsMethodPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('partner-wallet#tbank-sbp', $chunk);
        $this->assertStringContainsString('tbank-acquiring-platform-index', $chunk);

        $this->assertStringNotContainsString('витринный СБП открывает кошелёк', $chunk);
        $this->assertStringNotContainsString('миграция выдаёт T‑Bank существующим', $chunk);
        $this->assertStringNotContainsString('ЮKassa в базовой роли admin', $chunk);
        $this->assertStringContainsString('/doc#platform-payments-methods-index', $html);
    }

    public function test_related_pages_link_platform_payments_announcement(): void
    {
        $wallet = $this->docFile('partner-wallet.html');
        $service = $this->docFile('partner-service-payments.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $partners = $this->docFile('partners-permissions.html');
        $systems = $this->docFile('settings-payment-systems.html');
        $tbank = $this->docFile('tbank.html');
        $index = $this->docFile('index.html');

        foreach ([$wallet, $service, $groups, $partners, $systems, $tbank, $index] as $html) {
            $this->assertStringContainsString('/doc#platform-payments-methods-index', $html);
        }

        $this->assertStringContainsString('payment.method.tbankSBP', $wallet);
        $this->assertStringContainsString('без</b> выдачи ролям существующим школам', $groups);
        $this->assertStringContainsString('platformPayments.method.yookassa', $partners);
    }

    public function test_documentation_controller_mentions_platform_payments_group(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('platformPayments.method.*', $controller);
        $this->assertStringContainsString("'settings-permission-groups'", $controller);
        $this->assertStringContainsString('T‑Bank СБП / ЮKassa по platformPayments.method.*', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
