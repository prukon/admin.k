<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * Non-AJAX safety-net: POST без X-Requested-With при 0 ₽.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesManualPaidZeroPriceNonAjaxSafetyNetFeatureTest extends SettingPricesManualPaidZeroPriceTestCase
{
    public function test_non_ajax_paid_at_zero_redirects_with_price_error_and_does_not_mark_paid(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), $this->paidPayload());

        $response
            ->assertStatus(302)
            ->assertSessionHasErrors(['price'])
            ->assertSessionHasErrors(['price' => $this->zeroPriceMessage()]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 0,
            'is_manual_paid' => null,
        ]);
    }

    public function test_non_ajax_paid_at_zero_from_users_tab_redirects_with_price_error(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $response = $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.manual-paid'), $this->paidPayload());

        $response
            ->assertStatus(302)
            ->assertSessionHasErrors(['price']);
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_non_ajax_card_positive_price_from_zero_redirects_and_marks_paid(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), $this->paidPayload([
                'lesson_package_id' => $this->package->id,
                'price' => 9800.0,
            ]));

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 980000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_non_ajax_unpaid_at_zero_redirects_and_clears_flag(): void
    {
        $row = $this->seedZeroManuallyPaidMonth();

        $response = $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.manual-paid'), $this->unpaidPayload());

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 0,
            'is_manual_paid' => 0,
        ]);
    }

    public function test_non_ajax_card_zero_on_positive_row_redirects_with_price_error(): void
    {
        $row = $this->seedPositiveUnpaidMonth();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), $this->paidPayload([
                'lesson_package_id' => $this->package->id,
                'price' => 0,
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['price']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 980000,
            'is_manual_paid' => null,
        ]);
    }
}
