<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Services\Schedule\ScheduleJournalMonthService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ScheduleJournalPackageHoverLabelTest extends TestCase
{
    #[DataProvider('labelsProvider')]
    public function test_package_hover_label(string $name, ?int $cents, string $expected): void
    {
        $this->assertSame($expected, ScheduleJournalMonthService::packageHoverLabel($name, $cents));
    }

    /**
     * @return array<string, array{0: string, 1: int|null, 2: string}>
     */
    public static function labelsProvider(): array
    {
        return [
            'with fee' => ['Разовое занятие', 150000, 'Разовое занятие - 1 500 руб'],
            'zero fee' => ['Фикс', 0, 'Фикс - 0 руб'],
            'no fee' => ['Пробное', null, 'Пробное'],
            'empty name' => ['', 10000, 'Абонемент - 100 руб'],
        ];
    }

    #[DataProvider('postpayLabelsProvider')]
    public function test_postpay_package_hover_label(?int $cents, string $expected): void
    {
        $this->assertSame($expected, ScheduleJournalMonthService::postpayPackageHoverLabel($cents));
    }

    /**
     * @return array<string, array{0: int|null, 1: string}>
     */
    public static function postpayLabelsProvider(): array
    {
        return [
            'with price' => [50000, 'Постоплата: 500₽ в день'],
            'zero' => [0, 'Постоплата: 0₽ в день'],
            'no price' => [null, 'Постоплата'],
        ];
    }

    public function test_postpay_abonement_column_label(): void
    {
        $this->assertSame(
            "500₽\nв\u{00A0}день",
            \App\Services\Postpay\PostpayJournalService::postpayAbonementColumnLabel(50000)
        );
        $this->assertSame(
            "1000₽\nв\u{00A0}день",
            \App\Services\Postpay\PostpayJournalService::postpayAbonementColumnLabel(100000)
        );
    }

    public function test_flexible_abonement_column_label(): void
    {
        $this->assertSame(
            "10/12\nГибкий",
            ScheduleJournalMonthService::flexibleAbonementColumnLabel(10, 12)
        );
        $this->assertSame(
            "2/2\nГибкий",
            ScheduleJournalMonthService::flexibleAbonementColumnLabel(2, 2)
        );
    }

    public function test_flexible_abonement_column_hover_line_includes_price(): void
    {
        $this->assertSame(
            'Остаток занятий в текущем месяце по абонементу "Гибкий А" за 5 000 руб',
            ScheduleJournalMonthService::flexibleAbonementColumnHoverLine('Гибкий А', 500000)
        );
        $this->assertSame(
            '10/12 остаток занятий в текущем месяце по абонементу "Гибкий Б" за 0 руб',
            ScheduleJournalMonthService::flexibleAbonementColumnHoverLine('Гибкий Б', 0, true, 10, 12)
        );
        $this->assertSame(
            'Остаток занятий в текущем месяце по абонементу "Гибкий абонемент" за 100 руб',
            ScheduleJournalMonthService::flexibleAbonementColumnHoverLine('  ', 10000)
        );
    }

    public function test_fixed_abonement_place_button_hover_line(): void
    {
        $this->assertSame(
            'Название абона (4/4) за 5 000 руб',
            ScheduleJournalMonthService::fixedAbonementPlaceButtonHoverLine('Название абона', 4, 4, 500000)
        );
        $this->assertSame(
            'Абонемент (2/8) за 0 руб',
            ScheduleJournalMonthService::fixedAbonementPlaceButtonHoverLine('  ', 2, 8, 0)
        );
    }

    public function test_append_trainer_to_hover(): void
    {
        $this->assertSame(
            "Пробное\nТренер: Иванов Иван",
            ScheduleJournalMonthService::appendTrainerToHover('Пробное', true, 'Иванов Иван')
        );
        $this->assertSame(
            'Тренер не выбран',
            ScheduleJournalMonthService::appendTrainerToHover('', true, null)
        );
        $this->assertSame(
            'Пробное',
            ScheduleJournalMonthService::appendTrainerToHover('Пробное', false, 'Иванов Иван')
        );
    }
}
