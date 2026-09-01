<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Селект месяца на вкладке «По месяцам»: окно с сентября 2025, clamp старых значений.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 */
final class SettingPricesMonthlySelectWindowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
    }

    public function test_monthly_select_window_starts_september_2025_for_24_months(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index')
            ->assertViewHas('monthlySelectStartYear', 2025)
            ->assertViewHas('monthlySelectStartMonthIndex', 8)
            ->assertViewHas('monthlySelectMonthCount', 24)
            ->getContent();

        $this->assertStringContainsString('id="single-select-date"', $html);
        $this->assertStringContainsString('data-start-year="2025"', $html);
        $this->assertStringContainsString('data-start-month-index="8"', $html);
        $this->assertStringContainsString('data-month-count="24"', $html);
        $this->assertStringNotContainsString('data-start-year="2024"', $html);
        $this->assertStringNotContainsString('const startYear = 2024', $html);
    }

    public function test_monthly_page_clamps_every_month_before_september_2025(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        foreach ($this->monthsBeforeSelectWindow() as $savedMonth) {
            $this->withSession([
                'current_partner' => $this->partner->id,
                'prices_month' => $savedMonth,
            ]);

            $response = $this->get(route('admin.settingPrices.indexMenu'))
                ->assertOk();

            $this->assertSame(
                'Сентябрь 2025',
                $response->viewData('monthString'),
                'Ожидали clamp для «'.$savedMonth.'»'
            );
            $this->assertSame('Сентябрь 2025', session('prices_month'), $savedMonth);

            $html = $response->getContent();
            $this->assertStringContainsString('data-selected-label="Сентябрь 2025"', $html, $savedMonth);
            $this->assertStringNotContainsString(
                'data-selected-label="'.$savedMonth.'"',
                $html,
                $savedMonth
            );
        }

        $this->assertDatabaseHas('team_prices', [
            'new_month' => '2025-09-01',
        ]);
    }

    public function test_monthly_page_keeps_saved_months_inside_select_window(): void
    {
        foreach ($this->monthsInsideSelectWindow() as $savedMonth) {
            $this->withSession([
                'current_partner' => $this->partner->id,
                'prices_month' => $savedMonth,
            ]);

            $response = $this->get(route('admin.settingPrices.indexMenu'))
                ->assertOk();

            $this->assertSame($savedMonth, $response->viewData('monthString'), $savedMonth);
            $this->assertSame($savedMonth, session('prices_month'), $savedMonth);
            $this->assertStringContainsString(
                'data-selected-label="'.$savedMonth.'"',
                $response->getContent(),
                $savedMonth
            );
        }
    }

    public function test_update_date_still_accepts_month_before_window_and_monthly_get_clamps(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('updateDate'), ['month' => 'Сентябрь 2024'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'month' => 'Сентябрь 2024',
            ]);

        $this->assertSame('Сентябрь 2024', session('prices_month'));

        $response = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk();

        $this->assertSame('Сентябрь 2025', $response->viewData('monthString'));
        $this->assertSame('Сентябрь 2025', session('prices_month'));
    }

    public function test_users_tab_does_not_clamp_saved_month_before_select_window(): void
    {
        $this->withSession([
            'current_partner' => $this->partner->id,
            'prices_month' => 'Март 2025',
        ]);

        $this->get(route('admin.settingPrices.users'))
            ->assertOk();

        $this->assertSame('Март 2025', session('prices_month'));

        $monthly = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk();

        $this->assertSame('Сентябрь 2025', $monthly->viewData('monthString'));
        $this->assertSame('Сентябрь 2025', session('prices_month'));
    }

    /**
     * @return list<string>
     */
    private function monthsBeforeSelectWindow(): array
    {
        return [
            'Январь 2024',
            'Февраль 2024',
            'Март 2024',
            'Апрель 2024',
            'Май 2024',
            'Июнь 2024',
            'Июль 2024',
            'Август 2024',
            'Сентябрь 2024',
            'Октябрь 2024',
            'Ноябрь 2024',
            'Декабрь 2024',
            'Январь 2025',
            'Февраль 2025',
            'Март 2025',
            'Апрель 2025',
            'Май 2025',
            'Июнь 2025',
            'Июль 2025',
            'Август 2025',
        ];
    }

    /**
     * @return list<string>
     */
    private function monthsInsideSelectWindow(): array
    {
        return [
            'Сентябрь 2025',
            'Октябрь 2025',
            'Декабрь 2025',
            'Август 2026',
            'Сентябрь 2026',
            'Август 2027',
        ];
    }
}
