<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * UX-баг: при 0 ₽ в поле суммы ручная «Оплачено» рисовала галочку в установке цен,
 * хотя журнал и задолженности нулевую сумму не считают оплатой.
 *
 * Падает на коде до фикса (POST mode=paid при 0 писал is_manual_paid=1).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesManualPaidZeroPriceFeatureTest extends SettingPricesManualPaidZeroPriceTestCase
{
    public function test_marking_paid_at_zero_rub_does_not_look_paid(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        $this->assertSame($this->zeroPriceMessage(), $this->jsonFieldError(
            $this->withHeaders($this->ajaxHeaders())
                ->postJson(route('setting-prices.manual-paid'), $this->paidPayload()),
            'price'
        ));

        $row->refresh();
        $this->assertSame(0, (int) $row->price_cents);
        $this->assertNull($row->is_manual_paid);
        $this->assertFalse($row->effective_is_paid);
    }

    public function test_marking_paid_after_typing_positive_amount_saves_amount_and_paid(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload([
                'lesson_package_id' => $this->package->id,
                'price' => 9800.0,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.effective_is_paid', true);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 980000,
            'is_manual_paid' => 1,
            'is_paid' => 0,
        ]);
    }

    public function test_typing_zero_on_positive_row_then_marking_paid_keeps_old_amount_unpaid(): void
    {
        $row = $this->seedPositiveUnpaidMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload([
                'lesson_package_id' => $this->package->id,
                'price' => 0,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 980000,
            'is_manual_paid' => null,
        ]);
        $this->assertFalse($row->fresh()->effective_is_paid);
    }

    public function test_users_tab_can_mark_paid_when_stored_amount_is_positive(): void
    {
        $row = $this->seedPositiveUnpaidMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.effective_is_paid', true)
            ->assertJsonPath('user_price.price', 9800);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 980000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_clearing_manual_paid_at_zero_rub_is_allowed(): void
    {
        $row = $this->seedZeroManuallyPaidMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->unpaidPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.effective_is_paid', false);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 0,
            'is_manual_paid' => 0,
        ]);
    }

    public function test_users_tab_year_prices_stay_unpaid_after_rejected_zero_paid(): void
    {
        $this->seedZeroUnpaidMonth();

        $before = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
            ])
            ->assertOk();
        $octoberBefore = collect($before->json('months'))->firstWhere('new_month', self::MONTH_DATE);
        $this->assertNotNull($octoberBefore);
        $this->assertFalse((bool) $octoberBefore['effective_is_paid']);
        $this->assertSame(0, (int) $octoberBefore['price']);
        $this->assertTrue((bool) $octoberBefore['has_price_row']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        $after = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
            ])
            ->assertOk();
        $octoberAfter = collect($after->json('months'))->firstWhere('new_month', self::MONTH_DATE);
        $this->assertNotNull($octoberAfter);
        $this->assertFalse((bool) $octoberAfter['effective_is_paid']);
        $this->assertNull($octoberAfter['is_manual_paid']);
        $this->assertSame(0, (int) $octoberAfter['price']);
    }

    public function test_monthly_team_price_stays_unpaid_after_rejected_zero_paid(): void
    {
        $this->seedZeroUnpaidMonth();

        $before = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => self::MONTH_LABEL,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotSame('', trim($before->getContent()));
        $rowBefore = collect($before->json('usersPrice'))->firstWhere('user_id', $this->student->id);
        $this->assertIsArray($rowBefore);
        $this->assertSame(0, (int) $rowBefore['price']);
        $this->assertFalse((bool) $rowBefore['effective_is_paid']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertStatus(422);

        $after = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => self::MONTH_LABEL,
            ])
            ->assertOk();
        $rowAfter = collect($after->json('usersPrice'))->firstWhere('user_id', $this->student->id);
        $this->assertIsArray($rowAfter);
        $this->assertSame(0, (int) $rowAfter['price']);
        $this->assertFalse((bool) $rowAfter['effective_is_paid']);
        $this->assertNull($rowAfter['is_manual_paid']);
    }
}
