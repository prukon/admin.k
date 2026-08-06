<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа и AJAX/non-AJAX контракты для истории бывших участников
 * на вкладке «по месяцам».
 *
 * @see SettingPricesMonthlyFormerMembersFeatureTest
 * @see SettingPricesMonthlyPackageAccessFeatureTest
 */
final class SettingPricesMonthlyFormerMembersAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $currentStudent;

    private User $formerStudent;

    private LessonPackage $package;

    private TeamUserSyncService $teamSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->teamSync = app(TeamUserSyncService::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз Access',
        ]);

        $this->currentStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Текущий',
            'name' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($this->currentStudent, [(int) $this->team->id]);

        $this->formerStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Бывший',
            'name' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, [(int) $this->team->id]);

        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, []);

        UserPrice::forceCreate([
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 100000,
            'is_paid' => 0,
        ]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 450000,
            'is_active' => true,
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function formerMembersEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.indexMenu'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'POST',
                'url' => route('getTeamPrice'),
                'data' => [
                    'teamId' => $this->team->id,
                    'selectedDate' => 'Февраль 2026',
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setTeamPrice'),
                'data' => [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->package->id,
                    'selectedDate' => 'Февраль 2026',
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setPriceAllTeams'),
                'data' => [
                    'selectedDate' => 'Февраль 2026',
                    'teamsData' => [
                        [
                            'teamId' => $this->team->id,
                            'lesson_package_id' => $this->package->id,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setPriceAllUsers'),
                'data' => [
                    'selectedDate' => 'Февраль 2026',
                    'teamId' => $this->team->id,
                    'usersPrice' => [
                        [
                            'user_id' => $this->currentStudent->id,
                            'price' => 1100,
                            'lesson_package_id' => $this->package->id,
                            'user' => ['name' => $this->currentStudent->name],
                        ],
                        [
                            'user_id' => $this->formerStudent->id,
                            'price' => 9999,
                            'lesson_package_id' => $this->package->id,
                            'user' => ['name' => $this->formerStudent->name],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_gets_redirect_or_unauthorized_on_former_members_endpoints(): void
    {
        Auth::logout();

        foreach ($this->formerMembersEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_set_prices_view_gets_403_on_former_members_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->formerMembersEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без setPrices.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_authorized_user_former_members_endpoints_return_expected_status_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $page->assertOk();
        $page->assertViewIs('admin.SettingPrices.index');
        $this->assertNotSame('', trim($page->getContent()));

        $getTeam = $this->withHeaders($this->ajaxHeaders())
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

        $formerInTeam = collect($getTeam->json('usersTeam'))->firstWhere('id', $this->formerStudent->id);
        $formerInPrice = collect($getTeam->json('usersPrice'))->firstWhere('user_id', $this->formerStudent->id);
        $this->assertNotNull($formerInTeam);
        $this->assertTrue((bool) ($formerInTeam['is_former_member'] ?? false));
        $this->assertNotNull($formerInPrice);
        $this->assertTrue((bool) ($formerInPrice['is_former_member'] ?? false));
        $this->assertEquals(3267, (float) $formerInPrice['price']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->package->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'teamPrice', 'lesson_package_id', 'teamId']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Февраль 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->currentStudent->id,
                        'price' => 3200,
                        'lesson_package_id' => $this->package->id,
                        'user' => ['name' => $this->currentStudent->name],
                    ],
                    [
                        'user_id' => $this->formerStudent->id,
                        'price' => 8888,
                        'lesson_package_id' => $this->package->id,
                        'user' => ['name' => $this->formerStudent->name],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'usersPrice', 'selectedDate']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 320000,
        ]);
    }

    public function test_set_team_price_non_ajax_redirects_and_does_not_change_former_row(): void
    {
        $this->asAdmin();

        $response = $this->post(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Февраль 2026',
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'lesson_package_id' => null,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 450000,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_set_price_all_users_non_ajax_redirects_and_skips_former_row(): void
    {
        $this->asAdmin();

        $response = $this->post(route('setPriceAllUsers'), [
            'selectedDate' => 'Февраль 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->currentStudent->id,
                    'price' => 2100,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->currentStudent->name],
                ],
                [
                    'user_id' => $this->formerStudent->id,
                    'price' => 7777,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->formerStudent->name],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->currentStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 210000,
        ]);
    }

    public function test_set_price_all_teams_non_ajax_redirects_and_skips_former_row(): void
    {
        $this->asAdmin();

        $response = $this->post(route('setPriceAllTeams'), [
            'selectedDate' => 'Февраль 2026',
            'teamsData' => [
                [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
        ]);
    }

    public function test_get_team_price_foreign_partner_team_returns_404(): void
    {
        $this->asAdmin();

        $foreignPartner = Partner::factory()->create();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $foreignPartner->id,
            'deleted_at' => null,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $foreignTeam->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_manual_paid_for_former_member_requires_manage_then_rejects_membership(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $this->teamSync->syncTeamsForStudent($this->formerStudent, [(int) $otherTeam->id]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->formerStudent->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
                'mode' => 'paid',
                'comment' => 'Нет права',
            ])
            ->assertForbidden();

        $this->asSuperadmin();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->formerStudent->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
                'mode' => 'paid',
                'comment' => 'Есть право, но бывший',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Группа не найдена или ученик в ней не состоит.');
    }
}
