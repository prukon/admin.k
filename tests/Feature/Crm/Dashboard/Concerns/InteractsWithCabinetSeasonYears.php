<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard\Concerns;

use App\Models\User;

/**
 * Согласовано с createSeasons() в dashboard.blade.php:
 * data-season = год окончания учебного года (сент–авг).
 */
trait InteractsWithCabinetSeasonYears
{
    /**
     * @var list<string>
     */
    private const SEASON_MONTHS_RU = [
        'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
        'Июль', 'Август',
    ];

    /**
     * @var list<int>
     */
    private const SEASON_CALENDAR_MONTHS = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8];

    /**
     * @return list<string> YYYY-MM-01
     */
    protected function billingMonthsForSeasonEndYear(int $seasonEndYear): array
    {
        $out = [];
        foreach (self::SEASON_MONTHS_RU as $key => $monthRu) {
            $displayYear = in_array($monthRu, ['Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'], true)
                ? $seasonEndYear - 1
                : $seasonEndYear;
            $month = self::SEASON_CALENDAR_MONTHS[$key];
            $out[] = sprintf('%04d-%02d-01', $displayYear, $month);
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    protected function seasonEndYearsFromHtml(string $html): array
    {
        preg_match_all('/data-season="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1] ?? []);
    }

    /**
     * Месяцы, которые createSeasons() положит в .new-price-description / formatedPaymentDate.
     *
     * @return list<string>
     */
    protected function billingMonthsFromCabinetHtml(string $html): array
    {
        $months = [];
        foreach ($this->seasonEndYearsFromHtml($html) as $endYear) {
            foreach ($this->billingMonthsForSeasonEndYear($endYear) as $ymd) {
                $months[] = $ymd;
            }
        }

        return $months;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function embeddedUserPrices(string $html): array
    {
        $needle = 'var userPriceAll = ';
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos, 'В HTML /cabinet нет var userPriceAll');

        $start = $pos + strlen($needle);
        $this->assertSame('[', $html[$start] ?? '', 'userPriceAll должен начинаться с JSON-массива');

        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($html);
        for ($i = $start; $i < $len; $i++) {
            $ch = $html[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($html, $start, $i - $start + 1);
                    $decoded = json_decode($json, true);
                    $this->assertIsArray($decoded, 'userPriceAll не JSON: '.json_last_error_msg());

                    return $decoded;
                }
            }
        }

        $this->fail('JSON userPriceAll не закрылся');
    }

    /**
     * @param  list<array<string, mixed>>  $prices
     * @return list<string>
     */
    protected function priceMonthKeys(array $prices): array
    {
        $out = [];
        foreach ($prices as $row) {
            $raw = (string) ($row['new_month'] ?? '');
            if ($raw === '') {
                continue;
            }
            $out[] = substr($raw, 0, 10);
        }

        return $out;
    }

    protected function assertChargeHasJsSeasonCell(string $html, string $ymd): void
    {
        $this->assertContains(
            $ymd,
            $this->priceMonthKeys($this->embeddedUserPrices($html)),
            "В payload userPriceAll нет начисления {$ymd}"
        );
        $this->assertContains(
            $ymd,
            $this->billingMonthsFromCabinetHtml($html),
            "Нет шапки сезона, из которой createSeasons() сделает ячейку {$ymd} — родитель не увидит оплату"
        );
    }

    protected function cabinetHtmlFor(User $actor): string
    {
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        return is_string($content) ? $content : '';
    }
}
