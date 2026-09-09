<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * JSON-контракт POST get-team-price: список учеников для правой колонки.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 */
final class SettingPricesMonthlyTeamSelectAjaxContractFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

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
            'title' => 'Рубин Ajax',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'lastname' => 'Сидоров',
            'name' => 'Иван',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);
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

    public function test_loading_team_with_students_returns_json_list_and_creates_zero_price_rows(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'usersTeam' => [
                    ['id', 'name', 'lastname', 'is_former_member'],
                ],
                'usersPrice' => [
                    ['user_id', 'team_id', 'price', 'is_former_member'],
                ],
                'lessonPackages',
                'can_manage_manual_paid',
            ]);

        $this->assertSame($this->student->id, (int) $response->json('usersTeam.0.id'));
        $this->assertFalse((bool) $response->json('usersTeam.0.is_former_member'));
        $this->assertSame($this->student->id, (int) $response->json('usersPrice.0.user_id'));
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 0,
        ]);
    }

    public function test_empty_group_returns_200_without_users_not_empty_200(): void
    {
        $empty = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Пустая',
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $empty->id,
                'selectedDate' => 'Февраль 2026',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'lessonPackages']);
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertArrayNotHasKey('usersTeam', (array) $response->json());
        $this->assertArrayNotHasKey('usersPrice', (array) $response->json());
    }

    public function test_unknown_team_returns_404_json_with_message(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => 999999999,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Team not found');
    }

    public function test_missing_team_id_returns_422_on_team_id_not_500(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['teamId'])
            ->assertJsonPath('errors.teamId.0', 'Укажите группу.');
    }

    public function test_soft_deleted_team_returns_404_not_students(): void
    {
        $this->team->delete();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Team not found');
    }

    public function test_missing_month_returns_422_on_selected_date_not_500(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц.');
    }
}
