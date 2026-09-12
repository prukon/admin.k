<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Support\SettingPricesRequirePackageForPositivePrice;

/**
 * Non-AJAX safety-net: цена &gt; 0 только с абонементом.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesRequirePackageForPriceNonAjaxSafetyNetFeatureTest extends SettingPricesRequirePackageForPriceTestCase
{
    public function test_non_ajax_right_apply_redirects_and_saves_price_with_package(): void
    {
        $row = $this->seedUnpaidMonth(0);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), $this->monthlyPayload(4500, (int) $this->package->id));

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $row->refresh();
        $this->assertSame((int) $this->package->id, (int) $row->lesson_package_id);
        $this->assertSame(450000, (int) $row->price_cents);
    }

    public function test_non_ajax_year_save_redirects_and_creates_price_with_package(): void
    {
        $response = $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.user-year-prices.save'), $this->yearPayload(4500, (int) $this->package->id));

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 450000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_non_ajax_price_without_package_redirects_with_field_error(): void
    {
        $row = $this->seedUnpaidMonth(0);

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), $this->monthlyPayload(1500, null))
            ->assertStatus(302)
            ->assertSessionHasErrors(['usersPrice.0.lesson_package_id']);

        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            session('errors')->first('usersPrice.0.lesson_package_id')
        );

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertSame(0, (int) $row->price_cents);
    }

    public function test_non_ajax_year_save_price_without_package_redirects_with_field_error(): void
    {
        $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.user-year-prices.save'), $this->yearPayload(1500, null))
            ->assertStatus(302)
            ->assertSessionHasErrors(['prices.0.lesson_package_id']);

        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            session('errors')->first('prices.0.lesson_package_id')
        );

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 150000,
        ]);
    }

    public function test_non_ajax_clear_to_zero_redirects_and_clears_charge(): void
    {
        $row = $this->seedUnpaidMonth(450000, (int) $this->package->id);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), $this->monthlyPayload(0, null));

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertSame(0, (int) $row->price_cents);
    }
}
