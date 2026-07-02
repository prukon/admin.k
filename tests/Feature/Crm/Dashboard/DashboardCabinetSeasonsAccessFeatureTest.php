<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Консоль (/cabinet): право setPrices.cabinetSeasons.view — доступ к endpoint'ам и видимость сезонов.
 *
 * Раздел read-only (GET /cabinet, /get-user-details, /get-team-details): store/update safety-net не применим.
 * Inline JS консоли — BladeInlineJsSyntaxTest (dashboard.blade.php).
 */
final class DashboardCabinetSeasonsAccessFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Cabinet-Seasons-Access',
        ]);

        $this->student = $this->makeStudentWithTeams([$this->team], [
            'name'     => 'Seasons',
            'lastname' => 'Access',
        ]);

        $this->insertUserPrice($this->student, [
            'new_month' => '2025-09-01',
            'price'     => 5_500,
            'is_paid'   => 0,
        ], $this->team);
    }

    public function test_guest_is_denied_on_all_cabinet_endpoints(): void
    {
        Auth::logout();

        foreach ($this->cabinetEndpointsPayload() as $item) {
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
            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                "Гость: {$item['method']} {$item['url']} не должен отдавать 500"
            );
            $this->assertNotSame(
                200,
                $response->getStatusCode(),
                "Гость: {$item['method']} {$item['url']} не должен получать 200"
            );
        }
    }

    public function test_user_without_dashboard_view_gets_403_on_all_cabinet_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->cabinetEndpointsPayload() as $item) {
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
                "Без dashboard.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_student_with_dashboard_view_gets_200_on_cabinet_endpoints(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->cabinetEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ($item['method'] === 'GET' && str_contains($item['url'], '/cabinet')
                    ? ['HTTP_ACCEPT' => 'text/html']
                    : ['HTTP_ACCEPT' => 'application/json'])
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С dashboard.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_cabinet_returns_200_without_seasons_when_cabinet_seasons_permission_revoked(): void
    {
        $student = $this->studentWithoutCabinetSeasonsPermission();

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
    }

    public function test_cabinet_returns_200_with_seasons_when_cabinet_seasons_permission_present(): void
    {
        $html = $this->cabinetHtmlFor($this->student);

        $this->assertStringContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $html);
        $this->assertStringContainsString('createSeasons()', $html);
    }

    public function test_set_prices_view_alone_does_not_show_seasons_on_cabinet(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.cabinetSeasons.view', $this->partner);
        $this->grantPermissionForUser($actor, 'setPrices.view');
        $this->grantPermissionForUser($actor, 'dashboard.view');
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($actor, [(int) $this->team->id]);

        $this->insertUserPrice($actor, [
            'new_month' => '2025-09-01',
            'price'     => 3_000,
            'is_paid'   => 0,
        ], $this->team);

        $html = $this->cabinetHtmlFor($actor);

        $this->assertTrue($actor->can('setPrices.view'));
        $this->assertFalse($actor->can('setPrices.cabinetSeasons.view'));
        $this->assertStringNotContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
    }

    public function test_get_user_details_json_still_returns_user_price_without_cabinet_seasons_permission(): void
    {
        $student = $this->studentWithoutCabinetSeasonsPermission();

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $json = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertIsArray($json['userPrice'] ?? null);
        $this->assertNotEmpty($json['userPrice']);
    }

    public function test_lesson_packages_remain_visible_without_cabinet_seasons_permission(): void
    {
        $student = $this->studentWithoutCabinetSeasonsPermission();
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'name' => 'Пакет без сезонов',
        ]);
        UserLessonPackage::query()->create([
            'user_id'           => $student->id,
            'lesson_package_id' => $package->id,
            'team_id'           => $this->team->id,
            'lessons_total'     => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'fee_amount'        => '4200.00',
            'is_paid'           => false,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('Пакет без сезонов', $html);
        $this->assertStringContainsString('<span class="price-value">4 200</span>', $html);
    }

    public function test_cabinet_seasons_permission_exists_in_set_prices_group(): void
    {
        $row = DB::table('permissions')
            ->join('permission_groups', 'permissions.permission_group_id', '=', 'permission_groups.id')
            ->where('permissions.name', 'setPrices.cabinetSeasons.view')
            ->select('permissions.description', 'permission_groups.slug')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('setPrices', $row->slug);
        $this->assertStringContainsString('Консоль', (string) $row->description);
    }

    public function test_new_partner_assigns_cabinet_seasons_permission_to_user_and_admin_roles(): void
    {
        $partner = Partner::factory()->create();

        foreach (['user', 'admin'] as $roleName) {
            $roleId = (int) Role::query()->where('name', $roleName)->value('id');
            $permId = $this->permissionId('setPrices.cabinetSeasons.view');

            $this->assertTrue(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} нового партнёра должна иметь setPrices.cabinetSeasons.view"
            );
        }
    }

    public function test_trainer_with_dashboard_view_gets_200_but_no_seasons_without_cabinet_seasons_permission(): void
    {
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $trainerRoleId,
            'is_enabled' => true,
        ]);

        $this->assertFalse($trainer->can('setPrices.cabinetSeasons.view'));

        $html = $this->cabinetHtmlFor($trainer);

        $this->assertStringNotContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
    }

    /**
     * @return list<array{
     *     method: string,
     *     url: string,
     *     data?: array<string, mixed>,
     *     headers?: array<string, string>
     * }>
     */
    private function cabinetEndpointsPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('dashboard'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('getUserDetails', ['userId' => $this->student->id]),
            ],
            [
                'method' => 'GET',
                'url'    => route('getTeamDetails', [
                    'teamId'   => $this->team->id,
                    'teamName' => $this->team->title,
                ]),
            ],
        ];
    }

    private function studentWithoutCabinetSeasonsPermission(): User
    {
        $permId = $this->permissionId('setPrices.cabinetSeasons.view');
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->student->role_id)
            ->where('permission_id', $permId)
            ->delete();

        return $this->student->fresh();
    }

    private function cabinetHtmlFor(User $actor): string
    {
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        return is_string($content) ? $content : '';
    }
}
