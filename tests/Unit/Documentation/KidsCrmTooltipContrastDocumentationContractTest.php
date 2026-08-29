<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#kids-tooltip-contrast-index совпадает с CSS KidsCrmTooltip.
 */
final class KidsCrmTooltipContrastDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_opaque_tooltip_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="kids-tooltip-contrast-index"', $html);
        $start = strpos($html, 'id="kids-tooltip-contrast-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-inbox-sort-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('ulp-assignment-paid-tooltip', $chunk);
        $this->assertStringContainsString('--bs-tooltip-opacity: 1', $chunk);
        $this->assertStringContainsString('#111827', $chunk);
        $this->assertStringContainsString('z-index: 20050', $chunk);
        $this->assertStringContainsString('#system-monitors-stack', $chunk);
        $this->assertStringContainsString('kids-tooltip.css', $chunk);
        $this->assertStringContainsString('layouts/admin2', $chunk);
        $this->assertStringContainsString('reusable-ui-partials#tooltip-contrast', $chunk);
        $this->assertStringContainsString('KidsCrmTooltipContrastFeatureTest', $chunk);
        $this->assertStringNotContainsString('--bs-tooltip-opacity: 0.9', $chunk);
        $this->assertStringNotContainsString('отдельный ховер пульта', $chunk);
    }

    public function test_reusable_ui_partials_page_matches_opaque_tooltip_contract(): void
    {
        $html = $this->docFile('reusable-ui-partials.html');

        $this->assertStringContainsString('id="tooltip-contrast"', $html);
        $start = strpos($html, 'id="tooltip-contrast"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="tooltip-hint-datatable"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('--bs-tooltip-opacity: 0.9', $chunk);
        $this->assertStringContainsString('--bs-tooltip-opacity: 1', $chunk);
        $this->assertStringContainsString('#111827', $chunk);
        $this->assertStringContainsString('#ffffff', $chunk);
        $this->assertStringContainsString('z-index: 20050', $chunk);
        $this->assertStringContainsString('#system-monitors-stack', $chunk);
        $this->assertStringContainsString('/doc#kids-tooltip-contrast-index', $chunk);
        $this->assertStringContainsString('KidsCrmTooltipContrastFeatureTest', $chunk);
        $this->assertStringContainsString('Отдельного класса ховера у пульта нет', $chunk);
    }

    public function test_documentation_controller_mentions_opaque_tooltip(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'reusable-ui-partials'", $controller);
        $this->assertStringContainsString('непрозрачный ховер', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
