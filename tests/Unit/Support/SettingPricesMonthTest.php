<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SettingPricesMonth;
use PHPUnit\Framework\TestCase;

final class SettingPricesMonthTest extends TestCase
{
    public function test_parses_russian_label_and_next_month_including_year_boundary(): void
    {
        $this->assertSame('2026-09-01', SettingPricesMonth::tryParseLabel('Сентябрь 2026'));
        $this->assertSame('Сентябрь 2026', SettingPricesMonth::toLabel('2026-09-01'));
        $this->assertSame('2026-10-01', SettingPricesMonth::nextMonth('2026-09-01'));
        $this->assertSame('2027-01-01', SettingPricesMonth::nextMonth('2026-12-01'));
        $this->assertSame('Январь 2027', SettingPricesMonth::toLabel('2027-01-01'));
        $this->assertSame('сентябре', SettingPricesMonth::toPrepositionalMonth('2026-09-01'));
        $this->assertSame('мае', SettingPricesMonth::toPrepositionalMonth('2026-05-01'));
        $this->assertSame('январе', SettingPricesMonth::toPrepositionalMonth('2027-01-01'));
    }

    public function test_rejects_unknown_month_instead_of_falling_back_to_today(): void
    {
        $this->assertNull(SettingPricesMonth::tryParseLabel('Немесяц 2026'));
        $this->assertNull(SettingPricesMonth::tryParseLabel('Сентябрь'));
        $this->assertNull(SettingPricesMonth::tryParseLabel(''));
        $this->assertNull(SettingPricesMonth::tryParseLabel('Сентябрь 26'));
    }
}
