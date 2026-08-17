<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * /doc и admin-layout-sidebar.html совпадают с фактическим layout:
 * gutter в admin2, не в toolbar-CSS; локальный FA 6; лендинг без admin-слота.
 */
final class AdminLayoutSidebarDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_sidebar_stability_without_contradicting_layout(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="admin-sidebar-stability-index"', $html);
        $start = strpos($html, 'id="admin-sidebar-stability-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reverb-status-overlay-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('scrollbar-gutter: stable', $chunk);
        $this->assertStringContainsString('admin2', $chunk);
        $this->assertStringContainsString('admin-list-toolbar.css', $chunk);
        $this->assertStringContainsString('Font Awesome Free 6.5.1', $chunk);
        $this->assertStringContainsString('includes/fontawesome', $chunk);
        $this->assertStringContainsString('ka-f.fontawesome.com', $chunk);
        $this->assertStringContainsString('AdminSidebarLayoutStabilityFeatureTest', $chunk);
        $this->assertStringContainsString('users.layout_wide', $chunk);
        $this->assertStringContainsString('FontAwesomeSelfHostedFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/admin-layout-sidebar', $chunk);
        $this->assertStringNotContainsString('js/fontawesome/fontawesome.js', $chunk);
        $this->assertStringNotContainsString('OverlayScrollbars.min.css', $chunk);
    }

    public function test_sidebar_page_matches_admin2_and_landing_split(): void
    {
        $html = $this->docFile('admin-layout-sidebar.html');

        $this->assertStringContainsString('layouts/admin2.blade.php', $html);
        $this->assertStringContainsString('includes/fontawesome.blade.php', $html);
        $this->assertStringContainsString('landingPage.blade.php', $html);
        $this->assertStringContainsString('documentations.view', $html);
        $this->assertStringContainsString('dashboard.view', $html);
        $this->assertStringContainsString('scrollbar-gutter: stable', $html);
        $this->assertStringContainsString('admin-list-toolbar.css', $html);
        $this->assertStringContainsString('Font Awesome Free 6.5.1', $html);
        $this->assertStringContainsString('fa-solid-900.woff2', $html);
        $this->assertStringContainsString('public/plugins/overlayScrollbars/', $html);
        $this->assertStringContainsString('не</b> подключаем', $html);
        $this->assertStringContainsString('Vite', $html);
        $this->assertStringContainsString('AdminSidebarLayoutStabilityFeatureTest', $html);
        $this->assertStringContainsString('CabinetLayoutWideFeatureTest', $html);
        $this->assertStringContainsString('users.layout_wide', $html);
        $this->assertStringContainsString('includes/layout_wide_toggle.blade.php', $html);
        $this->assertStringContainsString('FontAwesomeSelfHostedFeatureTest', $html);
        $this->assertStringContainsString('/doc#admin-sidebar-stability-index', $html);
        $this->assertStringNotContainsString('npm run build обязателен', $html);
        $this->assertStringNotContainsString('gutter только в toolbar', $html);
    }

    public function test_controller_title_and_catalog_point_to_the_same_page(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('admin-layout-sidebar', $index);
        $this->assertStringContainsString('локальный FA 6.5.1', $index);
        $this->assertStringContainsString("'admin-layout-sidebar'", $controller);
        $this->assertStringContainsString('локальный Font Awesome 6.5.1 вместо Kit/CDN', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
