<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#cabinet-diagnostics-index и dashboard-cabinet §8:
 * settings.reverbOverlay.manage, Gate::before, без JSON-оверлея на /cabinet.
 */
final class CabinetDiagnosticsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_cabinet_diagnostics_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="cabinet-diagnostics-index"', $html);
        $start = strpos($html, 'id="cabinet-diagnostics-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-contacts-team-filter-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('settings.reverbOverlay.manage', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('cabinet_diagnostics', $chunk);
        $this->assertStringContainsString('partner_id = NULL', $chunk);
        $this->assertStringContainsString('/admin/settings/cabinet-diagnostics', $chunk);
        $this->assertStringContainsString('settings.cabinetDiagnostics', $chunk);
        $this->assertStringContainsString('can:settings.reverbOverlay.manage', $chunk);
        $this->assertStringContainsString('Оверлей статуса Reverb доступен только суперадмину.', $chunk);
        $this->assertStringContainsString('#cabinetDiagnosticsError', $chunk);
        $this->assertStringContainsString('errors.cabinetDiagnostics', $chunk);
        $this->assertStringContainsString('get-user-details', $chunk);
        $this->assertStringContainsString('#js-reverb-status', $chunk);
        $this->assertStringContainsString('sidebar-mini layout-fixed', $chunk);
        $this->assertStringContainsString('JSON-оверлея диагностики нет', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#cabinet-diagnostics', $chunk);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsFeatureTest', $chunk);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetDiagnosticsFeatureTest', $chunk);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $chunk);
        $this->assertStringContainsString('включён', $chunk);
        $this->assertStringContainsString('выключен', $chunk);
        $this->assertStringContainsString('responseJSON.message', $chunk);
        $this->assertStringNotContainsString('settings.cabinetDiagnostics.manage', $chunk);
        $this->assertStringNotContainsString('hasPermission()', $chunk);
        $this->assertStringNotContainsString('Оверлей на /cabinet', $chunk);
        $this->assertStringNotContainsString('PartnerContext::isSuperAdmin()', $chunk);
    }

    public function test_dashboard_cabinet_page_matches_diagnostics_contract(): void
    {
        $html = $this->docFile('dashboard-cabinet.html');

        $this->assertStringContainsString('id="cabinet-diagnostics"', $html);
        $this->assertStringContainsString('/doc#cabinet-diagnostics-index', $html);
        $start = strpos($html, 'id="cabinet-diagnostics"');
        $this->assertNotFalse($start);
        $end = strpos($html, '9) Feature-тесты');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('settings.reverbOverlay.manage', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('CabinetDiagnostics', $chunk);
        $this->assertStringContainsString('ToggleCabinetDiagnosticsRequest', $chunk);
        $this->assertStringContainsString('cabinet_diagnostics', $chunk);
        $this->assertStringContainsString('partner_id = NULL', $chunk);
        $this->assertStringContainsString('#cabinet-diagnostics', $chunk);
        $this->assertStringContainsString('#cabinetDiagnosticsError', $chunk);
        $this->assertStringContainsString('errors.cabinetDiagnostics', $chunk);
        $this->assertStringContainsString('get-user-details', $chunk);
        $this->assertStringContainsString('Оверлей статуса Reverb доступен только суперадмину.', $chunk);
        $this->assertStringContainsString('#js-reverb-status', $chunk);
        $this->assertStringContainsString('CabinetDiagnostics::shouldShow()', $chunk);
        $this->assertStringContainsString('JSON-оверлея на <code>/cabinet</code> нет', $chunk);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsUiContractsFeatureTest', $html);
        $this->assertStringContainsString('/admin/settings', $chunk);
        $this->assertStringContainsString('включён', $chunk);
        $this->assertStringContainsString('выключен', $chunk);
        $this->assertStringNotContainsString('settings.cabinetDiagnostics.manage', $chunk);
        $this->assertStringNotContainsString('PartnerContext::isSuperAdmin()', $chunk);
    }

    public function test_settings_permission_groups_page_does_not_keep_diagnostics_in_catalog(): void
    {
        $html = $this->docFile('settings-permission-groups.html');

        $this->assertStringContainsString('settings.reverbOverlay.manage', $html);
        $this->assertStringContainsString('Gate::before', $html);
        $this->assertStringContainsString('2026_08_18_234000_drop_settings_cabinet_diagnostics_manage_permission.php', $html);
        $this->assertStringContainsString('2026_08_18_235000_add_settings_reverb_overlay_manage_permission.php', $html);
        $this->assertStringContainsString('dashboard-cabinet#cabinet-diagnostics', $html);
        $this->assertStringContainsString('JSON-оверлея на <code>/cabinet</code> нет', $html);
        $this->assertStringContainsString('settings.cabinetDiagnostics.manage', $html);
    }

    public function test_documentation_controller_mentions_diagnostics_in_cabinet_title(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'dashboard-cabinet'", $controller);
        $this->assertStringContainsString('settings.reverbOverlay.manage', $controller);
        $this->assertStringContainsString('/admin/settings', $controller);
        $this->assertStringContainsString('оверлей Reverb', $controller);
        $this->assertStringNotContainsString('settings.cabinetDiagnostics.manage', $controller);
        $this->assertStringNotContainsString('диагностика консоли', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
