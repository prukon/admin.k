<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#tbank-commissions-history-index совпадает с кнопкой «История»
 * на /admin/settings/tbank-commissions (не отчёт «Платежи T‑Bank»).
 */
final class TbankCommissionsHistoryDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_commissions_history_toolbar(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="tbank-commissions-history-index"', $html);
        $start = strpos($html, 'id="tbank-commissions-history-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-flexible-replace-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/settings/tbank-commissions', $chunk);
        $this->assertStringContainsString('settings.commission', $chunk);
        $this->assertStringContainsString('Добавить комиссию', $chunk);
        $this->assertStringContainsString('Фильтры', $chunk);
        $this->assertStringContainsString('#historyModal', $chunk);
        $this->assertStringContainsString('includes/logModal', $chunk);
        $this->assertStringContainsString('fa-clock-rotate-left', $chunk);
        $this->assertStringContainsString('showLogModal', $chunk);
        $this->assertStringContainsString('/admin/settings/tbank-commissions/logs-data', $chunk);
        $this->assertStringContainsString('logs.data.tbank-commission', $chunk);
        $this->assertStringContainsString('tbank_commission.created', $chunk);
        $this->assertStringContainsString('tbank_commission.updated', $chunk);
        $this->assertStringContainsString('tbank_commission.deleted', $chunk);
        $this->assertStringContainsString('tbank_commission.payout_settings_updated', $chunk);
        $this->assertStringContainsString('level = security', $chunk);
        $this->assertStringContainsString('payout_scheduled_interval_minutes', $chunk);
        $this->assertStringContainsString('SUPERADMIN_ALL_OR_FILTER', $chunk);
        $this->assertStringContainsString('current_partner', $chunk);
        $this->assertStringContainsString('302/401/403/419', $chunk);
        $this->assertStringContainsString('tinkoff_payment_status_logs', $chunk);
        $this->assertStringContainsString('tbank#commissions-history', $chunk);
        $this->assertStringContainsString('audit-my-logs#history-ui-pattern', $chunk);
        $this->assertStringContainsString('TbankCommissionsAuditLogsFeatureTest', $chunk);
        $this->assertStringContainsString('TbankCommissionsPageFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TbankCommissionsHistoryDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#tbank-commissions-history-index', $chunk);
        $this->assertStringContainsString('/doc#tbank-commissions-columns-index', $chunk);

        $this->assertStringNotContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringNotContainsString('reports_tbank_payments', $chunk);
        $this->assertStringNotContainsString('tbank_commissions_index', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_do_not_mix_report(): void
    {
        $tbank = $this->docFile('tbank.html');
        $audit = $this->docFile('audit-my-logs.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $reports = $this->docFile('reports-admin.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('id="commissions-history"', $tbank);
        $this->assertStringContainsString('/doc#tbank-commissions-history-index', $tbank);
        $this->assertStringContainsString('TbankCommissionsHistoryDocumentationContractTest', $tbank);
        $this->assertStringContainsString('logs.data.tbank-commission', $tbank);
        $this->assertStringContainsString('SUPERADMIN_ALL_OR_FILTER', $tbank);
        $this->assertStringContainsString('settings.commission', $tbank);

        $this->assertStringContainsString('/doc#tbank-commissions-history-index', $audit);
        $this->assertStringContainsString('tbank_commission', $audit);
        $this->assertStringContainsString('SUPERADMIN_ALL_OR_FILTER', $audit);

        $this->assertStringContainsString('/doc#tbank-commissions-history-index', $ui);
        $this->assertStringContainsString('tbank_commission', $ui);

        $this->assertStringContainsString('/doc#tbank-commissions-history-index', $reports);
        $this->assertStringContainsString('/admin/settings/tbank-commissions', $reports);
        $this->assertStringContainsString('reports_tbank_payments', $reports);

        $this->assertStringContainsString('/doc#tbank-commissions-history-index', $index);
        $this->assertStringContainsString('logs.data.tbank-commission', $index);
        $this->assertStringContainsString('id="tbank-commissions-columns-index"', $index);
    }

    public function test_live_code_matches_announced_history_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root.'/routes/web.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Setting/TbankCommissionsController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/setting/tbankCommissions.blade.php');
        $enum = (string) file_get_contents($root.'/app/Enums/AuditEvent.php');

        $this->assertStringContainsString("Route::middleware('can:settings.commission')", $routes);
        $this->assertStringContainsString("admin/settings/tbank-commissions/logs-data", $routes);
        $this->assertStringContainsString("->name('logs.data.tbank-commission')", $routes);

        $this->assertStringContainsString('use BuildsLogTable', $controller);
        $this->assertStringContainsString("buildLogDataTable('tbank_commission', PartnerScopeMode::SUPERADMIN_ALL_OR_FILTER)", $controller);
        $this->assertStringContainsString('AuditEvent::TbankCommissionCreated', $controller);
        $this->assertStringContainsString('AuditEvent::TbankCommissionUpdated', $controller);
        $this->assertStringContainsString('AuditEvent::TbankCommissionDeleted', $controller);
        $this->assertStringContainsString('AuditEvent::TbankCommissionPayoutSettingsUpdated', $controller);
        $this->assertStringContainsString('if ($changes !== [])', $controller);
        $this->assertStringContainsString('if ($oldMinutes !== $newMinutes)', $controller);
        $this->assertStringContainsString('withPartnerId', $controller);
        $this->assertStringContainsString('Правило удалено.', $controller);
        $this->assertStringContainsString('Интервал запуска джобы (мин):', $controller);

        $listStart = strpos($blade, "@vite(['resources/css/admin-list-toolbar.css'])");
        $this->assertNotFalse($listStart);
        $listChunk = substr($blade, $listStart);
        $this->assertStringContainsString('data-bs-target="#historyModal"', $listChunk);
        $this->assertStringContainsString('fa-clock-rotate-left', $listChunk);
        $this->assertStringContainsString('>История</span>', $listChunk);
        $this->assertStringContainsString("@include('includes.logModal')", $blade);
        $this->assertStringContainsString("showLogModal(@json(route('logs.data.tbank-commission')))", $listChunk);

        $addPos = strpos($listChunk, '>Добавить комиссию</span>');
        $historyPos = strpos($listChunk, '>История</span>');
        $filtersPos = strpos($listChunk, '>Фильтры</span>');
        $columnsPos = strpos($listChunk, '>Колонки</span>');
        $this->assertNotFalse($addPos);
        $this->assertNotFalse($historyPos);
        $this->assertNotFalse($filtersPos);
        $this->assertNotFalse($columnsPos);
        $this->assertGreaterThan($addPos, $historyPos);
        $this->assertGreaterThan($historyPos, $filtersPos);
        $this->assertGreaterThan($filtersPos, $columnsPos);

        $this->assertStringContainsString("case TbankCommissionCreated = 'tbank_commission.created'", $enum);
        $this->assertStringContainsString("case TbankCommissionUpdated = 'tbank_commission.updated'", $enum);
        $this->assertStringContainsString("case TbankCommissionDeleted = 'tbank_commission.deleted'", $enum);
        $this->assertStringContainsString("case TbankCommissionPayoutSettingsUpdated = 'tbank_commission.payout_settings_updated'", $enum);
        $this->assertStringContainsString("self::TbankCommissionDeleted => AuditLevel::Security", $enum);
        $this->assertStringContainsString("self::TbankCommissionPayoutSettingsUpdated => 'tbank_commission'", $enum);

        $docsController = (string) file_get_contents($root.'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('кнопка История / logModal', $docsController);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
