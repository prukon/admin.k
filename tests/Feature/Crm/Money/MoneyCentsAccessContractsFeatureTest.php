<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Money;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserPrice;
use App\Models\Weekday;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * P1: доступ + AJAX/non-AJAX контракты для money/*_cents контуров
 * (установка цен с копейками, доп. платежи, month_price группы).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see docs/documentation/money.html
 */
final class MoneyCentsAccessContractsFeatureTest extends CrmTestCase
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
            'title' => 'Группа Money access',
            'deleted_at' => null,
            'month_price_cents' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'Доступ',
            'name' => 'Копейки',
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function moneyEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.indexMenu'),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.users'),
            ],
            [
                'method' => 'POST',
                'url' => route('getTeamPrice'),
                'data' => [
                    'selectedDate' => 'Ноябрь 2025',
                    'teamId' => $this->team->id,
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setPriceAllUsers'),
                'data' => [
                    'selectedDate' => 'Ноябрь 2025',
                    'teamId' => $this->team->id,
                    'usersPrice' => [[
                        'user_id' => $this->student->id,
                        'price' => 100.50,
                        'lesson_package_id' => null,
                        'user' => ['name' => $this->student->name],
                    ]],
                ],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.team.index'),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.team.store'),
                'data' => [
                    'title' => 'Группа guest money',
                    'default_duration_minutes' => 60,
                    'month_price' => '99.50',
                    'order_by' => 1,
                    'is_enabled' => 1,
                    'weekdays' => Weekday::query()->limit(1)->pluck('id')->all(),
                ],
            ],
        ];
    }

    public function test_guest_cannot_access_money_endpoints(): void
    {
        Auth::logout();

        foreach ($this->moneyEndpoints() as $item) {
            $method = strtolower($item['method']);
            $response = $this->{$method}($item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame('', trim((string) $response->getContent()), 'Пустой 200 недопустим');
            }
        }
    }

    public function test_user_without_set_prices_view_gets_403_on_setting_prices_money_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach ([
            ['GET', route('admin.settingPrices.indexMenu')],
            ['GET', route('admin.settingPrices.users')],
            ['POST', route('getTeamPrice'), [
                'selectedDate' => 'Ноябрь 2025',
                'teamId' => $this->team->id,
            ]],
            ['POST', route('setPriceAllUsers'), [
                'selectedDate' => 'Ноябрь 2025',
                'teamId' => $this->team->id,
                'usersPrice' => [[
                    'user_id' => $this->student->id,
                    'price' => 50.25,
                    'lesson_package_id' => null,
                    'user' => ['name' => 'x'],
                ]],
            ]],
        ] as $item) {
            $method = strtolower($item[0]);
            $response = $this->{$method}($item[1], $item[2] ?? []);
            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без setPrices.view: {$item[0]} {$item[1]} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_groups_view_gets_403_on_team_month_price_store(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Без groups.view',
            'default_duration_minutes' => 60,
            'month_price' => '150.50',
            'order_by' => 1,
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(403);

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Без groups.view',
        ]);
    }

    public function test_authorized_admin_set_price_all_users_ajax_with_kopecks_returns_json(): void
    {
        $this->asAdmin();

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 0,
            'is_paid' => false,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [[
                'user_id' => $this->student->id,
                'price' => 100.05,
                'lesson_package_id' => null,
                'user' => ['name' => $this->student->name],
            ]],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 10005,
        ]);
    }

    public function test_set_price_all_users_non_ajax_redirects_and_persists_kopecks(): void
    {
        $this->asAdmin();

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-12-01',
            'price_cents' => 0,
            'is_paid' => false,
        ]);

        $response = $this->post(route('setPriceAllUsers'), [
            'selectedDate' => 'Декабрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [[
                'user_id' => $this->student->id,
                'price' => 200.50,
                'lesson_package_id' => null,
                'user' => ['name' => $this->student->name],
            ]],
        ]);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            'non-AJAX setPriceAllUsers должен быть 302, не пустой 200. Got: '.$response->getStatusCode()
        );
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-12-01',
            'price_cents' => 20050,
        ]);
    }

    public function test_custom_payment_store_ajax_with_kopecks_json_contract(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'setPrices.customPayments.view');

        $this->postJson(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount' => '99.99',
            'note' => 'AJAX копейки',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'custom_payment' => ['id', 'amount', 'note'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.amount', 99.99);

        $this->assertDatabaseHas('user_custom_payment', [
            'user_id' => $this->student->id,
            'amount_cents' => 9999,
            'note' => 'AJAX копейки',
        ]);
    }

    public function test_custom_payment_store_non_ajax_returns_json_and_persists_not_empty_200(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'setPrices.customPayments.view');

        // Доп. платежи отвечают JSON даже без X-Requested-With (не redirect) — не пустой 200.
        $response = $this->post(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount' => '55.55',
            'note' => 'non-AJAX копейки',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.amount', 55.55);

        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('user_custom_payment', [
            'user_id' => $this->student->id,
            'amount_cents' => 5555,
            'note' => 'non-AJAX копейки',
        ]);
    }

    public function test_custom_payment_store_without_permission_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount' => '10.00',
            'note' => 'no perm',
        ], $this->ajaxHeaders())
            ->assertStatus(403);

        $this->assertDatabaseMissing('user_custom_payment', [
            'note' => 'no perm',
        ]);
    }

    public function test_team_store_ajax_with_month_price_kopecks(): void
    {
        $this->asAdmin();

        $this->postJson(route('admin.team.store'), [
            'title' => 'Группа 150.25',
            'default_duration_minutes' => 60,
            'month_price' => '150.25',
            'order_by' => 5,
            'is_enabled' => 1,
            'weekdays' => Weekday::query()->limit(1)->pluck('id')->all(),
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonFragment(['message' => 'Группа создана успешно']);

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Группа 150.25',
            'month_price_cents' => 15025,
        ]);
    }

    public function test_team_store_non_ajax_redirects_and_saves_month_price_cents(): void
    {
        $this->asAdmin();

        $response = $this->post(route('admin.team.store'), [
            'title' => 'Группа non-ajax money',
            'default_duration_minutes' => 45,
            'month_price' => '88.88',
            'order_by' => 8,
            'is_enabled' => 1,
            'weekdays' => Weekday::query()->limit(1)->pluck('id')->all(),
        ]);

        $response->assertStatus(302);
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Группа non-ajax money',
            'month_price_cents' => 8888,
        ]);
    }

    public function test_team_update_ajax_month_price_kopecks_and_edit_returns_rubles(): void
    {
        $this->asAdmin();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Update money',
            'month_price_cents' => 10000,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => 'Update money',
            'default_duration_minutes' => 60,
            'month_price' => '333.33',
            'order_by' => 1,
            'is_enabled' => 1,
            'weekdays' => Weekday::query()->limit(1)->pluck('id')->all(),
        ], $this->ajaxHeaders())
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'month_price_cents' => 33333,
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('month_price', 333.33);
    }

    public function test_lesson_package_store_ajax_price_with_kopecks(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'lessonPackages.view');
        $this->grantPermission($this->user, 'setPrices.packageAssignments.view');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Пакет 12.34',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price' => '12.34',
            'freeze_enabled' => 0,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Пакет 12.34',
            'price_cents' => 1234,
        ]);
    }

    public function test_set_price_all_users_validation_error_returns_422_json_not_500(): void
    {
        $this->asAdmin();

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'невалидная-дата',
            'teamId' => $this->team->id,
            'usersPrice' => [],
        ], $this->ajaxHeaders())
            ->assertStatus(422);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [[
                'user_id' => $this->student->id,
                'price' => -1,
                'lesson_package_id' => null,
                'user' => ['name' => 'x'],
            ]],
        ], $this->ajaxHeaders())
            ->assertStatus(422);
    }
}
