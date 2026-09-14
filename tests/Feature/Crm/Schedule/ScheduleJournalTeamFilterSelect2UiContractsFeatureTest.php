<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;

/**
 * UX-контракт фильтра групп журнала: первый HTML, дефолты, JS apply-on-close без «Применить».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalTeamFilterSelect2UiContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_first_open_renders_year_month_team_search_without_selected_groups(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'UiГруппаЖурнал',
            'is_enabled' => 1,
        ]);

        $html = $this->get(route('schedule.index'))->assertOk()->getContent();

        $yearPos = strpos($html, 'id="filter-year"');
        $monthPos = strpos($html, 'id="filter-month"');
        $teamPos = strpos($html, 'id="filter-team"');
        $searchPos = strpos($html, 'id="table-search"');
        $this->assertNotFalse($yearPos);
        $this->assertNotFalse($monthPos);
        $this->assertNotFalse($teamPos);
        $this->assertNotFalse($searchPos);
        $this->assertLessThan($monthPos, $yearPos);
        $this->assertLessThan($teamPos, $monthPos);
        $this->assertLessThan($searchPos, $teamPos);

        $this->assertStringContainsString('data-placeholder="Все группы"', $html);
        $this->assertTrue((bool) preg_match('/<select[^>]*id="filter-team"[^>]*>(.*?)<\/select>/s', $html, $select));
        $this->assertStringContainsString('js-generic-multiselect-select', $select[0]);
        $this->assertStringContainsString('multiple', $select[0]);
        $this->assertStringNotContainsString('value="all"', $select[1]);
        $this->assertStringNotContainsString('selected', $select[1]);
        $this->assertStringContainsString('value="none"', $select[1]);
        $this->assertStringContainsString('UiГруппаЖурнал', $select[1]);
        $this->assertStringNotContainsString('Применить', $html);
    }

    public function test_reopening_with_team_ids_keeps_selected_and_empty_does_not_force_none(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'UiПовторЖурнал',
            'is_enabled' => 1,
        ]);

        $selected = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$team->id],
        ]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<option value="'.$team->id.'"\s+selected/s',
            $selected
        );
        $this->assertDoesNotMatchRegularExpression('/<option value="none"\s+selected/s', $selected);

        $empty = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
        ]))->assertOk()->getContent();
        $this->assertTrue((bool) preg_match('/<select[^>]*id="filter-team"[^>]*>(.*?)<\/select>/s', $empty, $select));
        $this->assertStringNotContainsString('selected', $select[1]);
        $this->assertDoesNotMatchRegularExpression('/<option value="none"\s+selected/s', $empty);
    }

    public function test_team_filter_has_static_width_and_hides_native_select_until_select2(): void
    {
        $index = (string) file_get_contents(resource_path('views/admin/schedule/index.blade.php'));
        $css = (string) file_get_contents(resource_path('css/schedule.css'));
        $hotfixCss = (string) file_get_contents(public_path('css/schedule-journal-cells.css'));

        foreach ([$index, $css, $hotfixCss] as $chunk) {
            $wrapPos = strpos($chunk, '.schedule-fullscreen-wrapper .wrap-filter-team {');
            $this->assertNotFalse($wrapPos);
            $wrapChunk = substr($chunk, $wrapPos, 2800);
            $this->assertStringContainsString('width: 320px', $wrapChunk);
            $this->assertStringContainsString('max-width: 320px', $wrapChunk);
            $this->assertStringContainsString(':not(:has(.select2-container))', $wrapChunk);
            $this->assertStringContainsString('select.schedule-filter-team:not(.select2-hidden-accessible)', $wrapChunk);
            $this->assertStringContainsString('opacity: 0', $wrapChunk);
            $this->assertStringContainsString('height: auto !important', $wrapChunk);
            $this->assertStringNotContainsString('width: max-content', $wrapChunk);
        }

        foreach ([
            resource_path('js/schedule.js'),
            public_path('js/schedule-journal.js'),
        ] as $jsPath) {
            $js = (string) file_get_contents($jsPath);
            $initCallPos = strpos($js, "    initScheduleJournalTeamFilter();\n");
            $dtPos = strpos($js, "$('#schedule-table').DataTable({");
            $this->assertNotFalse($initCallPos, $jsPath);
            $this->assertNotFalse($dtPos, $jsPath);
            $this->assertLessThan(
                $dtPos,
                $initCallPos,
                "{$jsPath}: Select2 фильтра должен встать до DataTable, иначе вспышка нативного select"
            );
            $this->assertSame(
                1,
                substr_count($js, "    initScheduleJournalTeamFilter();\n"),
                "{$jsPath}: повторный init после DataTable снова сбросит стили"
            );
        }
    }

    public function test_js_applies_team_filter_on_dropdown_close_not_on_each_checkbox(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/schedule/journal.blade.php'));
        $this->assertStringNotContainsString('<script', $blade);
        $this->assertStringNotContainsString('Применить', $blade);
        $this->assertStringContainsString('js-generic-multiselect-select', $blade);
        $this->assertStringContainsString("@foreach(\$selectedTeamTokens as \$teamToken)", $blade);
        $this->assertStringNotContainsString("value=\"none\"", $this->searchHiddenTeamIdsSnippet($blade));

        foreach ([
            resource_path('js/schedule.js'),
            public_path('js/schedule-journal.js'),
        ] as $jsPath) {
            $this->assertFileExists($jsPath);
            $js = (string) file_get_contents($jsPath);

            $this->assertStringContainsString('function initScheduleJournalTeamFilter', $js);
            $initPos = strpos($js, 'KidsCrmGenericMultiselectSelect2.init($team');
            $snapshotPos = strpos($js, 'var teamFilterSnapshot = scheduleJournalTeamFilterSnapshot()');
            $this->assertNotFalse($initPos, $jsPath);
            $this->assertNotFalse($snapshotPos, $jsPath);
            $this->assertLessThan($snapshotPos, $initPos, "{$jsPath}: снимок после init, иначе Select2 change уедет в reload");

            $this->assertStringContainsString('$team.on(\'select2:close\', maybeNavigateTeamFilter)', $js);
            $this->assertStringContainsString('$team.on(\'select2:clear\', maybeNavigateTeamFilter)', $js);
            $this->assertStringContainsString('if (next === teamFilterSnapshot)', $js);
            $this->assertStringNotContainsString("$('.schedule-filter-team').on('change'", $js);
            $this->assertStringContainsString(
                "$('.schedule-filter-year, .schedule-filter-month').on('change'",
                $js
            );
            $this->assertStringContainsString("append('team_ids[]', token)", $js);
            $this->assertStringContainsString("searchParams.delete('team')", $js);
            $this->assertStringContainsString("data.push({name: 'journal_team_ids[]', value: token})", $js);
            $this->assertStringContainsString('data.journal_team_ids = tokens', $js);
            $this->assertStringContainsString("return 'all'", $js);

            $output = [];
            $exitCode = 0;
            exec('node --check '.escapeshellarg($jsPath).' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, "JS syntax error in {$jsPath}:\n".implode("\n", $output));
        }
    }

    private function searchHiddenTeamIdsSnippet(string $blade): string
    {
        $this->assertTrue(
            (bool) preg_match(
                '/@foreach\(\$selectedTeamTokens as \$teamToken\)[\s\S]*?@endforeach/',
                $blade,
                $match
            )
        );

        return $match[0];
    }
}
