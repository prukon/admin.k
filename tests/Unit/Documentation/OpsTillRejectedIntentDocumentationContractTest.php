<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#ops-till-rejected-intent-index совпадает с till.failed_intents:
 * за 24 часа только meta.tbank.last_status = REJECTED.
 */
final class OpsTillRejectedIntentDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_rejected_intents_only(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-till-rejected-intent-index"', $html);
        $start = strpos($html, 'id="ops-till-rejected-intent-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="users-datatable-name-search-index"', $start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('till-intents', $chunk);
        $this->assertStringContainsString('till.failed_intents', $chunk);
        $this->assertStringContainsString('REJECTED', $chunk);
        $this->assertStringContainsString('CANCELED', $chunk);
        $this->assertStringContainsString('DEADLINE_EXPIRED', $chunk);
        $this->assertStringContainsString('tbank.last_status', $chunk);
        $this->assertStringContainsString('OpsMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('countTone', $chunk);
        $this->assertStringContainsString('test_failed_intents_count_only_bank_rejected_inside_24h', $chunk);
        $this->assertStringContainsString('ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('Закрытие без списания не считается', $chunk);
    }

    public function test_live_code_matches_announced_rejected_intent_contract(): void
    {
        $ops = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/OpsMonitor.php');
        $this->assertStringContainsString("->where('status', 'failed')", $ops);
        $this->assertStringContainsString("->where('meta->tbank->last_status', 'REJECTED')", $ops);
        $this->assertStringContainsString("'failed_intents' =>", $ops);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/system_monitors/ops.blade.php');
        $this->assertStringContainsString('data-role="till-intents"', $blade);
        $this->assertStringContainsString('Банк отклонил оплату (REJECTED) за последние 24 часа', $blade);
        $this->assertStringContainsString('Закрытие без списания не считается', $blade);
        $this->assertStringNotContainsString('Неуспешные Init оплаты', $blade);
    }

    public function test_parent_docs_link_the_announcement(): void
    {
        $overlay = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#ops-till-rejected-intent-index', $overlay);

        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('/doc#ops-till-rejected-intent-index', $cabinet);
        $this->assertStringContainsString('till.failed_intents', $cabinet);

        $chat = $this->docFile('chat.html');
        $this->assertStringContainsString('/doc#ops-till-rejected-intent-index', $chat);

        $payments = $this->docFile('payments.html');
        $this->assertStringContainsString('/doc#ops-till-rejected-intent-index', $payments);
        $this->assertStringContainsString('payment_intents.status=failed', $payments);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
