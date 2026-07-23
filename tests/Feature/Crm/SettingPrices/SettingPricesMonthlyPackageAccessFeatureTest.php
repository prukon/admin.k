<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа вкладки «по месяцам» (абонементы групп/учеников).
 *
 * @see SettingPricesSectionFullAccessFeatureTest
 */
final class SettingPricesMonthlyPackageAccessFeatureTest extends CrmTestCase
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

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1000,
            'is_paid' => 0,
        ]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 350000,
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function monthlyPackageEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.indexMenu'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'POST',
                'url' => route('updateDate'),
                'data' => ['month' => 'Сентябрь 2024'],
            ],
            [
                'method' => 'POST',
                'url' => route('getTeamPrice'),
                'data' => [
                    'teamId' => $this->team->id,
                    'selectedDate' => 'Сентябрь 2024',
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setTeamPrice'),
                'data' => [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->package->id,
                    'selectedDate' => 'Сентябрь 2024',
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setPriceAllTeams'),
                'data' => [
                    'selectedDate' => 'Сентябрь 2024',
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
                    'selectedDate' => 'Сентябрь 2024',
                    'teamId' => $this->team->id,
                    'usersPrice' => [
                        [
                            'user_id' => $this->student->id,
                            'price' => 3500,
                            'lesson_package_id' => $this->package->id,
                            'user' => ['name' => $this->student->name],
                        ],
                    ],
                ],
            ],
            [
                'method' => 'GET',
                'url' => route('logs.data.settingPrice', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                ]),
            ],
        ];
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

    public function test_guest_gets_redirect_or_unauthorized_on_monthly_package_endpoints(): void
    {
        Auth::logout();

        foreach ($this->monthlyPackageEndpoints() as $item) {
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

    public function test_user_without_set_prices_view_gets_403_on_monthly_package_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->monthlyPackageEndpoints() as $item) {
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

    public function test_authorized_user_monthly_package_endpoints_return_expected_status_not_empty_200(): void
    {
        $this->asAdmin();

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $page->assertOk();
        $page->assertViewIs('admin.SettingPrices.index');
        $page->assertSee('setting-prices-team-package-select', false);
        $this->assertNotSame('', trim($page->getContent()));

        $this->withHeaders($this->ajaxHeaders())
            ->post(route('updateDate'), ['month' => 'Сентябрь 2024'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'month']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Сентябрь 2024',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'usersTeam',
                'usersPrice',
                'lessonPackages' => [
                    ['id', 'name', 'price'],
                ],
                'can_manage_manual_paid',
            ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->package->id,
                'selectedDate' => 'Сентябрь 2024',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'teamPrice',
                'lesson_package_id',
                'selectedDate',
                'teamId',
            ]);

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
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 3490,
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
                'lessonPackages',
            ]);

        $logs = $this->get(route('logs.data.settingPrice', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));
        $logs->assertOk();
        $this->assertNotSame('', trim($logs->getContent()));
    }

    public function test_ajax_validation_failures_return_422_with_field_errors(): void
    {
        $this->asAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Сентябрь 2024',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 'not-a-number',
                        'user' => ['name' => $this->student->name],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.price']);
    }
}
