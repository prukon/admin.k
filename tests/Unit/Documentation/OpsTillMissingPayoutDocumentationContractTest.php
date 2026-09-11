<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#ops-till-missing-payout-index совпадает с till.overdue_payouts:
 * CONFIRMED без COMPLETED после задержки партнёра, не будущая INITIATED.
 */
final class OpsTillMissingPayoutDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_till_missing_payout_after_delay(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-till-missing-payout-index"', $html);
        $start = strpos($html, 'id="ops-till-missing-payout-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-monthly-former-charge-clear-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('till-overdue', $chunk);
        $this->assertStringContainsString('till.overdue_payouts', $chunk);
        $this->assertStringContainsString('CONFIRMED', $chunk);
        $this->assertStringContainsString('multisplit', $chunk);
        $this->assertStringContainsString('COMPLETED', $chunk);
        $this->assertStringContainsString('auto_payout_delay_hours', $chunk);
        $this->assertStringContainsString('pickForPartner', $chunk);
        $this->assertStringContainsString('when_to_run', $chunk);
        $this->assertStringContainsString('INITIATED', $chunk);
        $this->assertStringContainsString('REJECTED', $chunk);
        $this->assertStringContainsString('is-bad', $chunk);
        $this->assertStringContainsString('missingPayoutAfterDelay', $chunk);
        $this->assertStringContainsString('OpsMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('overdueScheduled', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('confirmed_at', $chunk);
        $this->assertStringContainsString('channel=acquiring', $chunk);
        $this->assertStringContainsString('AUTHORIZING', $chunk);
        $this->assertStringContainsString('CREDIT_CHECKING', $chunk);
        $this->assertStringContainsString('tinkoff_payout_payment_id', $chunk);
        $this->assertStringContainsString('auto_payout_enabled', $chunk);
        $this->assertStringContainsString('without_payout', $chunk);
        $this->assertStringContainsString('countTone', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
    }

    public function test_live_code_matches_announced_till_missing_payout_contract(): void
    {
        $model = (string) file_get_contents(dirname(__DIR__, 3).'/app/Models/TinkoffPayment.php');
        $this->assertStringContainsString('function scopeMissingPayoutAfterDelay', $model);
        $this->assertStringContainsString('CHANNEL_MULTISPLIT', $model);
        $this->assertStringContainsString('auto_payout_delay_hours', $model);
        $this->assertStringContainsString("where('tinkoff_payouts.status', 'COMPLETED')", $model);
        $this->assertStringContainsString("where('tinkoff_payouts.status', 'INITIATED')", $model);
        $this->assertStringContainsString('when_to_run', $model);
        $this->assertStringNotContainsString('auto_payout_enabled', $model);

        $ops = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/OpsMonitor.php');
        $this->assertStringContainsString('missingPayoutAfterDelay()', $ops);
        $this->assertStringContainsString("'overdue_payouts' => (int) TinkoffPayment::query()->missingPayoutAfterDelay()->count()", $ops);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/system_monitors/ops.blade.php');
        $this->assertStringContainsString('data-role="till-overdue"', $blade);
        $this->assertStringContainsString('CONFIRMED без успешной выплаты после задержки автовыплаты партнёра', $blade);
        $this->assertStringContainsString("countTone(till.overdue_payouts)", $blade);
    }

    public function test_parent_docs_link_the_announcement(): void
    {
        $overlay = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#ops-till-missing-payout-index', $overlay);

        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('/doc#ops-till-missing-payout-index', $cabinet);

        $queues = $this->docFile('queues-monitoring.html');
        $this->assertStringContainsString('/doc#ops-till-missing-payout-index', $queues);

        $tbank = $this->docFile('tbank.html');
        $this->assertStringContainsString('/doc#ops-till-missing-payout-index', $tbank);
        $this->assertStringContainsString('till.overdue_payouts', $tbank);

        $chat = $this->docFile('chat.html');
        $this->assertStringContainsString('/doc#ops-till-missing-payout-index', $chat);

        $payouts = $this->docFile('tbank-admin-payouts.html');
        $this->assertStringContainsString('/doc#ops-till-missing-payout-index', $payouts);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
