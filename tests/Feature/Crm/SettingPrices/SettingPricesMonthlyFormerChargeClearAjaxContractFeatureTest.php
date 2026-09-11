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
 * JSON-контракт POST …/former-month-charge/clear (как шлёт Vite-модуль).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesMonthlyFormerChargeClearAjaxContractFeatureTest extends CrmTestCase
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
            'title' => 'Алмаз Ajax корзина',
        ]);

        $this->currentStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Текущий',
            'name' => 'Ajax',
        ]);
        $teamSync->syncTeamsForStudent($this->currentStudent, [(int) $this->team->id]);

        $this->formerStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Бывший',
            'name' => 'Ajax',
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
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
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

    public function test_ajax_clear_returns_json_success_and_not_empty_200(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->payload());

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Начисление снято.')
            ->assertJsonStructure(['success', 'message']);

        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 0,
            'lesson_package_id' => null,
        ]);
    }

    public function test_ajax_json_body_as_vite_module_sends_clears_row(): void
    {
        $response = $this->call(
            'POST',
            route('setting-prices.former-month-charge.clear'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
            json_encode($this->payload())
        );

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

    public function test_ajax_missing_user_returns_422_on_user_id(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), [
                'team_id' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id'])
            ->assertJsonPath('errors.user_id.0', 'Не указан ученик.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 326700,
        ]);
    }

    public function test_ajax_zero_user_id_returns_422_on_user_id(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), [
                'user_id' => 0,
                'team_id' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id'])
            ->assertJsonPath('errors.user_id.0', 'Некорректный ученик.');
    }

    public function test_ajax_missing_team_returns_422_on_team_id(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $this->formerStudent->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id'])
            ->assertJsonPath('errors.team_id.0', 'Выберите группу.');
    }

    public function test_ajax_missing_month_returns_422_on_selected_date(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $this->formerStudent->id,
                'team_id' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц.');
    }

    public function test_ajax_current_member_returns_422_on_charge_and_does_not_write(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 150000,
            'is_paid' => 0,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $this->currentStudent->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['charge'])
            ->assertJsonPath(
                'errors.charge.0',
                'Снять начисление корзиной можно только у ученика, который больше не состоит в этой группе.'
            );

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 150000,
        ]);
    }

    public function test_ajax_paid_former_returns_422_on_charge(): void
    {
        $this->row->forceFill(['is_paid' => 1])->save();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['charge'])
            ->assertJsonPath('errors.charge.0', FormerMemberMonthChargeService::REASON_PAID);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 326700,
            'is_paid' => 1,
        ]);
    }
}
