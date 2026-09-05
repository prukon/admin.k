<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#permission-capability-hints-index совпадает с матрицей прав.
 */
final class PermissionCapabilityHintsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_permission_capability_hints(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="permission-capability-hints-index"', $html);
        $start = strpos($html, 'id="permission-capability-hints-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="tbank-acquiring-platform-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/settings/rules', $chunk);
        $this->assertStringContainsString('partials.ui.tooltip-hint', $chunk);
        $this->assertStringContainsString('fa fa-info-circle', $chunk);
        $this->assertStringContainsString('permission_capability_hints.php', $chunk);
        $this->assertStringContainsString('PermissionCapabilityHint', $chunk);
        $this->assertStringContainsString('нумерованный список', $chunk);
        $this->assertStringContainsString('иконки нет', $chunk);
        $this->assertStringContainsString('литеральное', $chunk);
        $this->assertStringContainsString('config(\'permission_capability_hints.dashboard.view\')', $chunk);
        $this->assertStringContainsString('white-space: pre-wrap', $chunk);
        $this->assertStringContainsString('html: true', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('get-user-details', $chunk);
        $this->assertStringContainsString('data-bs-container="body"', $chunk);
        $this->assertStringContainsString('RulesPermissionCapabilityHintsFeatureTest', $chunk);
        $this->assertStringContainsString('settings-permission-groups#capability-hints', $chunk);
        $this->assertStringContainsString('не HTML', $chunk);
        $this->assertStringNotContainsString('автоскан', $chunk);
    }

    public function test_permission_groups_page_documents_capability_hover(): void
    {
        $html = $this->docFile('settings-permission-groups.html');

        $this->assertStringContainsString('id="capability-hints"', $html);
        $start = strpos($html, 'id="capability-hints"');
        $this->assertNotFalse($start);
        $chunk = substr($html, $start);

        $this->assertStringContainsString('partials.ui.tooltip-hint', $chunk);
        $this->assertStringContainsString('permission_capability_hints.php', $chunk);
        $this->assertStringContainsString('data-bs-container="body"', $chunk);
        $this->assertStringContainsString('PermissionCapabilityHint', $chunk);
        $this->assertStringContainsString('RulesPermissionCapabilityHintsFeatureTest', $chunk);
        $this->assertStringContainsString('/doc#permission-capability-hints-index', $chunk);
        $this->assertStringContainsString('если каталога нет — иконки нет', $chunk);
        $this->assertStringContainsString('литеральные имена', $chunk);
        $this->assertStringContainsString('dashboard.view', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('Не HTML', $chunk);
        $this->assertStringContainsString('html: true', $chunk);
    }

    public function test_reusable_ui_partials_document_tooltip_hint_container(): void
    {
        $html = $this->docFile('reusable-ui-partials.html');

        $this->assertStringContainsString("'container' => 'body'", $html);
        $this->assertStringContainsString('data-bs-container', $html);
    }

    public function test_documentation_controller_mentions_capability_hints(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'settings-permission-groups'", $controller);
        $this->assertStringContainsString('ховер возможностей', $controller);
    }

    public function test_roles_custom_page_points_to_capability_hover(): void
    {
        $html = $this->docFile('settings-roles-custom.html');

        $this->assertStringContainsString('settings-permission-groups#capability-hints', $html);
        $this->assertStringContainsString('/doc#permission-capability-hints-index', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
