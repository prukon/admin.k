<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SettingPrices;

use App\Services\SettingPrices\MonthlyPricesProlongReport;
use PHPUnit\Framework\TestCase;

final class MonthlyPricesProlongReportTest extends TestCase
{
    public function test_skip_reasons_count_students_and_teams_separately(): void
    {
        $report = new MonthlyPricesProlongReport(
            '2026-10-01',
            '2026-11-01',
            'Октябрь 2026',
            'Ноябрь 2026',
        );

        $report->addStudentCreate(4, 'Ученик Г', 10, 'Группа', 'Пакет', 100);
        $report->addStudentSkip(
            MonthlyPricesProlongReport::REASON_EMPTY_SOURCE,
            1,
            'Ученик А',
            10,
            'Группа',
            false,
        );
        $report->addStudentSkip(
            MonthlyPricesProlongReport::REASON_EMPTY_SOURCE,
            2,
            'Ученик Б',
            10,
            'Группа',
            false,
        );
        $report->addTeamSkip(
            MonthlyPricesProlongReport::REASON_EMPTY_SOURCE,
            10,
            'Группа',
            false,
        );
        $report->addStudentSkip(
            MonthlyPricesProlongReport::REASON_ALREADY_SET,
            3,
            'Ученик В',
            10,
            'Группа',
        );

        $payload = $report->toArray(false);
        $byReason = [];
        foreach ($payload['skip_reasons'] as $row) {
            $byReason[$row['reason']] = $row;
        }

        $this->assertSame(2, $byReason['empty_source']['students']);
        $this->assertSame(1, $byReason['empty_source']['teams']);
        $this->assertSame('В октябре не установлены абонементы', $byReason['empty_source']['label']);
        $this->assertArrayNotHasKey('count', $byReason['empty_source']);
        $this->assertSame(1, $byReason['already_set']['students']);
        $this->assertSame(0, $byReason['already_set']['teams']);
        $this->assertSame(3, $payload['counts']['students_skip']);
        $this->assertSame(1, $payload['counts']['teams_skip']);
        $this->assertStringContainsString('Пропущено: учеников 3, групп 1', $payload['message']);
        $this->assertStringNotContainsString('Пропущено: 4.', $payload['message']);
    }

    public function test_empty_source_label_uses_prepositional_source_month(): void
    {
        $report = new MonthlyPricesProlongReport(
            '2026-09-01',
            '2026-10-01',
            'Сентябрь 2026',
            'Октябрь 2026',
        );
        $report->addStudentSkip(
            MonthlyPricesProlongReport::REASON_EMPTY_SOURCE,
            1,
            'Ученик',
            10,
            'Группа',
            false,
        );

        $payload = $report->toArray(false);
        $this->assertSame(
            'В сентябре не установлены абонементы',
            $payload['skip_reasons'][0]['label']
        );
    }
}
