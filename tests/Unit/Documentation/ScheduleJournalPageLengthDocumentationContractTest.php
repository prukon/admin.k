<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#journal-pagination-index и §2.2 совпадают с «Показывать по» журнала.
 */
final class ScheduleJournalPageLengthDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_journal_page_length(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="journal-pagination-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="user-percent-discount-index"', $start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('table_key = schedule_journal', $chunk);
        $this->assertStringContainsString('user_table_settings.page_length', $chunk);
        $this->assertStringContainsString('JOURNAL_GROUP_STUDENTS_PER_PAGE = 50', $chunk);
        $this->assertStringContainsString('Показывать по', $chunk);
        $this->assertStringContainsString('/schedule/journal-page-length', $chunk);
        $this->assertStringContainsString('Сохранённые 20, 50 и 100 дефолт не подменяет.', $chunk);
        $this->assertStringContainsString('schedule-journal#journal-pagination', $chunk);
        $this->assertStringContainsString('ScheduleJournalPageLengthFeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleJournalPageLengthDocumentationContractTest', $chunk);
    }

    public function test_schedule_journal_doc_and_sources_match_page_length(): void
    {
        $journal = $this->docFile('schedule-journal.html');
        $start = strpos($journal, 'id="journal-pagination"');
        $this->assertNotFalse($start);
        $end = strpos($journal, 'id="journal-team-filter"', $start);
        $this->assertNotFalse($end);
        $chunk = substr($journal, $start, $end - $start);

        $this->assertStringContainsString('table_key = schedule_journal', $chunk);
        $this->assertStringContainsString('Нет строки или значение вне набора → <b>50</b>', $chunk);
        $this->assertStringContainsString('Сохранённые <b>20</b>, <b>50</b> и <b>100</b> дефолт не подменяет.', $chunk);
        $this->assertStringContainsString('/schedule/journal-page-length', $chunk);
        $this->assertStringContainsString('schedule.journal-page-length', $journal);
        $this->assertStringContainsString('errors.page_length', $chunk);
        $this->assertStringContainsString('Можно показать 20, 50 или 100 учеников.', $chunk);
        $this->assertStringContainsString('Укажите, сколько учеников показывать.', $chunk);
        $this->assertStringContainsString('Количество учеников должно быть целым числом.', $chunk);
        $this->assertStringContainsString('{success:true, page_length}', $chunk);
        $this->assertStringContainsString('ScheduleJournalPageLengthFeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleJournalPageLengthDocumentationContractTest', $chunk);
        $this->assertStringContainsString('data-error-for="page_length"', $chunk);

        $root = dirname(__DIR__, 3);
        $service = (string) file_get_contents($root.'/app/Services/Schedule/ScheduleJournalPageLength.php');
        $this->assertStringContainsString("public const TABLE_KEY = 'schedule_journal';", $service);
        $this->assertStringContainsString('public const LENGTHS = [20, 50, 100];', $service);
        $this->assertStringContainsString('public const DEFAULT = 50;', $service);

        $request = (string) file_get_contents($root.'/app/Http/Requests/Admin/SaveScheduleJournalPageLengthRequest.php');
        $this->assertStringContainsString('Можно показать 20, 50 или 100 учеников.', $request);
        $this->assertStringContainsString('Укажите, сколько учеников показывать.', $request);
        $this->assertStringContainsString('Количество учеников должно быть целым числом.', $request);

        $blade = (string) file_get_contents($root.'/resources/views/admin/schedule/_journal_group_users.blade.php');
        $this->assertStringContainsString('Показывать по', $blade);
        $this->assertStringContainsString('schedule-journal-per-page__select', $blade);
        $this->assertStringContainsString('data-error-for="page_length"', $blade);
        $this->assertStringContainsString('$users->total() >= \\App\\Services\\Schedule\\ScheduleJournalPageLength::DEFAULT', $blade);
        $this->assertStringContainsString('$users->lastPage() > 1', $blade);

        $routes = (string) file_get_contents($root.'/routes/web.php');
        $this->assertStringContainsString("->name('schedule.journal-page-length')", $routes);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
