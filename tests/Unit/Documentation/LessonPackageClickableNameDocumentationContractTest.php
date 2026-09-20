<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#lesson-package-clickable-name-index совпадает с колонкой «Название» шаблона.
 */
final class LessonPackageClickableNameDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_clickable_package_name(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="lesson-package-clickable-name-index"', $html);
        $start = strpos($html, 'id="lesson-package-clickable-name-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-email-newsletter-source-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/lesson-packages', $chunk);
        $this->assertStringContainsString('/admin/directories/lesson-packages', $chunk);
        $this->assertStringContainsString('admin/lessonPackages/tabs/packages.blade.php', $chunk);
        $this->assertStringContainsString("type: 'link'", $chunk);
        $this->assertStringContainsString('lesson-package-edit-btn', $chunk);
        $this->assertStringContainsString('#lessonPackageEditModal', $chunk);
        $this->assertStringContainsString('GET /admin/lesson-packages/{id}', $chunk);
        $this->assertStringContainsString('lessonPackages.view', $chunk);
        $this->assertStringContainsString('DataTableClickableNameLinksFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageClickableNameDocumentationContractTest', $chunk);
        $this->assertStringContainsString('lesson-packages#clickable-name', $chunk);
        $this->assertStringContainsString('reusable-ui-partials#datatable-clickable-names', $chunk);
        $this->assertStringContainsString('/doc#lesson-package-clickable-name-index', $html);
    }

    public function test_lesson_packages_page_and_live_code_match_clickable_name(): void
    {
        $html = $this->docFile('lesson-packages.html');
        $this->assertStringContainsString('id="clickable-name"', $html);
        $this->assertStringContainsString('/doc#lesson-package-clickable-name-index', $html);
        $this->assertStringContainsString('lesson-package-edit-btn', $html);
        $this->assertStringContainsString("type: 'link'", $html);
        $this->assertStringContainsString('#lessonPackageEditModal', $html);
        $this->assertStringContainsString('GET /admin/lesson-packages/{id}', $html);
        $this->assertStringContainsString('DataTableClickableNameLinksFeatureTest', $html);
        $this->assertStringContainsString('reusable-ui-partials#datatable-clickable-names', $html);

        $partials = $this->docFile('reusable-ui-partials.html');
        $this->assertStringContainsString('lesson-package-edit-btn', $partials);
        $this->assertStringContainsString('lesson-packages#clickable-name', $partials);
        $this->assertStringContainsString('/admin/directories/lesson-packages', $partials);

        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('кликабельное название → модалка edit', $controllerTitles);
        $this->assertStringContainsString('lesson-package-edit-btn', $controllerTitles);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/lessonPackages/tabs/packages.blade.php');
        $this->assertStringContainsString("type: 'link'", $blade);
        $this->assertStringContainsString("linkClass: 'lesson-package-edit-btn'", $blade);
        $this->assertStringContainsString("data-bs-target=\"#lessonPackageEditModal\"", $blade);
        $this->assertStringContainsString("$(document).on('click', '.lesson-package-edit-btn'", $blade);
        $this->assertStringContainsString("requestJson('GET', '/admin/lesson-packages/' + id)", $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
