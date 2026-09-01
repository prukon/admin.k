<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#system-monitors-index и dashboard-cabinet §8:
 * settings.systemMonitors.view, Gate::before, без JSON-оверлея на /cabinet.
 */
final class SystemMonitorsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_system_monitors_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="system-monitors-index"', $html);
        $start = strpos($html, 'id="system-monitors-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-contacts-team-filter-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('settings.systemMonitors.view', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('users.system_monitors', $chunk);
        $this->assertStringContainsString('/cabinet/system-monitors', $chunk);
        $this->assertStringContainsString('cabinet.system-monitors.update', $chunk);
        $this->assertStringContainsString('can:settings.systemMonitors.view', $chunk);
        $this->assertStringContainsString('Системные мониторы доступны только с правом просмотра.', $chunk);
        $this->assertStringContainsString('#system-monitors-error', $chunk);
        $this->assertStringContainsString('errors.system_monitors', $chunk);
        $this->assertStringContainsString('get-user-details', $chunk);
        $this->assertStringContainsString('#js-reverb-status', $chunk);
        $this->assertStringContainsString('#js-online-users', $chunk);
        $this->assertStringContainsString('#js-partner-switch', $chunk);
        $this->assertStringContainsString('partner.switch', $chunk);
        $this->assertStringContainsString('/cabinet/system-monitors/online-users', $chunk);
        $this->assertStringContainsString('cabinet.system-monitors.online-users', $chunk);
        $this->assertStringContainsString('OnlineUsersMonitorRequest', $chunk);
        $this->assertStringContainsString('UserPresence::ONLINE_WITHIN_SECONDS', $chunk);
        $this->assertStringContainsString('Без партнёра', $chunk);
        $this->assertStringContainsString('пустой список', $chunk);
        $this->assertStringContainsString('Без названия', $chunk);
        $this->assertStringContainsString('Auth::id()', $chunk);
        $this->assertStringContainsString('без зрителя', $chunk);
        $this->assertStringContainsString('/doc#online-users-overlay-index', $chunk);
        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('/cabinet/system-monitors/ops', $chunk);
        $this->assertStringContainsString('cabinet.system-monitors.ops', $chunk);
        $this->assertStringContainsString('OpsMonitorRequest', $chunk);
        $this->assertStringContainsString('OpsMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('/doc#ops-errors-detail-index', $chunk);
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $chunk);
        $this->assertStringContainsString('last_message', $chunk);
        $this->assertStringContainsString('вход — 72 ч', $chunk);
        $this->assertStringContainsString('KidsCrmTooltip', $chunk);
        $this->assertStringContainsString('sidebar-mini layout-fixed', $chunk);
        $this->assertStringContainsString('JSON-оверлея диагностики нет', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $chunk);
        $this->assertStringContainsString('SystemMonitorsToggleFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LogOutgoingEmailMailableClassFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetDiagnosticsFeatureTest', $chunk);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitors::canView()', $chunk);
        $this->assertStringContainsString('settings.reverbOverlay.manage', $chunk);
        $this->assertStringNotContainsString('settings.cabinetDiagnostics.manage', $chunk);
        $this->assertStringNotContainsString('hasPermission()', $chunk);
        $this->assertStringNotContainsString('Оверлей на /cabinet', $chunk);
        $this->assertStringNotContainsString('PartnerContext::isSuperAdmin()', $chunk);
        $this->assertStringNotContainsString('id="rowCabinetDiagnostics"', $chunk);
    }

    public function test_doc_index_announces_online_users_overlay_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="online-users-overlay-index"', $html);
        $start = strpos($html, 'id="online-users-overlay-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="ops-monitors-overlay-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-online-users', $chunk);
        $this->assertStringContainsString('#system-monitors-stack', $chunk);
        $this->assertStringContainsString('z-index: 20000', $chunk);
        $this->assertStringContainsString('над</b> бейджем Reverb', $chunk);
        $this->assertStringContainsString('UserPresence::ONLINE_WITHIN_SECONDS', $chunk);
        $this->assertStringContainsString('current_partner', $chunk);
        $this->assertStringContainsString('Auth::id()', $chunk);
        $this->assertStringContainsString('Зрителя', $chunk);
        $this->assertStringContainsString('is_enabled=0', $chunk);
        $this->assertStringNotContainsString('себя тоже', $chunk);
        $this->assertStringContainsString('Без партнёра', $chunk);
        $this->assertStringContainsString('Без названия', $chunk);
        $this->assertStringContainsString('text-align: center', $chunk);
        $this->assertStringContainsString('.online-users__partner-title', $chunk);
        $this->assertStringContainsString('text-align: left', $chunk);
        $this->assertStringContainsString('.online-users__user', $chunk);
        $this->assertStringContainsString('#id', $chunk);
        $this->assertStringContainsString('OnlineUsersMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('/cabinet/system-monitors/online-users', $chunk);
        $this->assertStringContainsString('cabinet.system-monitors.online-users', $chunk);
        $this->assertStringContainsString('OnlineUsersMonitorRequest', $chunk);
        $this->assertStringContainsString('Персональный флаг GET', $chunk);
        $this->assertStringContainsString('пустой <code>data-role="list"</code>', $chunk);
        $this->assertStringContainsString('раз в 3 с', $chunk);
        $this->assertStringContainsString('escapeHtml', $chunk);
        $this->assertStringContainsString('chat-presence-index', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $chunk);
        $this->assertStringContainsString('chat#online-users-overlay', $chunk);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersAjaxContractFeatureTest', $chunk);
        $this->assertStringNotContainsString('только текущий партнёр', $chunk);
        $this->assertStringNotContainsString('messages.view</code> открывает список', $chunk);
        $this->assertStringNotContainsString('PartnerContext::isSuperAdmin()', $chunk);
        $this->assertStringNotContainsString('JSON-оверлея диагностики на <code>/cabinet</code> есть', $chunk);
    }

    public function test_doc_index_announces_ops_overlay_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-monitors-overlay-index"', $html);
        $start = strpos($html, 'id="ops-monitors-overlay-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="trainer-salary-sales-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('#system-monitors-stack', $chunk);
        $this->assertStringContainsString('z-index: 20000', $chunk);
        $this->assertStringContainsString('#js-online-users', $chunk);
        $this->assertStringContainsString('KidsCrmTooltip', $chunk);
        $this->assertStringContainsString('#111827', $chunk);
        $this->assertStringContainsString('24 часа', $chunk);
        $this->assertStringContainsString('72 часа', $chunk);
        $this->assertStringContainsString('auth.recent_logins', $chunk);
        $this->assertStringContainsString('auth.login_failed', $chunk);
        $this->assertStringContainsString('QueueStatusSnapshot::compact()', $chunk);
        $this->assertStringContainsString('Handler::report', $chunk);
        $this->assertStringContainsString('my_logs', $chunk);
        $this->assertStringContainsString('laravel.log', $chunk);
        $this->assertStringContainsString('ViewException', $chunk);
        $this->assertStringContainsString('getPrevious()', $chunk);
        $this->assertStringContainsString('errors.recent', $chunk);
        $this->assertStringContainsString('errors-last', $chunk);
        $this->assertStringContainsString('last_message', $chunk);
        $this->assertStringContainsString('getPathInfo()', $chunk);
        $this->assertStringContainsString('/doc#ops-errors-detail-index', $chunk);
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $chunk);
        $this->assertStringContainsString('last_message', $chunk);
        $this->assertStringContainsString('innerHTML', $chunk);
        $this->assertStringContainsString('leftover', $chunk);
        $this->assertStringContainsString('current_partner', $chunk);
        $this->assertStringContainsString('OpsMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('/cabinet/system-monitors/ops', $chunk);
        $this->assertStringContainsString('cabinet.system-monitors.ops', $chunk);
        $this->assertStringContainsString('OpsMonitorRequest', $chunk);
        $this->assertStringContainsString('раз в 5 с', $chunk);
        $this->assertStringContainsString('textContent', $chunk);
        $this->assertStringContainsString('ClientWelcomeCredentialsMail', $chunk);
        $this->assertStringContainsString('STATUS_FAILED', $chunk);
        $this->assertStringContainsString('mailable_class', $chunk);
        $this->assertStringContainsString('buildViewDataUsing', $chunk);
        $this->assertStringContainsString('Доступ в личный кабинет', $chunk);
        $this->assertStringContainsString('LogOutgoingEmail', $chunk);
        $this->assertStringContainsString('EventServiceProvider', $chunk);
        $this->assertStringContainsString('TokenMismatchException', $chunk);
        $this->assertStringContainsString('data-role', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $chunk);
        $this->assertStringContainsString('chat#ops-monitors-overlay', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LogOutgoingEmailMailableClassFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsAuthAttemptsFeatureTest', $chunk);
        $this->assertStringNotContainsString('только текущий партнёр', $chunk);
        $this->assertStringNotContainsString('PartnerContext::isSuperAdmin()', $chunk);
        $this->assertStringNotContainsString('JSON-оверлея диагностики на <code>/cabinet</code> есть', $chunk);
    }

    public function test_dashboard_cabinet_page_matches_system_monitors_contract(): void
    {
        $html = $this->docFile('dashboard-cabinet.html');

        $this->assertStringContainsString('id="system-monitors"', $html);
        $this->assertStringContainsString('/doc#system-monitors-index', $html);
        $start = strpos($html, 'id="system-monitors"');
        $this->assertNotFalse($start);
        $end = strpos($html, '9) Feature-тесты');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('settings.systemMonitors.view', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('SystemMonitors', $chunk);
        $this->assertStringContainsString('UpdateSystemMonitorsRequest', $chunk);
        $this->assertStringContainsString('users.system_monitors', $chunk);
        $this->assertStringContainsString('#system-monitors-error', $chunk);
        $this->assertStringContainsString('errors.system_monitors', $chunk);
        $this->assertStringContainsString('get-user-details', $chunk);
        $this->assertStringContainsString('Системные мониторы доступны только с правом просмотра.', $chunk);
        $this->assertStringContainsString('#js-reverb-status', $chunk);
        $this->assertStringContainsString('#js-online-users', $chunk);
        $this->assertStringContainsString('#js-partner-switch', $chunk);
        $this->assertStringContainsString('partner.switch', $chunk);
        $this->assertStringContainsString('OnlineUsersMonitorRequest', $chunk);
        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('OpsMonitorRequest', $chunk);
        $this->assertStringContainsString('OpsMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('/cabinet/system-monitors/ops', $chunk);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('/doc#ops-errors-detail-index', $chunk);
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $chunk);
        $this->assertStringContainsString('KidsCrmTooltip', $chunk);
        $this->assertStringContainsString('/doc#kids-tooltip-contrast-index', $chunk);
        $this->assertStringContainsString('STATUS_FAILED', $chunk);
        $this->assertStringContainsString('mailable_class', $chunk);
        $this->assertStringContainsString('Доступ в личный кабинет', $chunk);
        $this->assertStringContainsString('buildViewDataUsing', $chunk);
        $this->assertStringContainsString('ViewException', $chunk);
        $this->assertStringContainsString('getPrevious()', $chunk);
        $this->assertStringContainsString('errors.recent', $chunk);
        $this->assertStringContainsString('last_message', $chunk);
        $this->assertStringContainsString('leftover', $chunk);
        $this->assertStringContainsString('SystemMonitors::shouldShow()', $chunk);
        $this->assertStringContainsString('JSON-оверлея на <code>/cabinet</code> нет', $chunk);
        $this->assertStringContainsString('пустой список', $chunk);
        $this->assertStringContainsString('Никого нет онлайн', $chunk);
        $this->assertStringContainsString('Без названия', $chunk);
        $this->assertStringContainsString('text-align: center', $chunk);
        $this->assertStringContainsString('.online-users__partner-title', $chunk);
        $this->assertStringContainsString('text-align: left', $chunk);
        $this->assertStringContainsString('.online-users__user', $chunk);
        $this->assertStringContainsString('Auth::id()', $chunk);
        $this->assertStringContainsString('зрителя', $chunk);
        $this->assertStringContainsString('OnlineUsersMonitor', $chunk);
        $this->assertStringContainsString('/doc#online-users-overlay-index', $chunk);
        $this->assertStringContainsString('URI переключателя', $chunk);
        $this->assertStringContainsString('SystemMonitorsToggleFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsAccessFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsUxFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersAccessFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOnlineUsersUxFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsAccessFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingUxFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFullAccessFeatureTest', $html);
        $this->assertStringContainsString('LogOutgoingEmailMailableClassFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsUxFeatureTest', $html);
        $this->assertStringContainsString('SystemMonitorsOpsAuthAttemptsFeatureTest', $html);
        $this->assertStringContainsString('KidsCrmTooltipContrastFeatureTest', $html);
        $this->assertStringContainsString('/cabinet/system-monitors', $chunk);
        $this->assertStringContainsString('settings.reverbOverlay.manage', $chunk);
        $this->assertStringNotContainsString('PartnerContext::isSuperAdmin()', $chunk);
        $this->assertStringNotContainsString('ToggleCabinetDiagnosticsRequest', $chunk);
    }

    public function test_settings_permission_groups_page_lists_system_monitors_not_reverb_overlay(): void
    {
        $html = $this->docFile('settings-permission-groups.html');

        $this->assertStringContainsString('settings.systemMonitors.view', $html);
        $this->assertStringContainsString('Gate::before', $html);
        $this->assertStringContainsString('2026_08_18_234000_drop_settings_cabinet_diagnostics_manage_permission.php', $html);
        $this->assertStringContainsString('2026_08_29_143100_replace_reverb_overlay_permission_with_system_monitors.php', $html);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $html);
        $this->assertStringContainsString('/doc#online-users-overlay-index', $html);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $html);
        $this->assertStringContainsString('/doc#ops-errors-detail-index', $html);
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $html);
        $this->assertStringContainsString('JSON-оверлея на <code>/cabinet</code> нет', $html);
        $this->assertStringContainsString('settings.reverbOverlay.manage', $html);
        $this->assertStringContainsString('SystemMonitorsPermissionCatalogFeatureTest', $html);
    }

    public function test_documentation_controller_mentions_system_monitors_in_cabinet_title(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'dashboard-cabinet'", $controller);
        $this->assertStringContainsString('settings.systemMonitors.view', $controller);
        $this->assertStringContainsString('системные мониторы', $controller);
        $this->assertStringContainsString('оверлей Reverb', $controller);
        $this->assertStringContainsString('оверлей онлайн по партнёрам', $controller);
        $this->assertStringContainsString('оверлей Пульт', $controller);
        $this->assertStringContainsString('last_message', $controller);
        $this->assertStringContainsString('ViewException', $controller);
        $this->assertStringContainsString('errors.recent', $controller);
        $this->assertStringContainsString('mailable_class', $controller);
        $this->assertStringContainsString('Доступ в личный кабинет', $controller);
        $this->assertStringContainsString('не точка чата', $controller);
        $this->assertStringNotContainsString('settings.reverbOverlay.manage', $controller);
        $this->assertStringNotContainsString('settings.cabinetDiagnostics.manage', $controller);
        $this->assertStringNotContainsString('диагностика консоли', $controller);
    }

    public function test_chat_ops_overlay_page_mentions_last_message_hover_and_recent(): void
    {
        $html = $this->docFile('chat.html');
        $this->assertStringContainsString('id="ops-monitors-overlay"', $html);
        $start = strpos($html, 'id="ops-monitors-overlay"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="tests"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('без ссылок', $chunk);
        $this->assertStringContainsString('last_message', $chunk);
        $this->assertStringContainsString('innerHTML', $chunk);
        $this->assertStringContainsString('ViewException', $chunk);
        $this->assertStringContainsString('errors.recent', $chunk);
        $this->assertStringContainsString('leftover', $chunk);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('/doc#ops-errors-detail-index', $chunk);
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $chunk);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
