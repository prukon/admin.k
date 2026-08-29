<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#trainer-salary-sales-index и ЗП §13 совпадают с живой схемой sales.
 */
final class TrainerSalarySalesDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_sales_scheme(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="trainer-salary-sales-index"', $html);
        $start = strpos($html, 'id="trainer-salary-sales-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="journal-postpay-payment-due-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end, 'Анонс sales стоит выше более старых блоков журнала.');
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('schedule.trainerSalary.scheme.sales', $chunk);
        $this->assertStringContainsString('intdiv', $chunk);
        $this->assertStringContainsString('team_trainer', $chunk);
        $this->assertStringContainsString('каждый 100% базы', $chunk);
        $this->assertStringContainsString('trainer_salary_sales_draft_trainers', $chunk);
        $this->assertStringContainsString('/docs/documentation/schedule-trainer-salary#sales', $chunk);
        $this->assertStringContainsString('ScheduleTrainerSalarySalesFeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleTrainerSalarySalesAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ScheduleTrainerSalarySalesUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString('test_trainer_salary_js_sales_live_row_update_contract', $chunk);
        $this->assertStringContainsString('errors.sales_percent', $chunk);
        $this->assertStringContainsString('без</b> <code>reload_table</code>', $chunk);
        $this->assertStringContainsString('step="1"', $chunk);
        $this->assertStringContainsString('is_manual_paid=false', $chunk);
        $this->assertStringContainsString('default_base_salary_cents', $chunk);
        $this->assertStringContainsString('2026_08_29_013000_add_trainer_salary_scheme_sales.php', $chunk);
        $this->assertStringNotContainsString('ставки × тренировки учеников', $chunk);
    }

    public function test_salary_page_section_matches_code(): void
    {
        $html = $this->docFile('schedule-trainer-salary.html');
        $scheme = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Schedule/TrainerSalary/Schemes/Sales/SalesTrainerSalaryScheme.php');
        $calculator = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Schedule/TrainerSalary/Schemes/Sales/SalesTrainerSalaryCalculator.php');
        $aggregator = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Schedule/TrainerSalary/Schemes/Sales/SalesPaidSalesAggregator.php');

        $this->assertStringContainsString('id="sales"', $html);
        $this->assertStringContainsString('schedule.trainerSalary.scheme.sales', $html);
        $this->assertStringContainsString('intdiv(sales_base_cents × sales_percent, 100)', $html);
        $this->assertStringContainsString('errors.sales_percent', $html);
        $this->assertStringContainsString('ScheduleTrainerSalarySalesAccessFeatureTest', $html);
        $this->assertStringContainsString('ScheduleTrainerSalarySalesUiContractsFeatureTest', $html);
        $this->assertStringContainsString('reload_table', $html);
        $this->assertStringContainsString('trainer_salary_sales_draft_trainers', $html);
        $this->assertStringContainsString('2026_08_29_013000_add_trainer_salary_scheme_sales.php', $html);

        $this->assertStringContainsString("public const CODE = 'sales'", $scheme);
        $this->assertStringContainsString("schedule.trainerSalary.scheme.sales", $scheme);
        $this->assertStringContainsString("'integer'", $scheme);
        $this->assertStringContainsString("'max:100'", $scheme);

        $this->assertStringContainsString('intdiv($salesBaseCents * $salesPercent, 100)', $calculator);
        $this->assertStringContainsString('lesson_package_fee', $aggregator);
        $this->assertStringContainsString('team_trainer', $aggregator);
        $this->assertStringContainsString('EFFECTIVE_PAID_SQL', $aggregator);

        $this->assertStringContainsString('sales_percent', $html);
        $this->assertStringContainsString('без <code>reload_table</code>', $html);
        $this->assertStringContainsString('тренировки журнала не считает', $html);
    }

    public function test_registry_and_seeder_include_sales(): void
    {
        $provider = (string) file_get_contents(dirname(__DIR__, 3).'/app/Providers/AppServiceProvider.php');
        $seeder = (string) file_get_contents(dirname(__DIR__, 3).'/database/seeders/PermissionSeeder.php');
        $sheets = $this->docFile('schedule-trainer-salary-sheets.html');

        $this->assertStringContainsString('SalesTrainerSalaryScheme', $provider);
        $this->assertStringContainsString('schedule.trainerSalary.scheme.sales', $seeder);
        $this->assertStringContainsString('sort_order\' => 28', $seeder);
        $this->assertStringContainsString('trainer-salary/sales/_sheet_detail_table.blade.php', $sheets);
        $this->assertStringContainsString('…sales', $sheets);

        $journal = $this->docFile('schedule-journal.html');
        $trainers = $this->docFile('admin-trainers.html');
        $perms = $this->docFile('partners-permissions.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('scheme.sales', $journal);
        $this->assertStringContainsString('trainer-salary-sales-index', $journal);
        $this->assertStringContainsString('schedule.trainerSalary.scheme.sales', $trainers);
        $this->assertStringContainsString('sales_percent', $trainers);
        $this->assertStringContainsString('schedule.trainerSalary.scheme.sales', $perms);
        $this->assertStringContainsString('schedule.trainerSalary.scheme.sales', $groups);
        $this->assertStringContainsString('2026_08_29_013000_add_trainer_salary_scheme_sales.php', $groups);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
