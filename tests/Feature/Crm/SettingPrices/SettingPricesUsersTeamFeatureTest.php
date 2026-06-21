<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Team-scoped цены ученика: разные группы — разные записи users_prices,
 * AJAX-контракт и fallback non-AJAX POST для save / manual-paid.
 */
final class SettingPricesUsersTeamFeatureTest extends StudentTeamPivotTestCase
{
    private Team $teamA;

    private Team $teamB;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $this->teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->student = $this->makeStudentWithTeams([$this->teamA, $this->teamB]);
    }

    public function test_user_year_prices_returns_prices_scoped_to_requested_team(): void
    {
        $this->asAdmin();

        UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-03-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);
        UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamB->id,
            'new_month' => '2024-03-01',
            'price'     => 2000,
            'is_paid'   => 0,
        ]);

        $responseA = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.team_id', $this->teamA->id);

        $marchA = collect($responseA->json('months'))->firstWhere('new_month', '2024-03-01');
        $this->assertNotNull($marchA);
        $this->assertSame(1000, $marchA['price']);

        $responseB = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->teamB->id,
                'year'    => 2024,
            ])
            ->assertOk()
            ->assertJsonPath('user.team_id', $this->teamB->id);

        $marchB = collect($responseB->json('months'))->firstWhere('new_month', '2024-03-01');
        $this->assertNotNull($marchB);
        $this->assertSame(2000, $marchB['price']);
    }

    public function test_save_user_year_prices_does_not_change_other_team_price(): void
    {
        $this->asAdmin();

        UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-05-01',
            'price'     => 1100,
            'is_paid'   => 0,
        ]);
        UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamB->id,
            'new_month' => '2024-05-01',
            'price'     => 2200,
            'is_paid'   => 0,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
                'prices'  => [
                    ['new_month' => '2024-05-01', 'price' => 1500],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-05-01',
            'price'     => 1500,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamB->id,
            'new_month' => '2024-05-01',
            'price'     => 2200,
        ]);
    }

    public function test_user_year_prices_validation_requires_team_id(): void
    {
        $this->asAdmin();

        $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'year'    => 2024,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_save_user_year_prices_validation_requires_team_id(): void
    {
        $this->asAdmin();

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'year'    => 2024,
            'prices'  => [
                ['new_month' => '2024-06-01', 'price' => 500],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_save_user_year_prices_rejects_team_where_student_not_member(): void
    {
        $this->asAdmin();

        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $foreignTeam->id,
            'year'    => 2024,
            'prices'  => [
                ['new_month' => '2024-07-01', 'price' => 900],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $foreignTeam->id,
            'new_month' => '2024-07-01',
        ]);
    }

    public function test_save_user_year_prices_non_ajax_redirects_and_persists(): void
    {
        $this->asAdmin();

        $response = $this->post(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->teamA->id,
            'year'    => 2024,
            'prices'  => [
                ['new_month' => '2024-04-01', 'price' => 4500],
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-04-01',
            'price'     => 4500,
        ]);
    }

    public function test_manual_paid_is_scoped_to_team(): void
    {
        $this->asSuperadmin();

        $rowA = UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);
        $rowB = UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamB->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id'      => $this->student->id,
                'team_id'      => $this->teamA->id,
                'selectedDate' => 'Сентябрь 2024',
                'mode'         => 'paid',
                'comment'      => 'Оплата только для группы A',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.id', $rowA->id);

        $this->assertDatabaseHas('users_prices', [
            'id'             => $rowA->id,
            'is_manual_paid' => 1,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'id'             => $rowB->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_manual_paid_non_ajax_redirects_and_updates_row(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-10-01',
            'price'     => 1200,
            'is_paid'   => 0,
        ]);

        $response = $this->post(route('setting-prices.manual-paid'), [
            'user_id'      => $this->student->id,
            'team_id'      => $this->teamA->id,
            'selectedDate' => 'Октябрь 2024',
            'mode'         => 'paid',
            'comment'      => 'Non-AJAX fallback',
        ]);

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id'             => $row->id,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_set_team_price_creates_user_price_only_for_target_team(): void
    {
        $this->asAdmin();

        $this->postJson(route('setTeamPrice'), [
            'teamId'       => $this->teamA->id,
            'teamPrice'    => 1800,
            'selectedDate' => 'Ноябрь 2024',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-11-01',
            'price'     => 1800,
        ]);
        $this->assertDatabaseMissing('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamB->id,
            'new_month' => '2024-11-01',
        ]);
    }

    public function test_set_price_all_users_scopes_price_to_selected_team(): void
    {
        $this->asAdmin();

        UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamB->id,
            'new_month' => '2024-12-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Декабрь 2024',
            'teamId'       => $this->teamB->id,
            'usersPrice'   => [
                [
                    'user_id' => $this->student->id,
                    'price'   => 3300,
                    'user'    => ['name' => $this->student->name],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamB->id,
            'new_month' => '2024-12-01',
            'price'     => 3300,
        ]);
        $this->assertDatabaseMissing('users_prices', [
            'user_id'   => $this->student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-12-01',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }
}
