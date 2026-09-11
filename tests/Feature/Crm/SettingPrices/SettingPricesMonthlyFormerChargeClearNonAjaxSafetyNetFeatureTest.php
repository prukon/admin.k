<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\SettingPrices\FormerMemberMonthChargeService;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net: форма без X-Requested-With → 302 на monthly, запись снимается.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesMonthlyFormerChargeClearNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $formerStudent;

    private User $currentStudent;

    private UserPrice $row;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();

        $teamSync = app(TeamUserSyncService::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз NonAjax корзина',
        ]);

        $this->currentStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Текущий',
            'name' => 'NonAjax',
        ]);
        $teamSync->syncTeamsForStudent($this->currentStudent, [(int) $this->team->id]);

        $this->formerStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Бывший',
            'name' => 'NonAjax',
        ]);
        $teamSync->syncTeamsForStudent($this->formerStudent, [(int) $this->team->id]);

        $this->row = UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);
        $teamSync->syncTeamsForStudent($this->formerStudent, []);
    }

    /**
     * @return array{user_id: int, team_id: int, selectedDate: string}
     */
    private function payload(): array
    {
        return [
            'user_id' => (int) $this->formerStudent->id,
            'team_id' => (int) $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ];
    }

    public function test_html_form_post_redirects_to_monthly_and_clears_charge(): void
    {
        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), $this->payload());

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 0,
            'lesson_package_id' => null,
        ]);
    }

    public function test_html_form_post_without_month_redirects_with_field_error(): void
    {
        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $this->formerStudent->id,
                'team_id' => $this->team->id,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['selectedDate' => 'Укажите месяц.']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 326700,
        ]);
    }

    public function test_html_form_post_for_current_member_redirects_with_charge_error(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 150000,
            'is_paid' => 0,
        ]);

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $this->currentStudent->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['charge']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 150000,
        ]);
    }

    public function test_html_form_post_for_paid_former_redirects_with_charge_error(): void
    {
        $this->row->forceFill(['is_paid' => 1])->save();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), $this->payload())
            ->assertStatus(302)
            ->assertSessionHasErrors(['charge' => FormerMemberMonthChargeService::REASON_PAID]);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 326700,
            'is_paid' => 1,
        ]);
    }

    public function test_json_accept_without_ajax_header_still_returns_json_not_empty_200(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post(route('setting-prices.former-month-charge.clear'), $this->payload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Начисление снято.');
        $this->assertNotSame('', trim($response->getContent()));

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 0,
        ]);
    }
}
