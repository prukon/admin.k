<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#journal-bulk-several-days-index совпадает с несколькими днями в «Добавить занятие».
 */
final class ScheduleJournalBulkSeveralDaysDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_several_days_in_bulk_place(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="journal-bulk-several-days-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-acquiring-amount-lock-index"', $start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/schedule', $chunk);
        $this->assertStringContainsString('#schedule-bulk-add', $chunk);
        $this->assertStringContainsString('#bulkPlaceModal', $chunk);
        $this->assertStringContainsString('Можно выбрать только группу {название}.', $chunk);
        $this->assertStringContainsString('bulkSelection.cells', $chunk);
        $this->assertStringContainsString('userId|дата', $chunk);
        $this->assertStringContainsString('За один раз можно поставить не больше 400 занятий.', $chunk);
        $this->assertStringContainsString('POST /schedule/bulk-place', $chunk);
        $this->assertStringContainsString('lessons[]', $chunk);
        $this->assertStringContainsString('trainer_profile_ids[]', $chunk);
        $this->assertStringContainsString('В абонементе не осталось занятий.', $chunk);
        $this->assertStringContainsString('errors.lessons', $chunk);
        $this->assertStringContainsString('Выберите занятия.', $chunk);
        $this->assertStringContainsString('errors.lessons.0.occurrence_date', $chunk);
        $this->assertStringContainsString('Некорректный формат даты занятия.', $chunk);
        $this->assertStringContainsString('На одну дату можно поставить занятие не больше чем 100 ученикам.', $chunk);
        $this->assertStringContainsString('#bulk-status-error', $chunk);
        $this->assertStringContainsString('schedule-journal#bulk-empty-lessons', $chunk);
        $this->assertStringContainsString('ScheduleJournalBulkPlaceFeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleJournalBulkPlaceContractsFeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleJournalBulkSeveralDaysDocumentationContractTest', $chunk);
        $this->assertStringContainsString(
            'BladeInlineJsSyntaxTest::test_schedule_journal_bulk_place_keeps_selection_and_does_not_open_single_modal',
            $chunk
        );
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($html, 'journal-bulk-several-days-index'),
            'Анонс должен быть на /doc, в оглавлении и в журнале'
        );
    }

    public function test_schedule_journal_doc_and_sources_match_several_days(): void
    {
        $journal = $this->docFile('schedule-journal.html');
        $start = strpos($journal, 'id="bulk-empty-lessons"');
        $this->assertNotFalse($start);
        $end = strpos($journal, '7.4.2) UI', $start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($journal, $start, $end - $start);

        $this->assertStringContainsString('bulkSelection.cells', $chunk);
        $this->assertStringContainsString('Можно выбрать только группу {название}.', $chunk);
        $this->assertStringContainsString('lessons[]', $chunk);
        $this->assertStringContainsString('Выберите занятия.', $chunk);
        $this->assertStringContainsString('Некорректный формат даты занятия.', $chunk);
        $this->assertStringContainsString('На одну дату можно поставить занятие не больше чем 100 ученикам.', $chunk);
        $this->assertStringContainsString('За один раз можно поставить не больше 400 занятий.', $chunk);
        $this->assertStringContainsString('#bulk-status-error', $chunk);
        $this->assertStringContainsString('В абонементе не осталось занятий.', $chunk);
        $this->assertStringContainsString('ScheduleJournalBulkSeveralDaysDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#journal-bulk-several-days-index', $journal);

        $root = dirname(__DIR__, 3);
        $request = (string) file_get_contents($root.'/app/Http/Requests/Admin/PlaceScheduleJournalBulkLessonsRequest.php');
        $this->assertStringContainsString('Выберите занятия.', $request);
        $this->assertStringContainsString('Некорректный формат даты занятия.', $request);
        $this->assertStringContainsString('На одну дату можно поставить занятие не больше чем 100 ученикам.', $request);
        $this->assertStringContainsString('За один раз можно поставить не больше 400 занятий.', $request);
        $this->assertStringContainsString("'max:400'", $request);
        $this->assertStringContainsString('function normalizedLessons', $request);

        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/ScheduleController.php');
        $this->assertStringContainsString('ksort($byDate);', $controller);
        $this->assertStringContainsString('normalizedLessons()', $controller);

        $js = (string) file_get_contents($root.'/resources/js/schedule.js');
        $this->assertStringContainsString('Можно выбрать только группу ', $js);
        $this->assertStringContainsString('function bulkClearDate', $js);
        $this->assertStringContainsString('bulk-student__date', $js);
        $this->assertStringContainsString('occurrence_date: user.date', $js);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
