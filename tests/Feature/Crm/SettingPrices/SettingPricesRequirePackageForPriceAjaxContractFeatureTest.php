<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Support\SettingPricesRequirePackageForPositivePrice;

/**
 * JSON-контракт AJAX: цена &gt; 0 только с абонементом.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesRequirePackageForPriceAjaxContractFeatureTest extends SettingPricesRequirePackageForPriceTestCase
{
    public function test_ajax_right_apply_returns_json_when_package_and_price_are_set(): void
    {
        $this->seedUnpaidMonth(0);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(4500, (int) $this->package->id));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'usersPrice',
                'selectedDate',
                'lessonPackages',
            ]);
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));
        $this->assertSame(4500, (int) $response->json('usersPrice.0.price'));
        $this->assertSame((int) $this->package->id, (int) $response->json('usersPrice.0.lesson_package_id'));
    }

    public function test_ajax_year_save_returns_json_success_when_package_is_set(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), $this->yearPayload(4500, (int) $this->package->id));

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 450000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_ajax_price_without_package_returns_422_on_users_price_package_field(): void
    {
        $this->seedUnpaidMonth(0);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(1500, null));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertArrayHasKey('message', $response->json());
    }

    public function test_ajax_year_save_price_without_package_returns_422_on_prices_package_field(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), $this->yearPayload(1500, null));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prices.0.lesson_package_id']);
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $this->jsonFieldError($response, 'prices.0.lesson_package_id')
        );
        $this->assertArrayHasKey('message', $response->json());
    }

    public function test_ajax_missing_month_returns_422_on_selected_date(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 4500, (int) $this->package->id),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц для установки цен.');
    }

    public function test_ajax_legacy_same_price_without_package_returns_200_not_empty_json(): void
    {
        $this->seedUnpaidMonth(150000, null);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(1500, null));

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotSame('{}', trim($response->getContent()));
        $this->assertSame(1500, (int) $response->json('usersPrice.0.price'));
        $this->assertNull($response->json('usersPrice.0.lesson_package_id'));
    }

    public function test_ajax_year_save_clear_to_zero_returns_json_success(): void
    {
        $this->seedUnpaidMonth(450000, (int) $this->package->id);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), $this->yearPayload(0, null));

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotSame('', trim($response->getContent()));
    }
}
