<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#landing-team-info-rows-index и school-leads-landing §5.4.2:
 * team-info отдаёт только адрес / вид спорта / стоимость / период.
 */
final class SchoolLeadLandingTeamInfoDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_reduced_team_info_rows(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="landing-team-info-rows-index"', $html);
        $start = strpos($html, 'id="landing-team-info-rows-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="user-delete-clears-email-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Занятий в неделю', $chunk);
        $this->assertStringContainsString('Занятий в месяц', $chunk);
        $this->assertStringContainsString('Продолжительность занятия', $chunk);
        $this->assertStringContainsString('Расписание занятий', $chunk);
        $this->assertStringContainsString('Адрес', $chunk);
        $this->assertStringContainsString('Вид спорта', $chunk);
        $this->assertStringContainsString('Стоимость в месяц', $chunk);
        $this->assertStringContainsString('Период занятий', $chunk);
        $this->assertStringContainsString('school-leads-landing#5-4-2', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingTeamInfoFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingFullFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingTeamInfoDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#landing-team-info-rows-index', $html);
    }

    public function test_landing_doc_lists_team_info_rows_and_removed_labels(): void
    {
        $html = $this->docFile('school-leads-landing.html');

        $this->assertStringContainsString('id="5-4-2"', $html);
        $start = strpos($html, 'id="5-4-2"');
        $this->assertNotFalse($start);
        $end = strpos($html, '>5.5) Прочее<');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Адрес', $chunk);
        $this->assertStringContainsString('locations.address', $chunk);
        $this->assertStringContainsString('Вид спорта', $chunk);
        $this->assertStringContainsString('Стоимость в месяц', $chunk);
        $this->assertStringContainsString('month_price_cents', $chunk);
        $this->assertStringContainsString('Период занятий', $chunk);
        $this->assertStringContainsString('formatTrainingPeriod', $chunk);
        $this->assertStringContainsString('Занятий в неделю', $chunk);
        $this->assertStringContainsString('Занятий в месяц', $chunk);
        $this->assertStringContainsString('Продолжительность занятия', $chunk);
        $this->assertStringContainsString('Расписание занятий', $chunk);
        $this->assertStringContainsString('hideTeamInfo()', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingTeamInfoFeatureTest.php', $chunk);
        $this->assertStringContainsString('/doc#landing-team-info-rows-index', $chunk);
    }

    public function test_related_docs_point_to_team_info_section(): void
    {
        $bindings = $this->docFile('location-team-bindings.html');
        $hierarchy = $this->docFile('directories-hierarchy.html');

        $this->assertStringContainsString('school-leads-landing#5-4-2', $bindings);
        $this->assertStringContainsString('Расписание занятий', $bindings);
        $this->assertStringContainsString('school-leads-landing#5-4-2', $hierarchy);
        $this->assertStringContainsString('SchoolLeadLandingTeamInfoFeatureTest.php', $hierarchy);
        $this->assertStringContainsString('SchoolLeadLandingTeamInfoDocumentationContractTest.php', $hierarchy);

        $sportTypes = $this->docFile('admin-sport-types.html');
        $this->assertStringContainsString('school-leads-landing#5-4-2', $sportTypes);
    }

    public function test_landing_service_does_not_emit_removed_team_info_labels(): void
    {
        $path = dirname(__DIR__, 3).'/app/Services/SchoolLeadLandingService.php';
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertStringContainsString("'Адрес'", $src);
        $this->assertStringContainsString("'Вид спорта'", $src);
        $this->assertStringContainsString("'Стоимость в месяц'", $src);
        $this->assertStringContainsString("'Период занятий'", $src);
        $this->assertStringContainsString('function formatTrainingPeriod', $src);
        $this->assertStringNotContainsString("'Занятий в неделю'", $src);
        $this->assertStringNotContainsString("'Занятий в месяц'", $src);
        $this->assertStringNotContainsString("'Продолжительность занятия'", $src);
        $this->assertStringNotContainsString("'Расписание занятий'", $src);
        $this->assertStringNotContainsString('function formatDuration', $src);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
