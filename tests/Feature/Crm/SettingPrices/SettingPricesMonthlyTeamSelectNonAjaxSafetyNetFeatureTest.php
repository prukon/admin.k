<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * get-team-price — read-only: без X-Requested-With всё равно JSON, не пустой 200 и не 500.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 */
final class SettingPricesMonthlyTeamSelectNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
            'title' => 'Легион NonAjax',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);
    }

    public function test_json_body_without_ajax_header_still_returns_students_json(): void
    {
        $response = $this->call(
            'POST',
            route('getTeamPrice'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'text/html',
            ],
            json_encode([
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
        );

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('success', true);
        $ids = collect($response->json('usersTeam'))->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $this->assertContains($this->student->id, $ids);
        $this->assertNotSame('', trim($response->getContent()));
    }

    public function test_html_form_post_returns_students_json_not_empty_200(): void
    {
        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('success', true);
        $ids = collect($response->json('usersTeam'))->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $this->assertContains($this->student->id, $ids);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 0,
        ]);
    }

    public function test_html_form_post_without_month_returns_422_json_not_redirect(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('getTeamPrice'), [
                'teamId' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц.');
    }
}
