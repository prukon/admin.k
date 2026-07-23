<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для store/update вкладки «по месяцам» (абонементы).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesMonthlyPackageNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа non-ajax',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1000,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Non-AJAX тариф',
            'price_cents' => 420000,
        ]);
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

    public function test_set_team_price_non_ajax_redirects_and_persists_package_snapshot(): void
    {
        $response = $this->post(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Сентябрь 2024',
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 4200,
            'lesson_package_id' => $this->package->id,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 4200,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_set_team_price_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Сентябрь 2024',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['lesson_package_id']);

        $this->assertDatabaseMissing('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_set_team_price_ajax_returns_json_contract(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->package->id,
                'selectedDate' => 'Сентябрь 2024',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('teamPrice', 4200)
            ->assertJsonPath('lesson_package_id', $this->package->id)
            ->assertJsonPath('teamId', $this->team->id)
            ->assertJsonStructure([
                'success',
                'teamPrice',
                'lesson_package_id',
                'selectedDate',
                'teamId',
            ]);
    }

    public function test_set_price_all_teams_non_ajax_redirects_and_updates_db(): void
    {
        TeamPrice::forceCreate([
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1111,
            'lesson_package_id' => null,
        ]);

        $response = $this->post(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData' => [
                [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 4200,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_set_price_all_teams_non_ajax_invalid_payload_redirects_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllTeams'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamsData' => null,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['teamsData']);
    }

    public function test_set_price_all_teams_ajax_returns_json_contract(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllTeams'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamsData' => [
                    [
                        'teamId' => $this->team->id,
                        'lesson_package_id' => $this->package->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_set_price_all_users_non_ajax_redirects_and_updates_row(): void
    {
        $response = $this->post(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 3990,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->student->name],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 3990,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_set_price_all_users_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 'bad',
                        'user' => ['name' => $this->student->name],
                    ],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['usersPrice.0.price']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1000,
        ]);
    }

    public function test_set_price_all_users_ajax_returns_json_contract_with_lesson_packages(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 4100,
                        'lesson_package_id' => $this->package->id,
                        'user' => ['name' => $this->student->name],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'usersPrice',
                'selectedDate',
                'lessonPackages' => [
                    ['id', 'name', 'price'],
                ],
            ]);
    }

    public function test_update_date_non_ajax_redirects_and_initializes_team_prices(): void
    {
        $response = $this->post(route('updateDate'), [
            'month' => 'Октябрь 2024',
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
        ]);
    }

    public function test_update_date_ajax_returns_json_contract(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->post(route('updateDate'), [
                'month' => 'Ноябрь 2024',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('month', 'Ноябрь 2024');
    }
}
