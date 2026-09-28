<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Документация журнала и календаря школы описывает несколько пробных и счётчик,
 * а не флаг «пробное уже использовано».
 */
final class UnlimitedSchoolScheduleTrialsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_unlimited_trials_at_the_top(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="unlimited-school-schedule-trials-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-template-team-title-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('school_schedule_trial_lessons_count', $chunk);
        $this->assertStringContainsString('data-occurrence-count="0"', $chunk);
        $this->assertStringContainsString('×N', $chunk);
        $this->assertStringContainsString('Пробная запись на это занятие уже добавлена.', $chunk);
        $this->assertStringContainsString('errors.user_id', $chunk);
        $this->assertStringContainsString('schedule.view', $chunk);
        $this->assertStringContainsString('lessonPackages.view', $chunk);
        $this->assertStringContainsString('ScheduleJournalUnlimitedTrialsFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolScheduleUnlimitedTrialsFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/schedule-journal#place-empty-cell', $chunk);
        $this->assertStringNotContainsString('Уже есть пробное занятие', $chunk);
        $this->assertStringNotContainsString('has_used_school_schedule_trial', $html);
        $this->assertStringContainsString('/doc#unlimited-school-schedule-trials-index', $html);
    }

    public function test_journal_and_calendar_docs_describe_unlimited_trials_and_the_counter(): void
    {
        $journal = $this->docFile('schedule-journal.html');
        $calendar = $this->docFile('school-schedule-calendar.html');
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        foreach ([$journal, $calendar, $index] as $html) {
            $this->assertStringContainsString('school_schedule_trial_lessons_count', $html);
            $this->assertStringNotContainsString('has_used_school_schedule_trial', $html);
            $this->assertStringNotContainsString('Уже есть пробное занятие', $html);
            $this->assertStringNotContainsString('alreadyScheduledReason', $html);
            $this->assertStringContainsString('/doc#unlimited-school-schedule-trials-index', $html);
        }

        $this->assertStringContainsString('Лимита «одно пробное на ученика» нет', $journal);
        $this->assertStringContainsString('ScheduleJournalUnlimitedTrialsFeatureTest', $journal);
        $this->assertStringContainsString('data-empty-lesson', $journal);

        $this->assertStringContainsString('Уже стоящее пробное на другом занятии кнопку не выключает', $calendar);
        $this->assertStringContainsString('SchoolScheduleUnlimitedTrialsFeatureTest', $calendar);
        $this->assertStringContainsString('school_schedule_trial_lessons_count</code> уменьшается на 1', $calendar);

        $this->assertStringContainsString('сколько угодно пробных', $controller);
        $this->assertStringContainsString('school_schedule_trial_lessons_count', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
