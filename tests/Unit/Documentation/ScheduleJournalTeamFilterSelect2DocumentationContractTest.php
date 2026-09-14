<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#journal-team-filter-select2-index совпадает с фильтром групп журнала.
 */
final class ScheduleJournalTeamFilterSelect2DocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_journal_team_filter_select2(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="journal-team-filter-select2-index"', $html);
        $start = strpos($html, 'id="journal-team-filter-select2-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="tbank-deal-close-http-error-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/schedule', $chunk);
        $this->assertStringContainsString('/admin/teams', $chunk);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2', $chunk);
        $this->assertStringContainsString('320px', $chunk);
        $this->assertStringContainsString('initScheduleJournalTeamFilter()', $chunk);
        $this->assertStringContainsString('DataTable', $chunk);
        $this->assertStringContainsString('team_ids[]', $chunk);
        $this->assertStringContainsString('select2:close', $chunk);
        $this->assertStringContainsString('journal_team_ids[]', $chunk);
        $this->assertStringNotContainsString(
            'Клиент шлёт <code>journal_team_filter</code> (текущий',
            $html,
            'Анонсы колонок журнала не должны описывать только скаляр без journal_team_ids[]'
        );
        $this->assertStringContainsString('Применить', $chunk);
        $this->assertStringContainsString('OR', $chunk);
        $this->assertStringContainsString('groups.own', $chunk);
        $this->assertStringContainsString('ScheduleJournalTeamFilter', $chunk);
        $this->assertStringContainsString('schedule-journal#journal-team-filter', $chunk);
        $this->assertStringContainsString('ScheduleJournalTeamFilterSelect2FeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleJournalTeamFilterSelect2AccessFeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleJournalTeamFilterSelect2AjaxContractFeatureTest', $chunk);
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($html, 'journal-team-filter-select2-index'),
            'Анонс должен быть на /doc и в оглавлении списка разделов'
        );
    }

    public function test_related_docs_and_sources_match_journal_team_filter(): void
    {
        $journal = $this->docFile('schedule-journal.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/schedule/journal.blade.php');
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/schedule.js');
        $hotfix = (string) file_get_contents(dirname(__DIR__, 3).'/public/js/schedule-journal.js');
        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Admin/GetScheduleJournalIndexRequest.php');

        $this->assertStringContainsString('id="journal-team-filter"', $journal);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2', $journal);
        $this->assertStringContainsString('320px', $journal);
        $this->assertStringContainsString('initScheduleJournalTeamFilter()', $journal);
        $this->assertStringContainsString('до</b> DataTable', $journal);
        $this->assertStringContainsString('team_ids[]', $journal);
        $this->assertStringContainsString('select2:close', $journal);
        $this->assertStringContainsString('ScheduleJournalTeamFilterSelect2AjaxContractFeatureTest', $journal);
        $this->assertStringContainsString('journal_team_ids[]', $journal);
        $this->assertStringContainsString('Применить', $journal);
        $this->assertStringNotContainsString(
            'journal_team_filter</code> = текущий',
            $journal,
            '§5.3 больше не должен описывать только скаляр journal_team_filter'
        );

        $own = $this->docFile('groups-own.html');
        $this->assertStringContainsString('team_ids[]', $own);
        $this->assertStringContainsString('errors.team_ids.0', $own);

        $this->assertStringContainsString('schedule-journal#journal-team-filter', $ui);
        $this->assertStringContainsString('#filter-team', $ui);

        $this->assertStringContainsString('js-generic-multiselect-select', $blade);
        $this->assertStringContainsString('data-placeholder="Все группы"', $blade);
        $this->assertStringContainsString('name="team_ids[]"', $blade);

        foreach ([$js, $hotfix] as $source) {
            $this->assertStringContainsString('select2:close', $source);
            $this->assertStringContainsString("append('team_ids[]'", $source);
            $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.init', $source);
        }

        $this->assertStringContainsString("'team_ids'", $request);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
