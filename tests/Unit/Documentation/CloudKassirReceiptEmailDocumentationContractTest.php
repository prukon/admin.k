<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#cloudkassir-receipt-email-index и payments §7.1:
 * чек CloudKassir уходит родителю, иначе ученику, иначе школе.
 */
final class CloudKassirReceiptEmailDocumentationContractTest extends TestCase
{
    public function test_payments_doc_describes_receipt_email_order(): void
    {
        $html = $this->docFile('payments.html');

        $this->assertStringContainsString('id="cloudkassir-receipt-email"', $html);
        $this->assertStringContainsString('resolveCustomerReceiptEmail', $html);
        $this->assertStringContainsString('parents.email', $html);
        $this->assertStringContainsString('users.email', $html);
        $this->assertStringContainsString('partners.email', $html);
        $this->assertStringContainsString('test_receipt_email_prefers_parent_then_student_then_school', $html);
        $this->assertStringContainsString('CloudKassirReceiptEmailFeatureTest', $html);
        $this->assertStringContainsString('errors.comment', $html);
        $this->assertStringContainsString('/doc#cloudkassir-receipt-email-index', $html);
    }

    public function test_doc_index_announces_receipt_email_order(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="cloudkassir-receipt-email-index"', $html);
        $start = strpos($html, 'id="cloudkassir-receipt-email-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-apply-all-teams-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('resolveCustomerReceiptEmail', $chunk);
        $this->assertStringContainsString('parents.email', $chunk);
        $this->assertStringContainsString('users.email', $chunk);
        $this->assertStringContainsString('partners.email', $chunk);
        $this->assertStringContainsString('CloudKassirReceiptEmailFeatureTest', $chunk);
        $this->assertStringContainsString('CloudKassirReceiptBuilderAgentTest', $chunk);
        $this->assertStringContainsString('errors.comment', $chunk);
        $this->assertStringContainsString('CloudKassirReceiptEmailDocumentationContractTest', $chunk);
        $this->assertStringContainsString('payments#cloudkassir-receipt-email', $chunk);
        $this->assertStringContainsString('кошелёк', $chunk);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
