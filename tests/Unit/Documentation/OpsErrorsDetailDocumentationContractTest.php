<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#ops-errors-detail-index совпадает с живым пультом:
 * ховер last_message, unwrap ViewException, ring errors.recent без страницы.
 */
final class OpsErrorsDetailDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ops_errors_detail_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-errors-detail-index"', $html);
        $start = strpos($html, 'id="ops-errors-detail-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="custom-payments-edit-permission-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('Handler::report', $chunk);
        $this->assertStringContainsString('my_logs', $chunk);
        $this->assertStringContainsString('laravel.log', $chunk);
        $this->assertStringContainsString('errors.last_class', $chunk);
        $this->assertStringContainsString('errors.top_class', $chunk);
        $this->assertStringContainsString('TokenMismatchException', $chunk);
        $this->assertStringContainsString('72 часа', $chunk);
        $this->assertStringContainsString('errors.last_message', $chunk);
        $this->assertStringContainsString('errors-last', $chunk);
        $this->assertStringContainsString('KidsCrmTooltip', $chunk);
        $this->assertStringContainsString('innerHTML', $chunk);
        $this->assertStringContainsString('leftover', $chunk);
        $this->assertStringContainsString('errors-top', $chunk);
        $this->assertStringContainsString('ViewException', $chunk);
        $this->assertStringContainsString('ViewExceptionWithSolution', $chunk);
        $this->assertStringContainsString('getPrevious()', $chunk);
        $this->assertStringContainsString('base_path', $chunk);
        $this->assertStringContainsString('errors.recent', $chunk);
        $this->assertStringContainsString('getPathInfo()', $chunk);
        $this->assertStringContainsString('ops:errors:recent', $chunk);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('/doc#kids-tooltip-contrast-index', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $chunk);
        $this->assertStringContainsString('chat#ops-monitors-overlay', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('OpsErrorsDetailDocumentationContractTest', $chunk);
        $this->assertStringNotContainsString('отдельной страницы 500', $chunk);
        $this->assertStringNotContainsString('окно «Вход» 24 часа', $chunk);
        $this->assertStringNotContainsString('JSON-оверлея диагностики на <code>/cabinet</code> есть', $chunk);
        $this->assertStringNotContainsString('рисует <code>errors.recent</code>', $chunk);
    }

    public function test_dashboard_cabinet_and_chat_link_ops_errors_detail_announcement(): void
    {
        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('/doc#ops-errors-detail-index', $cabinet);
        $this->assertStringContainsString('last_message', $cabinet);
        $this->assertStringContainsString('ViewException', $cabinet);
        $this->assertStringContainsString('errors.recent', $cabinet);
        $this->assertStringContainsString('без страницы', $cabinet);

        $chat = $this->docFile('chat.html');
        $start = strpos($chat, 'id="ops-monitors-overlay"');
        $this->assertNotFalse($start);
        $end = strpos($chat, 'id="tests"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($chat, $start, $end - $start);
        $this->assertStringContainsString('/doc#ops-errors-detail-index', $chunk);
        $this->assertStringContainsString('last_message', $chunk);
        $this->assertStringContainsString('ViewException', $chunk);
        $this->assertStringContainsString('errors.recent', $chunk);
        $this->assertStringContainsString('без ссылок', $chunk);
    }

    public function test_documentation_controller_mentions_ops_errors_detail(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('last_message', $controller);
        $this->assertStringContainsString('ViewException', $controller);
        $this->assertStringContainsString('errors.recent', $controller);
        $this->assertStringContainsString('оверлей Пульт', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
