<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#ops-day-kpi-index совпадает с живыми строками «Сегодня» / «Вчера»:
 * календарный день Europe/Moscow, T‑Bank TRIM, комиссия из правил, leftover.
 */
final class OpsDayKpiDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_today_and_yesterday_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-day-kpi-index"', $html);
        $start = strpos($html, 'id="ops-day-kpi-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="users-create-edit-toast-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('Сегодня', $chunk);
        $this->assertStringContainsString('Вчера', $chunk);
        $this->assertStringContainsString('Очередь', $chunk);
        $this->assertStringContainsString('ops-monitors__slash', $chunk);
        $this->assertStringContainsString('Europe/Moscow', $chunk);
        $this->assertStringContainsString('startOfDay()', $chunk);
        $this->assertStringContainsString('operation_date', $chunk);
        $this->assertStringContainsString('summ_cents', $chunk);
        $this->assertStringContainsString('TRIM', $chunk);
        $this->assertStringContainsString('deal_id', $chunk);
        $this->assertStringContainsString('payment_id', $chunk);
        $this->assertStringContainsString('payment_status', $chunk);
        $this->assertStringContainsString('tinkoff_commission_rules', $chunk);
        $this->assertStringContainsString('platform_percent', $chunk);
        $this->assertStringContainsString('platform_min_fixed', $chunk);
        $this->assertStringContainsString('tinkoff_payouts.platform_fee', $chunk);
        $this->assertStringContainsString('tinkoff_payments', $chunk);
        $this->assertStringContainsString('current_partner', $chunk);
        $this->assertStringContainsString('pending', $chunk);
        $this->assertStringContainsString('succeeded', $chunk);
        $this->assertStringContainsString('failed', $chunk);
        $this->assertStringContainsString('<code>day</code>', $chunk);
        $this->assertStringContainsString('<code>yesterday</code>', $chunk);
        $this->assertStringContainsString('turnover', $chunk);
        $this->assertStringContainsString('commission', $chunk);
        $this->assertStringContainsString('payments_count', $chunk);
        $this->assertStringContainsString('/cabinet/system-monitors/ops', $chunk);
        $this->assertStringContainsString('OpsMonitor::snapshot()', $chunk);
        $this->assertStringContainsString('data-role="day-*"', $chunk);
        $this->assertStringContainsString('leftover', $chunk);
        $this->assertStringContainsString('textContent', $chunk);
        $this->assertStringContainsString('toLocaleString', $chunk);
        $this->assertStringContainsString('SystemMonitors::canView()', $chunk);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $chunk);
        $this->assertStringContainsString('chat#ops-monitors-overlay', $chunk);
        $this->assertStringContainsString('reports-payments', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsDayFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsDayUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsDayAccessFeatureTest', $chunk);
        $this->assertStringContainsString('OpsDayKpiDocumentationContractTest', $chunk);
        $this->assertStringNotContainsString('скользящие 24 ч этих строк', $chunk);
        $this->assertStringNotContainsString('только текущий партнёр', $chunk);
        $this->assertStringNotContainsString('platform_fee из выплаты', $chunk);
        $this->assertStringNotContainsString('toLocaleString рисует', $chunk);
        $this->assertStringNotContainsString('первый HTML сразу 150000', $chunk);
    }

    public function test_detail_pages_link_today_yesterday_announcement(): void
    {
        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('/doc#ops-day-kpi-index', $cabinet);
        $this->assertStringContainsString('cabinet.system-monitors.ops', $cabinet);
        $this->assertStringContainsString('<code>day</code>', $cabinet);
        $this->assertStringContainsString('<code>yesterday</code>', $cabinet);
        $this->assertStringContainsString('leftover', $cabinet);
        $this->assertStringContainsString('SystemMonitorsOpsDayFeatureTest', $cabinet);
        $this->assertStringContainsString('OpsDayKpiDocumentationContractTest', $cabinet);

        $chat = $this->docFile('chat.html');
        $start = strpos($chat, 'id="ops-monitors-overlay"');
        $this->assertNotFalse($start);
        $end = strpos($chat, 'id="tests"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($chat, $start, $end - $start);
        $this->assertStringContainsString('/doc#ops-day-kpi-index', $chunk);
        $this->assertStringContainsString('<code>day</code>', $chunk);
        $this->assertStringContainsString('<code>yesterday</code>', $chunk);
        $this->assertStringContainsString('leftover', $chunk);

        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('/doc#ops-day-kpi-index', $groups);

        $index = $this->docFile('index.html');
        $overlayStart = strpos($index, 'id="ops-monitors-overlay-index"');
        $this->assertNotFalse($overlayStart);
        $overlayEnd = strpos($index, 'id="trainer-salary-sales-index"');
        $this->assertNotFalse($overlayEnd);
        $overlay = substr($index, $overlayStart, $overlayEnd - $overlayStart);
        $this->assertStringContainsString('/doc#ops-day-kpi-index', $overlay);
        $this->assertStringContainsString('Europe/Moscow', $overlay);
    }

    public function test_documentation_controller_mentions_calendar_day_and_leftover(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('Сегодня/Вчера', $controller);
        $this->assertStringContainsString('календарный день Europe/Moscow', $controller);
        $this->assertStringContainsString('leftover', $controller);
        $this->assertStringNotContainsString('скользящие 24 ч Сегодня', $controller);
        $this->assertStringNotContainsString('только текущий партнёр в пульте Сегодня', $controller);
    }

    public function test_live_ops_monitor_matches_documented_calendar_day_and_tbank_trim(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Support/OpsMonitor.php');
        $this->assertStringContainsString('calendarDaySnapshot', $src);
        $this->assertStringContainsString("now()->startOfDay()", $src);
        $this->assertStringContainsString("\$todayStart->copy()->subDay()", $src);
        $this->assertStringContainsString("where('summ_cents', '>', 0)", $src);
        $this->assertStringContainsString('constrainTbankPayments', $src);
        $this->assertStringContainsString('TRIM(deal_id)', $src);
        $this->assertStringContainsString('TinkoffCommissionRule::query()', $src);
        $this->assertStringNotContainsString('tinkoff_payouts.platform_fee', $src);
        $this->assertStringContainsString("'pending', 'succeeded'", $src);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/system_monitors/ops.blade.php');
        $this->assertStringContainsString('ops-monitors__slash', $blade);
        $this->assertStringContainsString('data-role="day-turnover">…</span>', $blade);
        $this->assertStringContainsString('data-role="yesterday-turnover">…</span>', $blade);
        $this->assertStringContainsString("day.turnover == null ? '—' : day.turnover", $blade);
        $this->assertStringContainsString("setText('day-turnover', '—', 'is-muted')", $blade);
        $this->assertStringNotContainsString('toLocaleString', $blade);
        $this->assertStringNotContainsString('₽', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
