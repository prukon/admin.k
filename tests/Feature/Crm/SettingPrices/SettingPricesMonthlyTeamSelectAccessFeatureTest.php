<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к вкладке «По месяцам» и AJAX загрузки учеников группы.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 */
final class SettingPricesMonthlyTeamSelectAccessFeatureTest extends CrmTestCase
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

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Дубль Select',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'lastname' => 'Иванов',
            'name' => 'Пётр',
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

    public function test_guest_cannot_open_monthly_or_load_team_users(): void
    {
        Auth::logout();

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $this->assertContains($page->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $page->getStatusCode());
        $this->assertStringNotContainsString('id="left_bar"', $page->getContent());
        $this->assertStringNotContainsString("id='left_bar'", $page->getContent());

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ]);
        $this->assertContains($json->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertArrayNotHasKey('usersTeam', (array) $json->json());
    }

    public function test_user_without_set_prices_view_cannot_open_monthly_or_load_team_users(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertForbidden();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertForbidden();
    }

    public function test_admin_with_set_prices_view_can_open_monthly_and_load_team_users(): void
    {
        $this->asAdmin();

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $page->assertOk();
        $this->assertNotSame('', trim($page->getContent()));
        $this->assertMatchesRegularExpression('/id=[\'"]left_bar[\'"]/', $page->getContent());
        $this->assertMatchesRegularExpression('/id=[\'"]right_bar[\'"]/', $page->getContent());

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'usersTeam',
                'usersPrice',
                'lessonPackages',
                'can_manage_manual_paid',
            ]);
    }

    public function test_get_patch_delete_on_get_team_price_are_method_not_allowed(): void
    {
        $this->asAdmin();
        $url = route('getTeamPrice');

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $html = $this->call($method, $url);
            $this->assertContains(
                $html->getStatusCode(),
                [404, 405],
                "{$method} getTeamPrice HTML → {$html->getStatusCode()}"
            );
            $this->assertNotSame(500, $html->getStatusCode());
            $this->assertNotSame(200, $html->getStatusCode());

            $json = $this->json($method, $url, [
                'teamId' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ]);
            $this->assertContains(
                $json->getStatusCode(),
                [404, 405],
                "{$method} getTeamPrice JSON → {$json->getStatusCode()}"
            );
            $this->assertNotSame(500, $json->getStatusCode());
            $this->assertNotSame(200, $json->getStatusCode());
        }
    }

    public function test_foreign_partner_team_does_not_leak_students(): void
    {
        $this->asAdmin();

        $foreignPartner = Partner::factory()->create();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $foreignPartner->id,
            'deleted_at' => null,
            'title' => 'Чужая группа',
        ]);
        $foreignStudent = User::factory()->create([
            'partner_id' => $foreignPartner->id,
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($foreignStudent, [(int) $foreignTeam->id]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $foreignTeam->id,
                'selectedDate' => 'Февраль 2026',
            ]);

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Team not found');
        $this->assertArrayNotHasKey('usersTeam', (array) $response->json());
        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $foreignStudent->id,
            'team_id' => $foreignTeam->id,
            'new_month' => '2026-02-01',
        ]);
    }
}
