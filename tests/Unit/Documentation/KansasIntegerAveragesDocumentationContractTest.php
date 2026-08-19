<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#trainer-salary-kansas-integer-averages-index и ЗП §12.1
 * должны совпадать с живым Канзасом: факт вверх после десятой, база только целое, без ховера на средних.
 */
final class KansasIntegerAveragesDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_integer_averages_before_older_kansas_blocks(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="trainer-salary-kansas-integer-averages-index"', $html);
        $start = strpos($html, 'id="trainer-salary-kansas-integer-averages-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-header-subtitle-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end, 'Анонс целых средних Канзаса стоит выше более старых chat-блоков.');
        $chunk = substr($html, $start, $end - $start);

        $this->assertGreaterThan(
            $start,
            (int) strpos($html, 'id="trainer-salary-kansas-month-settings-index"')
        );
        $this->assertGreaterThan(
            $start,
            (int) strpos($html, 'id="trainer-salary-kansas-index"')
        );

        $this->assertStringContainsString('15.04 → 15', $chunk);
        $this->assertStringContainsString('15.1 → 16', $chunk);
        $this->assertStringContainsString('KansasQuantity::averageToTenths', $chunk);
        $this->assertStringContainsString('151 учеников / 10 тренировок → 160', $chunk);
        $this->assertStringContainsString('step="1"', $chunk);
        $this->assertStringContainsString('16.5', $chunk);
        $this->assertStringContainsString('16.0', $chunk);
        $this->assertStringContainsString('errors.base_avg_students', $chunk);
        $this->assertStringContainsString('422', $chunk);
        $this->assertStringContainsString('302', $chunk);
        $this->assertStringContainsString('data-kids-tooltip-hint', $chunk);
        $this->assertStringContainsString('премия за тренировку <b>900</b> ₽ (не 810)', $chunk);
        $this->assertStringContainsString('*_tenths', $chunk);
        $this->assertStringContainsString('16 → 160', $chunk);
        $this->assertStringContainsString('"16"</code>, не <code>"16.0"', $chunk);
        $this->assertStringContainsString('PATCH <code>base_avg_students</code> игнорируется', $chunk);
        $this->assertStringContainsString('/docs/documentation/schedule-trainer-salary#kansas-integer-averages', $chunk);
        $this->assertStringContainsString('ScheduleTrainerSalaryKansasIntegerAveragesFeatureTest', $chunk);
        $this->assertStringContainsString('KansasTrainerSalaryCalculatorTest', $chunk);
        $this->assertStringContainsString('KansasIntegerAveragesDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('формула не менял', $chunk);
        $this->assertStringNotContainsString('премия <b>810</b>', $chunk);
        $this->assertStringNotContainsString('15.1 хранится', $chunk);
        $this->assertStringNotContainsString('step="0.1"', $chunk);
    }

    public function test_older_kansas_announcements_point_to_integer_averages_and_do_not_claim_old_formula(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('/doc#trainer-salary-kansas-integer-averages-index', $html);
        $this->assertStringContainsString('целые средние Канзаса', $html);
        $this->assertStringContainsString('факт 15.1 → 16', $html);
        $this->assertStringNotContainsString('Формула, права и смена схемы без слепка не менялись', $html);

        $monthStart = strpos($html, 'id="trainer-salary-kansas-month-settings-index"');
        $kansasStart = strpos($html, 'id="trainer-salary-kansas-index"');
        $this->assertNotFalse($monthStart);
        $this->assertNotFalse($kansasStart);
        $monthChunk = substr($html, $monthStart, $kansasStart - $monthStart);
        $this->assertStringContainsString('#trainer-salary-kansas-integer-averages-index', $monthChunk);
        $this->assertStringContainsString('15.1 → 16', $monthChunk);
        $this->assertStringContainsString('KansasIntegerAveragesDocumentationContractTest', $monthChunk);

        $familyStart = strpos($html, 'id="family-student-payment-index"');
        $this->assertNotFalse($familyStart);
        $kansasChunk = substr($html, $kansasStart, $familyStart - $kansasStart);
        $this->assertStringContainsString('#trainer-salary-kansas-integer-averages-index', $kansasChunk);
        $this->assertStringContainsString('вверх до целого после <code>round(..., 1)</code>', $kansasChunk);
        $this->assertStringContainsString('#kansas-integer-averages', $kansasChunk);
    }

    public function test_salary_page_section_matches_announcement(): void
    {
        $html = $this->docFile('schedule-trainer-salary.html');

        $this->assertStringContainsString('id="kansas-integer-averages"', $html);
        $this->assertStringContainsString('/doc#trainer-salary-kansas-integer-averages-index', $html);
        $this->assertStringContainsString('KansasIntegerAveragesDocumentationContractTest', $html);

        $start = strpos($html, 'id="kansas-integer-averages"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'trainer_salary_kansas_period_settings');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('KansasQuantity::averageToTenths', $chunk);
        $this->assertStringContainsString('toWholeTenths', $chunk);
        $this->assertStringContainsString('151/10 → 16', $chunk);
        $this->assertStringContainsString('ceil(round(sum / trainings_count, 1))', $chunk);
        $this->assertStringContainsString('15.04 → 15, 15.1 → 16', $chunk);
        $this->assertStringContainsString('step="1"', $chunk);
        $this->assertStringContainsString('step="0.01"', $chunk);
        $this->assertStringContainsString('16.5', $chunk);
        $this->assertStringContainsString('errors.base_avg_students', $chunk);
        $this->assertStringContainsString('не <code>"16.0"</code>', $chunk);
        $this->assertStringContainsString('Classic это правило не получает', $chunk);

        $this->assertStringNotContainsString('step="0.1"', $html);
        $this->assertStringNotContainsString('десятые в ховере', $html);
    }

    public function test_sheets_and_catalog_do_not_contradict_integer_averages(): void
    {
        $sheets = $this->docFile('schedule-trainer-salary-sheets.html');
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('средние — целые без ховера', $sheets);
        $this->assertStringContainsString('#kansas-integer-averages', $sheets);
        $this->assertStringContainsString('/doc#trainer-salary-kansas-integer-averages-index', $sheets);

        $this->assertStringContainsString('целые средние Канзаса', $controller);
        $this->assertStringContainsString('факт вверх после десятой, база только целое', $controller);
        $this->assertStringContainsString('id="trainer-salary-kansas-integer-averages-index"', $index);
        $this->assertStringContainsString('/doc#trainer-salary-kansas-integer-averages-index', $index);
    }

    public function test_live_code_matches_documented_integer_average_rules(): void
    {
        $quantity = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Schedule/TrainerSalary/Schemes/Kansas/KansasQuantity.php');
        $calculator = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Schedule/TrainerSalary/Schemes/Kansas/KansasTrainerSalaryCalculator.php');
        $scheme = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Schedule/TrainerSalary/Schemes/Kansas/KansasTrainerSalaryScheme.php');
        $avgCell = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/schedule/trainer-salary/kansas/_avg_cell.blade.php');
        $monthSettings = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/schedule/trainer-salary/kansas/_month_settings_body.blade.php');

        $this->assertStringContainsString('15.04 → 15, 15.1 → 16', $quantity);
        $this->assertStringContainsString('151 → 160 (15.1 → 16)', $quantity);
        $this->assertStringContainsString('function averageToTenths', $quantity);
        $this->assertStringContainsString('ceilTenthsToWholeTenths', $quantity);
        $this->assertStringContainsString("preg_match('/^\\d{1,3}$/', \$v)", $quantity);
        $this->assertStringContainsString('function formatTenthsAsInt', $quantity);
        $this->assertStringContainsString('return self::formatTenthsAsInt($tenths);', $quantity);

        $this->assertStringContainsString('KansasQuantity::averageToTenths', $calculator);
        $this->assertStringContainsString('15.04 → 15, 15.1 → 16', $calculator);

        $this->assertStringContainsString("'integer'", $scheme);
        $this->assertStringContainsString('Базовое среднее должно быть целым числом.', $scheme);
        $this->assertStringContainsString("'max:999'", $scheme);
        $this->assertStringContainsString('toWholeTenthsOrFail', $scheme);
        $this->assertStringContainsString('formatTenthsAsInt', $scheme);

        $this->assertStringNotContainsString('data-kids-tooltip-hint', $avgCell);
        $this->assertStringContainsString('целое без ховера', $avgCell);

        $this->assertStringContainsString('data-kids-tooltip-hint', $monthSettings);
        $this->assertStringContainsString('step="0.01"', $monthSettings);
        $this->assertStringContainsString('step="1"', $monthSettings);
        $this->assertStringContainsString('max="999"', $monthSettings);
        $this->assertSame(1, substr_count($monthSettings, 'data-kids-tooltip-hint'));
        $this->assertStringNotContainsString('step="0.1"', $monthSettings);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
