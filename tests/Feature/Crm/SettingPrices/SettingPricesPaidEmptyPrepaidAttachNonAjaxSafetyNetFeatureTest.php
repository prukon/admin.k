<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * Non-AJAX safety-net: постановка предоплаты на оплаченный месяц без абона.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesPaidEmptyPrepaidAttachNonAjaxSafetyNetFeatureTest extends SettingPricesPaidEmptyPrepaidAttachTestCase
{
    public function test_non_ajax_right_apply_redirects_and_attaches_prepaid(): void
    {
        $row = $this->seedPaidEmptyMonth();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $this->assertNotNull($row->user_lesson_package_id);
    }

    public function test_non_ajax_year_save_redirects_and_attaches_prepaid(): void
    {
        $row = $this->seedPaidEmptyMonth(280000);

        $response = $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 8000,
                    'lesson_package_id' => (int) $this->to->id,
                ]],
            ]);

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
        $this->assertSame(280000, (int) $row->price_cents);
    }

    public function test_non_ajax_team_snapshot_redirects_and_attaches_prepaid(): void
    {
        $row = $this->seedPaidEmptyMonth(360000);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->from->id,
            ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $row->refresh();
        $this->assertSame((int) $this->from->id, (int) $row->lesson_package_id);
        $this->assertSame(360000, (int) $row->price_cents);
    }

    public function test_non_ajax_all_teams_snapshot_redirects_and_attaches_prepaid(): void
    {
        $row = $this->seedPaidEmptyMonth(190000);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllTeams'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamsData' => [[
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->to->id,
                ]],
            ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
        $this->assertSame(190000, (int) $row->price_cents);
    }

    public function test_non_ajax_fixed_on_paid_empty_redirects_with_package_error(): void
    {
        $row = $this->seedPaidEmptyMonth();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 9000.0, (int) $this->fixed->id),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['usersPrice.0.lesson_package_id']);

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
    }
}
