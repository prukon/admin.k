<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * JSON-контракт AJAX: ручная оплата при 0 ₽ → 422 на поле цены.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesManualPaidZeroPriceAjaxContractFeatureTest extends SettingPricesManualPaidZeroPriceTestCase
{
    public function test_ajax_paid_at_zero_returns_422_on_price_with_message(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload());

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price'])
            ->assertJsonPath('errors.price.0', $this->zeroPriceMessage());
        $this->assertArrayHasKey('message', $response->json());
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 0,
            'is_manual_paid' => null,
        ]);
    }

    public function test_ajax_users_tab_payload_without_card_price_still_rejects_stored_zero(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload());

        $response
            ->assertStatus(422)
            ->assertJsonMissingPath('user_price')
            ->assertJsonValidationErrors(['price']);

        $row->refresh();
        $this->assertNull($row->is_manual_paid);
    }

    public function test_ajax_card_price_zero_returns_422_and_does_not_change_stored_amount(): void
    {
        $row = $this->seedPositiveUnpaidMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload([
                'lesson_package_id' => $this->package->id,
                'price' => 0,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price'])
            ->assertJsonPath('errors.price.0', $this->zeroPriceMessage());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 980000,
            'is_manual_paid' => null,
        ]);
    }

    public function test_ajax_card_positive_price_from_zero_row_returns_json_user_price(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload([
                'lesson_package_id' => $this->package->id,
                'price' => 9800.0,
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'user_price' => [
                    'id',
                    'user_id',
                    'team_id',
                    'price',
                    'is_manual_paid',
                    'effective_is_paid',
                ],
            ])
            ->assertJsonPath('user_price.price', 9800)
            ->assertJsonPath('user_price.effective_is_paid', true);
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 980000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_ajax_unpaid_at_zero_returns_json_success(): void
    {
        $row = $this->seedZeroManuallyPaidMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->unpaidPayload());

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.effective_is_paid', false);
        $this->assertNotSame('', trim($response->getContent()));

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 0,
            'is_manual_paid' => 0,
        ]);
    }

    public function test_ajax_missing_comment_returns_422_on_comment_not_price(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => self::MONTH_LABEL,
                'mode' => 'paid',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment'])
            ->assertJsonMissingPath('errors.price');

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => null,
        ]);
    }
}
