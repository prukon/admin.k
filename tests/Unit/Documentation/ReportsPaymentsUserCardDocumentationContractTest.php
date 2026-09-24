<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-payments-user-card-index совпадает с карточкой ученика в отчёте «Платежи».
 */
final class ReportsPaymentsUserCardDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_payments_user_card(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-payments-user-card-index"', $html);
        $start = strpos($html, 'id="reports-payments-user-card-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-package-clickable-name-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/payments', $chunk);
        $this->assertStringContainsString('js-payment-user-card', $chunk);
        $this->assertStringContainsString('#paymentUserCardModal', $chunk);
        $this->assertStringContainsString('/admin/reports/payments/users/{user}', $chunk);
        $this->assertStringContainsString('reports.payments.users.show', $chunk);
        $this->assertStringContainsString('partials.ui.user-card-modal', $chunk);
        $this->assertStringContainsString('/admin/reports/payments/monthly', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv/teams', $chunk);
        $this->assertStringContainsString('/admin/reports/debts', $chunk);
        $this->assertStringContainsString('/admin/reports/payment-intents', $chunk);
        $this->assertStringContainsString('/schedule', $chunk);
        $this->assertStringContainsString('js-user-card', $chunk);
        $this->assertStringContainsString('#peerCardModal', $chunk);
        $this->assertStringContainsString('PaymentReportUserCardFeatureTest', $chunk);
        $this->assertStringContainsString('fa-users', $chunk);
        $this->assertStringContainsString('fa-layer-group', $chunk);
        $this->assertStringContainsString('reports-payments#user-card', $chunk);
        $this->assertStringContainsString('/doc#reports-payments-user-card-index', $html);
    }

    public function test_reports_payments_documents_user_card_and_chat_modal_stays_separate(): void
    {
        $payments = $this->docFile('reports-payments.html');
        $this->assertStringContainsString('id="user-card"', $payments);
        $this->assertStringContainsString('js-payment-user-card', $payments);
        $this->assertStringContainsString('PaymentReportUserCard', $payments);
        $this->assertStringContainsString('parent_email', $payments);
        $this->assertStringContainsString('discount_percent', $payments);
        $this->assertStringContainsString('family_siblings', $payments);
        $this->assertStringContainsString('fa-users', $payments);
        $this->assertStringContainsString('fa-layer-group', $payments);
        $this->assertStringContainsString('partials.ui.tooltip-hint', $payments);
        $this->assertStringContainsString('#peerCardModal', $payments);
        $this->assertStringContainsString('/doc#reports-payments-user-card-index', $payments);

        $journal = $this->docFile('schedule-journal.html');
        $this->assertStringContainsString('js-user-card', $journal);
        $this->assertStringContainsString('/schedule/users/{user}', $journal);
        $this->assertStringContainsString('reports-payments#user-card', $journal);

        $partials = $this->docFile('reusable-ui-partials.html');
        $this->assertStringContainsString('js-payment-user-card', $partials);
        $this->assertStringContainsString('reports-payments#user-card', $partials);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
