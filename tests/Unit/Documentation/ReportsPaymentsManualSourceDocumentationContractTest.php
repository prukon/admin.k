<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class ReportsPaymentsManualSourceDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_payments_manual_source(): void
    {
        $html = $this->docFile('index.html');
        $this->assertStringContainsString('id="reports-payments-manual-source-index"', $html);

        $start = strpos($html, 'id="reports-payments-manual-source-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-lesson-package-bind-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('payment_source', $chunk);
        $this->assertStringContainsString('is_manual_paid', $chunk);
        $this->assertStringContainsString('users_prices', $chunk);
        $this->assertStringContainsString('user_lesson_packages', $chunk);
        $this->assertStringContainsString('user_custom_payment', $chunk);
        $this->assertStringContainsString('/admin/reports/payments', $chunk);
        $this->assertStringContainsString('PaymentsReportManualSourceFeatureTest', $chunk);
        $this->assertStringContainsString('reports-payments#payment-source', $chunk);
    }

    public function test_reports_payments_documents_source_filter(): void
    {
        $payments = $this->docFile('reports-payments.html');
        $this->assertStringContainsString('id="payment-source"', $payments);
        $this->assertStringContainsString('pay-filter-source', $payments);
        $this->assertStringContainsString('payment_source', $payments);
        $this->assertStringContainsString('is_manual_paid', $payments);
        $this->assertStringContainsString('/doc#reports-payments-manual-source-index', $payments);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
