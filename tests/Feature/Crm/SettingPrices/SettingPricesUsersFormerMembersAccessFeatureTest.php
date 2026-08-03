<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

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
 * на вкладке «по ученикам».
 *
 * @see SettingPricesUsersFormerMembersFeatureTest
 * @see SettingPricesUsersTeamFeatureTest
 */
final class SettingPricesUsersFormerMembersAccessFeatureTest extends CrmTestCase
{
    private Team $almaz;

    private Team $dubl;

    private User $student;

    private TeamUserSyncService $teamSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->teamSync = app(TeamUserSyncService::class);

        $this->almaz = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз Users Access',
        ]);
        $this->dubl = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Дубль Users Access',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->almaz->id,
            'is_enabled' => true,
            'lastname' => 'История',
            'name' => 'Ученик',
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [
            (int) $this->almaz->id,
            (int) $this->dubl->id,
        ]);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
            'is_paid' => 0,
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->dubl->id]);
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
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function formerMembersEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.users'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'POST',
                'url' => route('setting-prices.user-year-prices'),
                'data' => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->almaz->id,
                    'year' => 2026,
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setting-prices.user-year-prices.save'),
                'data' => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->almaz->id,
                    'year' => 2026,
                    'prices' => [
                        [
                            'new_month' => '2026-02-01',
                            'price' => 9999,
                            'lesson_package_id' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_guest_gets_redirect_or_unauthorized_on_users_former_endpoints(): void
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

    public function test_user_without_set_prices_view_gets_403_on_users_former_endpoints(): void
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

    public function test_authorized_user_users_former_endpoints_return_expected_status_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $page = $this->get(route('admin.settingPrices.users'));
        $page->assertOk();
        $page->assertViewIs('admin.SettingPrices.index');
        $page->assertViewHas('activeTab', 'users');
        $html = $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('data-user-id="' . $this->student->id . '"', $html);
        $this->assertMatchesRegularExpression(
            '/data-user-id="' . $this->student->id . '"[^>]*data-former-team-ids="[^"]*' . $this->almaz->id . '/',
            $html
        );

        $yearResponse = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->almaz->id,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_former_member', true)
            ->assertJsonPath('can_manage_manual_paid', false)
            ->assertJsonStructure([
                'success',
                'is_former_member',
                'can_manage_manual_paid',
                'user' => ['id', 'name', 'lastname', 'team_id', 'team_name'],
                'year',
                'months',
                'lessonPackages',
            ]);

        $february = collect($yearResponse->json('months'))->firstWhere('new_month', '2026-02-01');
        $this->assertNotNull($february);
        $this->assertEquals(3267, (float) $february['price']);

        // Save для бывшего — 422, не пустой 200 и не 500.
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->almaz->id,
                'year' => 2026,
                'prices' => [
                    [
                        'new_month' => '2026-02-01',
                        'price' => 9999,
                        'lesson_package_id' => null,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Группа не найдена или ученик в ней не состоит.');

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
        ]);
    }

    public function test_save_user_year_prices_non_ajax_rejects_former_and_does_not_change_db(): void
    {
        $this->asAdmin();

        $response = $this->post(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'year' => 2026,
            'prices' => [
                [
                    'new_month' => '2026-02-01',
                    'price' => 5555,
                    'lesson_package_id' => null,
                ],
            ],
        ]);

        // Контроллер отвечает JSON 422 до redirect-helper — не пустой 200.
        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertJsonPath('message', 'Группа не найдена или ученик в ней не состоит.');

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->almaz->id,
            'new_month' => '2026-02-01',
            'price' => 3267,
        ]);
    }

    public function test_user_year_prices_ajax_for_current_membership_is_not_former(): void
    {
        $this->asAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->dubl->id,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_former_member', false);
    }

    public function test_user_year_prices_foreign_partner_student_returns_404(): void
    {
        $this->asAdmin();

        $foreignPartner = Partner::factory()->create();
        $foreignStudent = User::factory()->create([
            'partner_id' => $foreignPartner->id,
            'is_enabled' => true,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $foreignStudent->id,
                'team_id' => $this->almaz->id,
                'year' => 2026,
            ])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_manual_paid_former_member_access_matrix(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->almaz->id,
                'selectedDate' => 'Февраль 2026',
                'mode' => 'paid',
                'comment' => 'Нет права manualPaid',
            ])
            ->assertForbidden();

        $this->asSuperadmin();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->almaz->id,
                'selectedDate' => 'Февраль 2026',
                'mode' => 'paid',
                'comment' => 'Бывший участник',
            ])
            ->assertStatus(422);
    }
}
