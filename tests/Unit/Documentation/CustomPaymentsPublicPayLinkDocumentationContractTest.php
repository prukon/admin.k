<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class CustomPaymentsPublicPayLinkDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_custom_payment_public_pay_link(): void
    {
        $html = $this->docFile('index.html');
        $this->assertStringContainsString('id="custom-payments-public-pay-link-index"', $html);

        $start = strpos($html, 'id="custom-payments-public-pay-link-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="users-contract-create-index"');
        $this->assertNotFalse($end);
        $chunk = substr($html, (int) $start, (int) $end - (int) $start);

        $this->assertStringContainsString('/pc/{short_code}', $chunk);
        $this->assertStringContainsString('Ссылка на оплату', $chunk);
        $this->assertStringContainsString('pay_link_available', $chunk);
        $this->assertStringContainsString('public-pay-link', $chunk);
        $this->assertStringContainsString('CustomPaymentPublicPayFeatureTest', $chunk);
        $this->assertStringContainsString('setting-prices-custom-payments#public-pay-link', $chunk);
        $this->assertStringContainsString('user_custom_payment.note', $chunk);
        $this->assertStringContainsString('Описание (отображается у ученика)', $chunk);
    }

    public function test_custom_payments_and_payments_docs_describe_public_link(): void
    {
        $custom = $this->docFile('setting-prices-custom-payments.html');
        $payments = $this->docFile('payments.html');

        $this->assertStringContainsString('id="public-pay-link"', $custom);
        $this->assertStringContainsString('Под суммой выводится', $custom);
        $this->assertStringContainsString('/pc/{short_code}', $custom);
        $this->assertStringContainsString('js-custom-payment-copy-pay-link', $custom);
        $this->assertStringContainsString('user_period_price_id', $custom);

        $this->assertStringContainsString('/pc/{code}', $payments);
        $this->assertStringContainsString('setting-prices-custom-payments#public-pay-link', $payments);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
