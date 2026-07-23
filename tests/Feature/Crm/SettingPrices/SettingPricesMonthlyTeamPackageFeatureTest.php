<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Выбор абонемента для группы (левый столбец «по месяцам»).
 */
final class SettingPricesMonthlyTeamPackageFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $unpaid;

    private User $paidAuto;

    private User $paidManual;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа пакет',
        ]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Групповой тариф',
            'price_cents' => 550000,
        ]);

        $this->unpaid = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);
        $this->paidAuto = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);
        $this->paidManual = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);

        UserPrice::forceCreate([
            'user_id' => $this->unpaid->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1000,
            'is_paid' => 0,
            'is_manual_paid' => null,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->paidAuto->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 800,
            'is_paid' => 1,
            'is_manual_paid' => null,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->paidManual->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 900,
            'is_paid' => 0,
            'is_manual_paid' => true,
        ]);
    }

    public function test_monthly_page_renders_team_package_select(): void
    {
        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('setting-prices-team-package-select', false)
            ->assertSee('Групповой тариф', false);
    }

    public function test_set_team_price_applies_package_snapshot_and_skips_effective_paid(): void
    {
        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Сентябрь 2024',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('teamPrice', 5500)
            ->assertJsonPath('lesson_package_id', $this->package->id);

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 5500,
            'lesson_package_id' => $this->package->id,
        ]);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->unpaid->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 5500,
            'lesson_package_id' => $this->package->id,
        ]);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->paidAuto->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 800,
            'is_paid' => 1,
        ]);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->paidManual->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 900,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_set_team_price_creates_missing_user_price_and_skips_disabled(): void
    {
        $withoutRow = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);
        $disabled = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => false,
        ]);

        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Октябрь 2024',
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $withoutRow->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 5500,
            'lesson_package_id' => $this->package->id,
        ]);
        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $disabled->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
        ]);
    }

    public function test_set_team_price_uses_server_package_price_not_client_amount(): void
    {
        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'teamPrice' => 1,
            'price' => 1,
            'selectedDate' => 'Сентябрь 2024',
        ])
            ->assertOk()
            ->assertJsonPath('teamPrice', 5500);

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 5500,
        ]);
    }

    public function test_monthly_page_marks_selected_team_package_and_shows_legacy_price(): void
    {
        TeamPrice::forceCreate([
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 777,
            'lesson_package_id' => $this->package->id,
        ]);

        // месяц в сессии — как после updateDate
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
            'prices_month' => 'Сентябрь 2024',
        ]);

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-legacy-price="777"', $html);
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="' . preg_quote((string) $this->package->id, '/') . '"[^>]*selected/i',
            $html
        );
    }

    public function test_set_price_all_teams_skips_rows_without_package_and_applies_selected(): void
    {
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $pkgB = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 220000,
        ]);

        TeamPrice::forceCreate([
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1000,
        ]);
        TeamPrice::forceCreate([
            'team_id' => $teamB->id,
            'new_month' => '2024-09-01',
            'price' => 2000,
        ]);

        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData' => [
                [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->package->id,
                ],
                [
                    'teamId' => $teamB->id,
                    // без абонемента — отфильтруется на бэке
                ],
                [
                    'teamId' => $teamB->id,
                    'lesson_package_id' => $pkgB->id,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 5500,
            'lesson_package_id' => $this->package->id,
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id' => $teamB->id,
            'new_month' => '2024-09-01',
            'price' => 2200,
            'lesson_package_id' => $pkgB->id,
        ]);
    }

    public function test_set_price_all_teams_empty_selection_is_noop(): void
    {
        TeamPrice::forceCreate([
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1111,
        ]);

        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData' => [
                ['teamId' => $this->team->id],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1111,
        ]);
    }

    public function test_set_team_price_rejects_foreign_package(): void
    {
        $foreign = LessonPackage::factory()->forPartner(
            (int) \App\Models\Partner::factory()->create()->id
        )->create(['price_cents' => 10000]);

        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $foreign->id,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);
    }
}
