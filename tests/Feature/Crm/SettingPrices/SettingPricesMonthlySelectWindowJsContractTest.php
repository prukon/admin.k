<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * Inline-JS селекта месяца на вкладке «По месяцам»: окно сентябрь 2025 … август 2027.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 */
final class SettingPricesMonthlySelectWindowJsContractTest extends TestCase
{
    public function test_monthly_blade_select_reads_window_from_data_attributes_not_hardcoded_2024(): void
    {
        $blade = (string) file_get_contents($this->monthlyBladePath());

        $this->assertStringContainsString('id="single-select-date"', $blade);
        $this->assertStringContainsString('data-start-year="{{ (int) $monthlySelectStartYear }}"', $blade);
        $this->assertStringContainsString('data-start-month-index="{{ (int) $monthlySelectStartMonthIndex }}"', $blade);
        $this->assertStringContainsString('data-month-count="{{ (int) $monthlySelectMonthCount }}"', $blade);
        $this->assertStringContainsString('data-selected-label="{{ $monthString }}"', $blade);
        $this->assertStringContainsString('dataset.startYear', $blade);
        $this->assertStringContainsString('dataset.startMonthIndex', $blade);
        $this->assertStringContainsString('dataset.monthCount', $blade);
        $this->assertStringContainsString("selectElement.innerHTML = '';", $blade);
        $this->assertStringNotContainsString('const startYear = 2024', $blade);
        $this->assertStringNotContainsString('let CountMonths', $blade);
    }

    public function test_monthly_select_js_builds_september_2025_through_august_2027_without_excluded_months(): void
    {
        $blade = (string) file_get_contents($this->monthlyBladePath());
        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $blade, $matches);
        $this->assertNotEmpty($matches[1], 'В monthly.blade.php нет inline <script>');

        $fillScript = '';
        foreach ($matches[1] as $raw) {
            if (str_contains($raw, 'dataset.startYear')) {
                $fillScript = trim($raw);
                break;
            }
        }
        $this->assertNotSame('', $fillScript, 'Не найден script заполнения #single-select-date');

        $harness = <<<'JS'
const options = [];
const mockSelect = {
  dataset: {
    startYear: '2025',
    startMonthIndex: '8',
    monthCount: '24',
    selectedLabel: 'Октябрь 2025',
  },
  appendChild(el) { options.push(el); },
};
Object.defineProperty(mockSelect, 'innerHTML', {
  set(v) { if (v === '') { options.length = 0; } },
  get() { return ''; },
});
global.document = {
  getElementById(id) {
    if (id !== 'single-select-date') {
      throw new Error('unexpected id ' + id);
    }
    return mockSelect;
  },
  createElement() {
    return { value: '', textContent: '', selected: false };
  },
};
JS;
        $footer = <<<'JS'

const labels = options.map(function (o) { return o.value; });
const selected = options.filter(function (o) { return o.selected; }).map(function (o) { return o.value; });
process.stdout.write(JSON.stringify({ labels: labels, selected: selected }));
JS;

        $tmp = sys_get_temp_dir().'/monthly-select-window-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $harness."\n".$fillScript."\n".$footer);

        try {
            $output = [];
            $exitCode = 0;
            exec('node '.escapeshellarg($tmp).' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, implode("\n", $output));

            $payload = json_decode(implode("\n", $output), true);
            $this->assertIsArray($payload);
            $labels = $payload['labels'] ?? [];
            $this->assertCount(24, $labels);
            $this->assertSame('Сентябрь 2025', $labels[0]);
            $this->assertSame('Август 2027', $labels[23]);
            $this->assertSame(['Октябрь 2025'], $payload['selected'] ?? []);

            foreach ($labels as $label) {
                $this->assertStringNotContainsString('2024', $label, $label);
            }

            $excluded2025 = [
                'Январь 2025',
                'Февраль 2025',
                'Март 2025',
                'Апрель 2025',
                'Май 2025',
                'Июнь 2025',
                'Июль 2025',
                'Август 2025',
            ];
            foreach ($excluded2025 as $label) {
                $this->assertNotContains($label, $labels);
            }

            $this->assertContains('Сентябрь 2025', $labels);
            $this->assertContains('Сентябрь 2026', $labels);
            $this->assertContains('Август 2027', $labels);
        } finally {
            @unlink($tmp);
        }
    }

    private function monthlyBladePath(): string
    {
        $path = resource_path('views/admin/SettingPrices/monthly.blade.php');
        $this->assertFileExists($path);

        return $path;
    }
}
