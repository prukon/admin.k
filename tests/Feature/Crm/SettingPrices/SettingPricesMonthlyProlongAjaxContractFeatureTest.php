<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\Feature\Crm\CrmTestCase;

/**
 * JSON-контракт превью/записи пролонгации месяца.
 */
final class SettingPricesMonthlyProlongAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    public function test_preview_rejects_invalid_month_with_selected_date_error(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.prolong-month.preview'), [
                'selectedDate' => 'Немесяц 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите корректный месяц.');
    }

    public function test_preview_rejects_missing_month_with_selected_date_error(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.prolong-month.preview'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц.');
    }

    public function test_apply_rejects_missing_month_with_selected_date_error(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.prolong-month.apply'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц.');
    }

    public function test_apply_rejects_invalid_month_with_selected_date_error(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.prolong-month.apply'), [
                'selectedDate' => 'Foo 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите корректный месяц.');
    }

    public function test_preview_json_contract_for_empty_partner(): void
    {
        $preview = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.prolong-month.preview'), [
                'selectedDate' => 'Сентябрь 2026',
            ]);

        $preview
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source_month', '2026-09-01')
            ->assertJsonPath('target_month', '2026-10-01')
            ->assertJsonPath('source_month_label', 'Сентябрь 2026')
            ->assertJsonPath('target_month_label', 'Октябрь 2026')
            ->assertJsonPath('can_apply', false)
            ->assertJsonStructure([
                'skip_reasons',
                'counts' => [
                    'students_create',
                    'students_unchanged',
                    'students_skip',
                    'students_error',
                    'teams_set',
                    'teams_unchanged',
                    'teams_skip',
                ],
            ]);

        $this->assertDatabaseMissing('team_prices', [
            'new_month' => '2026-10-01',
        ]);
        $this->assertDatabaseMissing('users_prices', [
            'new_month' => '2026-10-01',
        ]);

        foreach ((array) $preview->json('skip_reasons') as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('reason', $row);
            $this->assertArrayHasKey('students', $row);
            $this->assertArrayHasKey('teams', $row);
            $this->assertArrayHasKey('label', $row);
            $this->assertArrayNotHasKey('count', $row);
        }
    }
}
