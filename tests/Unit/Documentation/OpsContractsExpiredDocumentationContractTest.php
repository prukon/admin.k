<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#ops-contracts-expired-index совпадает с строкой «Договоры» пульта:
 * fill_expired / sms_expired, ховер ученик+школа, leftover.
 */
final class OpsContractsExpiredDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_contracts_expired_row(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-contracts-expired-index"', $html);
        $start = strpos($html, 'id="ops-contracts-expired-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-documents-expiry-notice-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('Договоры', $chunk);
        $this->assertStringContainsString('contracts-fill-expired', $chunk);
        $this->assertStringContainsString('contracts-sms-expired', $chunk);
        $this->assertStringContainsString('fill_expired_count', $chunk);
        $this->assertStringContainsString('sms_expired_count', $chunk);
        $this->assertStringContainsString('awaiting_client_fill', $chunk);
        $this->assertStringContainsString('fill_expires_at', $chunk);
        $this->assertStringContainsString('CREATION_MODE_TEMPLATE', $chunk);
        $this->assertStringContainsString('STATUS_EXPIRED', $chunk);
        $this->assertStringContainsString('Подпислон', $chunk);
        $this->assertStringContainsString('Без школы', $chunk);
        $this->assertStringContainsString('#id', $chunk);
        $this->assertStringContainsString('CONTRACTS_HOVER_LIMIT', $chunk);
        $this->assertStringContainsString('и ещё', $chunk);
        $this->assertStringContainsString('KidsCrmTooltip', $chunk);
        $this->assertStringContainsString('textContent', $chunk);
        $this->assertStringContainsString('leftover', $chunk);
        $this->assertStringContainsString('countTone', $chunk);
        $this->assertStringContainsString('is-bad', $chunk);
        $this->assertStringContainsString('OpsMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsContractsExpiredFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsContractsExpiredUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsContractsExpiredAccessFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringNotContainsString('окно 24 ч для fill_expired', $chunk);
        $this->assertStringNotContainsString('ttl_hours Подпислона в fill_expires_at', $chunk);
    }

    public function test_live_code_matches_announced_contracts_expired_contract(): void
    {
        $ops = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/OpsMonitor.php');
        $this->assertStringContainsString('function contractsSnapshot', $ops);
        $this->assertStringContainsString('CONTRACTS_HOVER_LIMIT', $ops);
        $this->assertStringContainsString('CREATION_MODE_TEMPLATE', $ops);
        $this->assertStringContainsString('STATUS_AWAITING_CLIENT_FILL', $ops);
        $this->assertStringContainsString('STATUS_EXPIRED', $ops);
        $this->assertStringContainsString('fill_expires_at', $ops);
        $this->assertStringContainsString("'fill_expired_count'", $ops);
        $this->assertStringContainsString("'sms_expired_count'", $ops);
        $this->assertStringContainsString('Без школы', $ops);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/system_monitors/ops.blade.php');
        $this->assertStringContainsString('ops-monitors__label">Договоры', $blade);
        $this->assertStringContainsString('data-role="contracts-fill-expired"', $blade);
        $this->assertStringContainsString('data-role="contracts-sms-expired"', $blade);
        $this->assertStringContainsString('function formatContractRows(', $blade);
        $this->assertStringContainsString("setHint('contracts-fill-expired'", $blade);
        $this->assertStringContainsString("setHint('contracts-sms-expired'", $blade);
        $this->assertStringContainsString('countTone(contracts.fill_expired_count)', $blade);
        $this->assertStringContainsString('countTone(contracts.sms_expired_count)', $blade);
        $this->assertStringContainsString('и ещё ', $blade);
    }

    public function test_parent_docs_link_the_announcement(): void
    {
        $overlay = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#ops-contracts-expired-index', $overlay);

        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('/doc#ops-contracts-expired-index', $cabinet);

        $chat = $this->docFile('chat.html');
        $this->assertStringContainsString('/doc#ops-contracts-expired-index', $chat);

        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('/doc#ops-contracts-expired-index', $groups);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
