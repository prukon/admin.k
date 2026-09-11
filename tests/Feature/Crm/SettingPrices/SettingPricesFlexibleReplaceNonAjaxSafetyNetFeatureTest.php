<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * Non-AJAX safety-net: POST без X-Requested-With → 302 и запись в БД (не пустой 200).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesFlexibleReplaceNonAjaxSafetyNetFeatureTest extends SettingPricesFlexibleReplaceTestCase
{
    public function test_non_ajax_right_apply_paid_replace_redirects_and_keeps_price(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

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

        $seed['row']->refresh();
        $this->assertSame((int) $this->to->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
    }

    public function test_non_ajax_volume_blocked_redirects_with_package_error(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 2, paid: false);

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 3000.0, (int) $this->small->id),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['usersPrice.0.lesson_package_id']);

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
    }

    public function test_non_ajax_unknown_package_redirects_with_field_error(): void
    {
        $this->assignFromPackage();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, 999999),
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['usersPrice.0.lesson_package_id']);
    }

    public function test_non_ajax_year_save_paid_replace_redirects_and_persists(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

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

        $seed['row']->refresh();
        $this->assertSame((int) $this->to->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
    }

    public function test_non_ajax_year_save_volume_blocked_redirects_with_prices_error(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 2, paid: false);

        $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 3000,
                    'lesson_package_id' => (int) $this->small->id,
                ]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['prices.0.lesson_package_id']);

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
    }

    public function test_non_ajax_team_snapshot_paid_replace_redirects_and_keeps_price(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->to->id,
            ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $seed['row']->refresh();
        $this->assertSame((int) $this->to->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
    }
}
