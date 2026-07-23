<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Установка цен»: контроль доступа и smoke всех endpoint'ов раздела.
 */
final class SettingPricesSectionFullAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $lessonPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $this->team->id,
            'is_enabled' => true,
        ]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        UserPrice::query()->create([
            'user_id'   => $this->student->id,
            'team_id'   => $this->team->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);

        $this->lessonPackage = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 100000,
        ]);
    }

    public function test_guest_cannot_access_any_section_endpoint(): void
    {
        Auth::logout();

        foreach ($this->coreSectionRoutesPayload() as $item) {
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

    public function test_user_without_set_prices_view_gets_403_on_core_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->coreSectionRoutesPayload() as $item) {
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
        }
    }

    public function test_user_with_set_prices_view_core_endpoints_return_expected_status(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $this->assertCoreSectionEndpointsSucceed();
    }

    public function test_superadmin_core_endpoints_return_expected_status(): void
    {
        $this->asSuperadmin();
        $this->assertCoreSectionEndpointsSucceed();
    }

    public function test_manual_paid_requires_manual_paid_manage_permission(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id'      => $this->student->id,
            'team_id'      => $this->team->id,
            'selectedDate' => 'Сентябрь 2024',
            'mode'         => 'paid',
            'comment'      => 'Нет права manualPaid',
        ])->assertForbidden();

        $this->get(route('admin.settingPrices.users'))->assertOk();
    }

    public function test_superadmin_manual_paid_returns_json_contract(): void
    {
        $this->asSuperadmin();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id'      => $this->student->id,
                'team_id'      => $this->team->id,
                'selectedDate' => 'Сентябрь 2024',
                'mode'         => 'paid',
                'comment'      => 'Smoke manual paid',
            ])
            ->assertOk()
            ->assertJsonStructure(['success', 'user_price'])
            ->assertJsonPath('success', true);
    }

    public function test_custom_payments_endpoints_require_custom_payments_view(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        foreach ($this->customPaymentsRoutesPayload() as $item) {
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
                "Без setPrices.customPayments.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_custom_payments_view_endpoints_return_expected_status(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->grantPermission($actor, 'setPrices.customPayments.view');
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.customPayments'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index')
            ->assertViewHas('activeTab', 'custom_payments');

        $this->get(route('admin.settingPrices.customPayments.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('admin.settingPrices.customPayments.users-search', ['q' => 'Smoke']))
            ->assertOk();

        $this->getJson(route('admin.settingPrices.customPayments.teams-for-user', [
            'user_id' => $this->student->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.customPayments.store'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'amount'  => 199,
                'note'    => 'Smoke store',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function assertCoreSectionEndpointsSucceed(): void
    {
        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index');

        $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index')
            ->assertViewHas('activeTab', 'users');

        $this->withHeaders($this->ajaxHeaders())
            ->post(route('updateDate'), ['month' => 'Сентябрь 2024'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId'       => $this->team->id,
                'selectedDate' => 'Сентябрь 2024',
            ])
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'usersTeam',
                'usersPrice',
                'can_manage_manual_paid',
            ]);

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 150000,
        ]);
        $packageBulk = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 160000,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId'            => $this->team->id,
                'lesson_package_id' => $package->id,
                'selectedDate'      => 'Сентябрь 2024',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllTeams'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamsData'    => [
                    ['teamId' => $this->team->id, 'lesson_package_id' => $packageBulk->id],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Сентябрь 2024',
                'teamId'       => $this->team->id,
                'usersPrice'   => [
                    [
                        'user_id' => $this->student->id,
                        'price'   => 1700,
                        'user'    => ['name' => $this->student->name],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->get(route('logs.data.settingPrice', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year'    => 2024,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'can_manage_manual_paid',
                'user',
                'year',
                'months',
            ])
            ->assertJsonPath('success', true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year'    => 2024,
                'prices'  => [
                    ['new_month' => '2024-03-01', 'price' => 550],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function coreSectionRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.settingPrices.indexMenu'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.settingPrices.users'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'POST',
                'url'    => route('updateDate'),
                'data'   => ['month' => 'Сентябрь 2024'],
            ],
            [
                'method' => 'POST',
                'url'    => route('getTeamPrice'),
                'data'   => [
                    'teamId'       => $this->team->id,
                    'selectedDate' => 'Сентябрь 2024',
                ],
            ],
            [
                'method' => 'POST',
                'url'    => route('setTeamPrice'),
                'data'   => [
                    'teamId'            => $this->team->id,
                    'lesson_package_id' => $this->lessonPackage->id,
                    'selectedDate'      => 'Сентябрь 2024',
                ],
            ],
            [
                'method' => 'POST',
                'url'    => route('setPriceAllTeams'),
                'data'   => [
                    'selectedDate' => 'Сентябрь 2024',
                    'teamsData'    => [
                        [
                            'teamId'            => $this->team->id,
                            'lesson_package_id' => $this->lessonPackage->id,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'POST',
                'url'    => route('setPriceAllUsers'),
                'data'   => [
                    'selectedDate' => 'Сентябрь 2024',
                    'teamId'       => $this->team->id,
                    'usersPrice'   => [
                        [
                            'user_id' => $this->student->id,
                            'price'   => 1000,
                            'user'    => ['name' => $this->student->name],
                        ],
                    ],
                ],
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.settingPrice', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'POST',
                'url'    => route('setting-prices.user-year-prices'),
                'data'   => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'year'    => 2024,
                ],
            ],
            [
                'method' => 'POST',
                'url'    => route('setting-prices.user-year-prices.save'),
                'data'   => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'year'    => 2024,
                    'prices'  => [
                        ['new_month' => '2024-03-01', 'price' => 500],
                    ],
                ],
            ],
            [
                'method' => 'POST',
                'url'    => route('setting-prices.manual-paid'),
                'data'   => [
                    'user_id'      => $this->student->id,
                    'team_id'      => $this->team->id,
                    'selectedDate' => 'Сентябрь 2024',
                    'mode'         => 'paid',
                    'comment'      => 'Access matrix',
                ],
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function customPaymentsRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.settingPrices.customPayments'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.settingPrices.customPayments.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.settingPrices.customPayments.users-search', ['q' => 'Test']),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.settingPrices.customPayments.teams-for-user', [
                    'user_id' => $this->student->id,
                ]),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.settingPrices.customPayments.store'),
                'data'   => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'amount'  => 500,
                    'note'    => 'Smoke',
                ],
            ],
        ];
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
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
