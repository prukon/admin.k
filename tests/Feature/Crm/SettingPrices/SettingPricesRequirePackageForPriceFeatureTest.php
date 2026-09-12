<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use App\Support\SettingPricesRequirePackageForPositivePrice;

/**
 * Цена &gt; 0 только с абонементом: «По месяцам» и «По ученикам».
 *
 * Падает на коде до фикса: POST с суммой без пакета отдавал 200 и писал строку.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html#require-package-for-price
 */
final class SettingPricesRequirePackageForPriceFeatureTest extends SettingPricesRequirePackageForPriceTestCase
{
    public function test_manager_cannot_set_new_price_without_package(): void
    {
        $this->seedUnpaidMonth(0);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(1500, null));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'lesson_package_id' => null,
        ]);
    }

    public function test_manager_saves_price_when_package_is_selected(): void
    {
        $this->seedUnpaidMonth(0);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(4500, (int) $this->package->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 450000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_manager_cannot_clear_package_while_keeping_price(): void
    {
        $this->seedUnpaidMonth(450000, (int) $this->package->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(4500, null))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 450000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_manager_can_clear_charge_to_zero_without_package(): void
    {
        $this->seedUnpaidMonth(450000, (int) $this->package->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(0, null))
            ->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'lesson_package_id' => null,
        ]);
    }

    public function test_legacy_unchanged_price_without_package_is_kept(): void
    {
        $this->seedUnpaidMonth(150000, null);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(1500, null))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 150000,
            'lesson_package_id' => null,
        ]);
    }

    public function test_changing_legacy_price_without_package_is_rejected(): void
    {
        $this->seedUnpaidMonth(150000, null);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(2000, null));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 150000,
            'lesson_package_id' => null,
        ]);
    }

    public function test_users_tab_rejects_new_price_without_package(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), $this->yearPayload(1500, null));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prices.0.lesson_package_id']);
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $this->jsonFieldError($response, 'prices.0.lesson_package_id')
        );

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 150000,
        ]);
    }

    public function test_users_tab_saves_price_when_package_is_selected(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), $this->yearPayload(4500, (int) $this->package->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 450000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_users_tab_can_clear_charge_to_zero_without_package(): void
    {
        $this->seedUnpaidMonth(450000, (int) $this->package->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), $this->yearPayload(0, null))
            ->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'lesson_package_id' => null,
        ]);
    }

    public function test_paid_month_keeps_sync_message_when_removing_package(): void
    {
        $this->seedPaidMonth(450000, (int) $this->package->id);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(4500, null));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame(
            'Нельзя снять абонемент у оплаченного месяца.',
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );
        $this->assertNotSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 450000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_mixed_students_fail_together_when_one_has_price_without_package(): void
    {
        $this->seedUnpaidMonth(0);
        $okStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Ок',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($okStudent, [(int) $this->team->id]);
        UserPrice::forceCreate([
            'user_id' => $okStudent->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 1500, null),
                    $this->rightPayload($okStudent, 4500, (int) $this->package->id),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'lesson_package_id' => null,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $okStudent->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'lesson_package_id' => null,
        ]);
    }

    public function test_manual_paid_flag_without_package_fields_is_not_blocked(): void
    {
        $this->grantPermission($this->user, 'setPrices.manualPaid.manage');
        $row = $this->seedUnpaidMonth(450000, (int) $this->package->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => self::MONTH_LABEL,
                'mode' => 'paid',
                'comment' => 'Только статус, правило цены не дублируем',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame((int) $this->package->id, (int) $row->lesson_package_id);
        $this->assertSame(450000, (int) $row->price_cents);
        $this->assertTrue((bool) $row->is_manual_paid);
    }
}
