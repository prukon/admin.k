<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * P1: AJAX/non-AJAX контракты sync users_prices → ULP (месячный ends_at) + контроль доступа.
 *
 * @see SettingPricesUsersPriceUlpSyncFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesUsersPriceUlpSyncContractsFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $fixed;

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
            'title' => 'Группа ULP contracts',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Contracts',
            'lastname' => 'Student',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->fixed = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'name' => 'Fixed contracts',
            'price_cents' => 800000,
            'is_active' => true,
        ]);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-10-01',
            'price' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
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
     * @return array{user_id:int,price:float,lesson_package_id:int|null,user:array{name:string}}
     */
    private function userPricePayload(float $price, ?int $packageId): array
    {
        return [
            'user_id' => (int) $this->student->id,
            'price' => $price,
            'lesson_package_id' => $packageId,
            'user' => ['name' => (string) $this->student->name],
        ];
    }

    public function test_set_price_all_users_ajax_creates_ulp_with_billing_month_end(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Октябрь 2025',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->userPricePayload(8000.0, (int) $this->fixed->id),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'usersPrice', 'selectedDate', 'lessonPackages']);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-10-01')
            ->firstOrFail();

        $this->assertNotNull($row->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame('2025-10-01', $ulp->billing_month?->format('Y-m-d'));
        $this->assertNull($ulp->starts_at);
        $this->assertSame('2025-10-31', $ulp->ends_at?->format('Y-m-d'));
        $this->assertSame(8000.0, (float) $ulp->fee_amount);
    }

    public function test_set_price_all_users_non_ajax_redirects_and_creates_ulp_not_empty_200(): void
    {
        $response = $this->post(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->userPricePayload(7500.0, (int) $this->fixed->id),
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-10-01')
            ->firstOrFail();

        $this->assertSame(7500.0, (float) $row->price);
        $this->assertNotNull($row->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame('2025-10-31', $ulp->ends_at?->format('Y-m-d'));
        $this->assertNull($ulp->starts_at);
    }

    public function test_set_team_price_non_ajax_redirects_and_creates_ulp_for_student(): void
    {
        $response = $this->post(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->fixed->id,
            'selectedDate' => 'Октябрь 2025',
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-10-01')
            ->firstOrFail();

        $this->assertSame((int) $this->fixed->id, (int) $row->lesson_package_id);
        $this->assertNotNull($row->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame('2025-10-01', $ulp->billing_month?->format('Y-m-d'));
        $this->assertSame('2025-10-31', $ulp->ends_at?->format('Y-m-d'));
    }

    public function test_set_price_all_users_ajax_package_change_blocked_when_placed_returns_422_field_errors(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Октябрь 2025',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->userPricePayload(8000.0, (int) $this->fixed->id),
                ],
            ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-10-01')
            ->firstOrFail();
        $ulpId = (int) $row->user_lesson_package_id;
        UserLessonPackage::query()->whereKey($ulpId)->update([
            'starts_at' => '2025-10-06',
            'ends_at' => '2025-10-31',
        ]);

        $other = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(8, 90)->create([
            'name' => 'Fixed B contracts',
            'price_cents' => 1200000,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Октябрь 2025',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->userPricePayload(12000.0, (int) $other->id),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $errors = $response->json('errors') ?? [];
        $fieldErrors = $errors['usersPrice.0.lesson_package_id'] ?? [];
        $this->assertNotEmpty($fieldErrors);
        $this->assertStringContainsString('разложен', (string) $fieldErrors[0]);

        $row->refresh();
        $this->assertSame((int) $this->fixed->id, (int) $row->lesson_package_id);
        $this->assertSame($ulpId, (int) $row->user_lesson_package_id);
    }

    public function test_set_price_all_users_non_ajax_package_change_blocked_when_placed_redirects_with_errors(): void
    {
        $this->post(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->userPricePayload(8000.0, (int) $this->fixed->id),
            ],
        ])->assertRedirect();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-10-01')
            ->firstOrFail();
        UserLessonPackage::query()->whereKey($row->user_lesson_package_id)->update([
            'starts_at' => '2025-10-06',
            'ends_at' => '2025-10-31',
        ]);

        $other = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(8, 90)->create([
            'name' => 'Fixed C contracts',
            'price_cents' => 1100000,
            'is_active' => true,
        ]);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => 'Октябрь 2025',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->userPricePayload(11000.0, (int) $other->id),
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame((int) $this->fixed->id, (int) $row->fresh()->lesson_package_id);
    }

    public function test_save_user_year_prices_non_ajax_redirects_and_creates_ulp_with_month_end(): void
    {
        $response = $this->post(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2025,
            'prices' => [
                [
                    'new_month' => '2025-10-01',
                    'price' => 8200,
                    'lesson_package_id' => $this->fixed->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2025-10-01')
            ->firstOrFail();
        $this->assertNotNull($row->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame('2025-10-31', $ulp->ends_at?->format('Y-m-d'));
        $this->assertNull($ulp->starts_at);
        $this->assertSame(8200.0, (float) $ulp->fee_amount);
    }

    public function test_guest_ulp_sync_endpoints_denied_not_500(): void
    {
        Auth::logout();

        $endpoints = [
            [
                'method' => 'POST',
                'url' => route('setPriceAllUsers'),
                'data' => [
                    'selectedDate' => 'Октябрь 2025',
                    'teamId' => $this->team->id,
                    'usersPrice' => [$this->userPricePayload(8000.0, (int) $this->fixed->id)],
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setTeamPrice'),
                'data' => [
                    'selectedDate' => 'Октябрь 2025',
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->fixed->id,
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setting-prices.user-year-prices.save'),
                'data' => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'year' => 2025,
                    'prices' => [
                        [
                            'new_month' => '2025-10-01',
                            'price' => 8000,
                            'lesson_package_id' => $this->fixed->id,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($endpoints as $item) {
            $web = $this->call($item['method'], $item['url'], $item['data']);
            $this->assertContains(
                $web->getStatusCode(),
                [302, 401, 403, 419],
                "Гость web: {$item['url']} → {$web->getStatusCode()}"
            );
            $this->assertNotSame(500, $web->getStatusCode());

            $json = $this->call(
                $item['method'],
                $item['url'],
                $item['data'],
                [],
                [],
                [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-REQUESTED-WITH' => 'XMLHttpRequest',
                ]
            );
            $this->assertContains(
                $json->getStatusCode(),
                [401, 403, 419],
                "Гость JSON: {$item['url']} → {$json->getStatusCode()}"
            );
            $this->assertNotSame(500, $json->getStatusCode());
        }

        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $this->student->id)->count());
    }

    public function test_without_set_prices_view_ulp_sync_endpoints_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $endpoints = [
            [
                'url' => route('setPriceAllUsers'),
                'data' => [
                    'selectedDate' => 'Октябрь 2025',
                    'teamId' => $this->team->id,
                    'usersPrice' => [$this->userPricePayload(8000.0, (int) $this->fixed->id)],
                ],
            ],
            [
                'url' => route('setTeamPrice'),
                'data' => [
                    'selectedDate' => 'Октябрь 2025',
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->fixed->id,
                ],
            ],
            [
                'url' => route('setting-prices.user-year-prices.save'),
                'data' => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'year' => 2025,
                    'prices' => [
                        [
                            'new_month' => '2025-10-01',
                            'price' => 8000,
                            'lesson_package_id' => $this->fixed->id,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($endpoints as $item) {
            $this->post($item['url'], $item['data'])->assertForbidden();
            $this->withHeaders($this->ajaxHeaders())
                ->postJson($item['url'], $item['data'])
                ->assertForbidden();
        }

        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $this->student->id)->count());
    }

    public function test_authorized_admin_page_and_ulp_sync_endpoints_not_empty_200(): void
    {
        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Октябрь 2025',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->userPricePayload(8000.0, (int) $this->fixed->id),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThan(
            0,
            UserLessonPackage::query()->where('user_id', $this->student->id)->count()
        );
    }
}
