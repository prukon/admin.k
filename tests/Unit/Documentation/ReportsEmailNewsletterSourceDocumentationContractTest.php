<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-email-newsletter-source-index совпадает с колонкой/фильтром
 * «Email рассылка» в «Платежи» и «Платежные запросы».
 */
final class ReportsEmailNewsletterSourceDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_email_newsletter_source(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-email-newsletter-source-index"', $html);
        $start = strpos($html, 'id="reports-email-newsletter-source-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-lesson-package-bind-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('up_public_pay', $chunk);
        $this->assertStringContainsString('ulp_public_pay', $chunk);
        $this->assertStringContainsString('/pm/{code}', $chunk);
        $this->assertStringContainsString('/admin/reports/payments', $chunk);
        $this->assertStringContainsString('/admin/reports/payment-intents', $chunk);
        $this->assertStringContainsString('email_newsletter', $chunk);
        $this->assertStringContainsString('EmailNewsletterPaymentSource', $chunk);
        $this->assertStringContainsString('ReportsEmailNewsletterSourceFeatureTest', $chunk);
        $this->assertStringContainsString('reports-payments#email-newsletter-source', $chunk);
        $this->assertStringContainsString('/doc#reports-email-newsletter-source-index', $html);
    }

    public function test_reports_payments_and_admin_document_column_and_filter(): void
    {
        $payments = $this->docFile('reports-payments.html');
        $this->assertStringContainsString('id="email-newsletter-source"', $payments);
        $this->assertStringContainsString('email_newsletter', $payments);
        $this->assertStringContainsString('up_public_pay', $payments);
        $this->assertStringContainsString('ulp_public_pay', $payments);
        $this->assertStringContainsString('pay-filter-email-newsletter', $payments);
        $this->assertStringContainsString('EmailNewsletterPaymentSource', $payments);
        $this->assertStringContainsString('/doc#reports-email-newsletter-source-index', $payments);

        $admin = $this->docFile('reports-admin.html');
        $this->assertStringContainsString('email_newsletter', $admin);
        $this->assertStringContainsString('up_public_pay', $admin);

        $notify = $this->docFile('setting-prices-payment-notifications.html');
        $this->assertStringContainsString('email_newsletter', $notify);
        $this->assertStringContainsString('reports-payments#email-newsletter-source', $notify);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
