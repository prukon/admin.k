<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#ops-row-copy-index совпадает с живым пультом:
 * copy-row у заголовка строки, is-bad/is-warn, статика+живой ховер, без нового HTTP.
 */
final class OpsRowCopyDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ops_row_copy_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-row-copy-index"', $html);
        $start = strpos($html, 'id="ops-row-copy-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-list-hide-remaining-signed-revoked-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('data-role="copy-row"', $chunk);
        $this->assertStringContainsString('fa-copy', $chunk);
        $this->assertStringContainsString('Копировать красные и жёлтые', $chunk);
        $this->assertStringContainsString('is-bad', $chunk);
        $this->assertStringContainsString('is-warn', $chunk);
        $this->assertStringContainsString('is-ok', $chunk);
        $this->assertStringContainsString('is-muted', $chunk);
        $this->assertStringContainsString('rowProblemText', $chunk);
        $this->assertStringContainsString('[data-role].is-bad, [data-role].is-warn', $chunk);
        $this->assertStringContainsString('data-ops-hint-default', $chunk);
        $this->assertStringContainsString('last_message', $chunk);
        $this->assertStringContainsString('Пульт ·', $chunk);
        $this->assertStringContainsString('красных и жёлтых показателей нет', $chunk);
        $this->assertStringContainsString('navigator.clipboard.writeText', $chunk);
        $this->assertStringContainsString('is-copied', $chunk);
        $this->assertStringContainsString('bindRowCopyButtons', $chunk);
        $this->assertStringContainsString('GET /cabinet/system-monitors/ops', $chunk);
        $this->assertStringContainsString('ops-monitors__head', $chunk);
        $this->assertStringContainsString('Сегодня', $chunk);
        $this->assertStringContainsString('Договоры', $chunk);
        $this->assertStringContainsString('jobs', $chunk);
        $this->assertStringContainsString('errors-top', $chunk);
        $this->assertStringContainsString('my_logs', $chunk);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $chunk);
        $this->assertStringContainsString('chat#ops-monitors-overlay', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsUxFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('OpsRowCopyDocumentationContractTest', $chunk);
        $this->assertStringNotContainsString('копирует весь пульт одной кнопкой', $chunk);
        $this->assertStringNotContainsString('новый GET copy-row', $chunk);
        $this->assertStringNotContainsString('копирует is-ok', $chunk);
    }

    public function test_live_blade_matches_announced_row_copy_contract(): void
    {
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/system_monitors/ops.blade.php');
        $this->assertSame(9, substr_count($blade, 'class="ops-monitors__copy" data-role="copy-row"'));
        $this->assertStringContainsString('ops-monitors__head', $blade);
        $this->assertStringContainsString('Копировать красные и жёлтые', $blade);
        $this->assertStringContainsString('function rowProblemText(', $blade);
        $this->assertStringContainsString('[data-role].is-bad, [data-role].is-warn', $blade);
        $this->assertStringContainsString('красных и жёлтых показателей нет', $blade);
        $this->assertStringContainsString('Пульт · ', $blade);
        $this->assertStringContainsString('data-ops-hint-default', $blade);
        $this->assertStringContainsString('navigator.clipboard.writeText', $blade);
        $this->assertStringContainsString('function bindRowCopyButtons(', $blade);
        $this->assertStringContainsString('is-copied', $blade);
        $this->assertStringContainsString("queue.jobs > 20", $blade);
        $this->assertStringContainsString("setText('errors-top'", $blade);
        $this->assertStringNotContainsString('wrap.innerHTML', $blade);
        $this->assertStringNotContainsString('cabinet.system-monitors.ops.copy', $blade);
    }

    public function test_parent_docs_link_the_announcement(): void
    {
        $overlay = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#ops-row-copy-index', $overlay);

        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('/doc#ops-row-copy-index', $cabinet);
        $this->assertStringContainsString('OpsRowCopyDocumentationContractTest', $cabinet);

        $chat = $this->docFile('chat.html');
        $this->assertStringContainsString('/doc#ops-row-copy-index', $chat);

        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('/doc#ops-row-copy-index', $groups);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('ops-row-copy-index', $controller);
        $this->assertStringContainsString('copy-row', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
