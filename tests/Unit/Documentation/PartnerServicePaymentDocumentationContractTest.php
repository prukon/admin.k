<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * /doc и partner-service-payments.html совпадают с изоляцией абонплаты по PartnerContext.
 */
final class PartnerServicePaymentDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_service_payment_isolation(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="partner-service-payment-isolation-index"', $html);
        $start = strpos($html, 'id="partner-service-payment-isolation-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="payment-notifications-sbp-link-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/partner-payment/recharge', $chunk);
        $this->assertStringContainsString('/partner-payment/history', $chunk);
        $this->assertStringContainsString('CreatePartnerServicePaymentRequest', $chunk);
        $this->assertStringContainsString('servicePayments.view', $chunk);
        $this->assertStringContainsString('PartnerContext', $chunk);
        $this->assertStringContainsString('STRICT_CURRENT', $chunk);
        $this->assertStringContainsString('value="1"', $chunk);
        $this->assertStringContainsString('Нельзя оплатить сервис за другую школу.', $chunk);
        $this->assertStringContainsString('PartnerServicePaymentPartnerIsolationFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerServicePaymentAccessFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-service-payments', $chunk);
        $this->assertStringContainsString('/doc#partner-service-payment-isolation-index', $html);
    }

    public function test_service_payment_page_matches_code_contract(): void
    {
        $html = $this->docFile('partner-service-payments.html');

        $this->assertStringContainsString('/doc#partner-service-payment-isolation-index', $html);
        $this->assertStringContainsString('CreatePartnerServicePaymentRequest', $html);
        $this->assertStringContainsString('servicePayments.view', $html);
        $this->assertStringContainsString('PartnerContext', $html);
        $this->assertStringContainsString('STRICT_CURRENT', $html);
        $this->assertStringContainsString('Нельзя оплатить сервис за другую школу.', $html);
        $this->assertStringContainsString('latestActiveAccessEndDateForPartner', $html);
        $this->assertStringContainsString('PartnerServicePaymentPartnerIsolationFeatureTest', $html);
        $this->assertStringContainsString('PartnerServicePaymentAccessFeatureTest', $html);
        $this->assertStringContainsString('PartnerServicePaymentDocumentationContractTest', $html);
        $this->assertStringContainsString('data-error-for="partner_id"', $html);
        $this->assertStringContainsString('session errors[field]', $html);
    }

    public function test_page_titles_include_service_payments(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'partner-service-payments'", $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
