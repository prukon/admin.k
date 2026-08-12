<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Консоль (/cabinet): селект «Выбор группы» фильтрует назначенные абонементы по team_id.
 *
 * UX-баг до фикса: applyDashboardTeamContext выходил по !dashboardSeasonsEnabled,
 * поэтому абонементы не фильтровались, а селект без сезонов не показывался.
 *
 * @see resources/views/dashboard.blade.php
 * @see resources/views/includes/dashboard_team_switcher.blade.php
 */
final class DashboardLessonPackagesTeamFilterFeatureTest extends StudentTeamPivotTestCase
{
    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Filter-A',
        ]);
        $this->teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Filter-B',
        ]);
    }

    public function test_guest_is_denied_on_cabinet_with_assigned_packages(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 1_000, 'Гостевой пакет', $this->teamA);

        Auth::logout();

        $response = $this->get(route('dashboard'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_user_without_dashboard_view_gets_403_on_cabinet_with_packages(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 1_000, 'Пакет без dashboard.view', $this->teamA);

        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_student_without_seasons_permission_still_gets_200_on_cabinet_with_packages(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 2_200, 'Пакет без сезонов 200', $this->teamA);

        $response = $this->actingAs($student->fresh())
            ->withSession(['current_partner' => $this->partner->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('Пакет без сезонов 200', false)
            ->assertSee('id="dashboard-active-team"', false);
    }

    public function test_cabinet_renders_packages_of_both_teams_with_team_attributes_for_client_filter(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.single.view');
        $this->createAssignment($student, 4_000, 'Пакет группы A', $this->teamA, [], [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
            'duration_days' => 1,
            'lessons_count' => 1,
        ]);
        $this->createAssignment($student, 5_500, 'Пакет группы B', $this->teamB, [], [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Пакет группы A', $html);
        $this->assertStringContainsString('Пакет группы B', $html);
        $this->assertStringContainsString('data-ulp-team-id="'.$this->teamA->id.'"', $html);
        $this->assertStringContainsString('data-ulp-team-id="'.$this->teamB->id.'"', $html);
        $this->assertStringContainsString('id="dashboard-lesson-packages"', $html);
        $this->assertStringContainsString('id="dashboard-active-team"', $html);

        $switcherPos = strpos($html, 'id="dashboard-active-team"');
        $packagesPos = strpos($html, 'id="dashboard-lesson-packages"');
        $this->assertNotFalse($switcherPos);
        $this->assertNotFalse($packagesPos);
        $this->assertLessThan($packagesPos, $switcherPos);
    }

    public function test_cabinet_keeps_package_without_team_in_markup_so_js_can_hide_it_when_switcher_is_on(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 2_000, 'С группой A', $this->teamA);
        $this->createAssignment($student, 3_000, 'Без группы', $this->teamA, [
            'team_id' => null,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('С группой A', $html);
        $this->assertStringContainsString('Без группы', $html);
        $this->assertStringContainsString('data-ulp-team-id=""', $html);
        $this->assertStringContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('cardTeam && Number(cardTeam) === Number(teamId)', $html);
    }

    public function test_zero_fee_packages_do_not_enable_team_switcher_without_seasons(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 0, 'Нулевая стоимость', $this->teamA);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = false', $html);
    }

    public function test_admin_with_users_view_sees_packages_without_student_team_switcher(): void
    {
        $this->asAdmin();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($this->user, [
            (int) $this->teamA->id,
            (int) $this->teamB->id,
        ]);
        $this->grantPermissionForUser($this->user, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($this->user, 7_700, 'Админский пакет', $this->teamA);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = false', $html);
        $this->assertStringContainsString('Админский пакет', $html);
        $this->assertStringContainsString('id="dashboard-lesson-packages"', $html);
        $this->assertStringContainsString('id="single-select-user"', $html);
    }

    public function test_get_user_details_does_not_replace_lesson_packages_block(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 1_800, 'Пакет не в AJAX', $this->teamA);

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $json = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertArrayNotHasKey('userLessonPackages', $json);
        $this->assertArrayNotHasKey('user_lesson_packages', $json);
    }

    public function test_cabinet_js_filters_packages_even_when_seasons_are_disabled(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 6_600, 'Фильтр без сезонов', $this->teamA);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = true', $html);
        $this->assertStringContainsString('filterLessonPackagesByTeam(teamId)', $html);
        $this->assertStringContainsString('if (!dashboardSeasonsEnabled)', $html);

        $applyPos = strpos($html, 'function applyDashboardTeamContext');
        $this->assertNotFalse($applyPos);
        $applyChunk = substr($html, $applyPos, 1200);
        $filterPos = strpos($applyChunk, 'filterLessonPackagesByTeam(teamId)');
        $seasonsReturnPos = strpos($applyChunk, 'if (!dashboardSeasonsEnabled)');
        $this->assertNotFalse($filterPos);
        $this->assertNotFalse($seasonsReturnPos);
        $this->assertLessThan(
            $seasonsReturnPos,
            $filterPos,
            'Фильтр абонементов должен вызываться до выхода из applyDashboardTeamContext при выключенных сезонах.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/function applyDashboardTeamContext\([^)]*\)\s*\{\s*if\s*\(\s*!dashboardSeasonsEnabled/',
            $html
        );
    }

    /**
     * P2: страница консоли с 2 группами и абонементами — не пустой HTML, селект и блок на месте.
     */
    public function test_cabinet_team_filter_page_smoke_shows_switcher_and_packages_without_blank_screen(): void
    {
        $student = $this->makeMultiTeamStudent();
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.single.view');
        $this->createAssignment($student, 8_800, 'Smoke разовое', $this->teamA, [], [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
            'duration_days' => 1,
            'lessons_count' => 1,
        ]);

        $response = $this->actingAs($student)
            ->withSession(['current_partner' => $this->partner->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('Консоль', $html);
        $this->assertStringContainsString('Выбор группы', $html);
        $this->assertStringContainsString('Smoke разовое', $html);
        $this->assertStringContainsString('filterLessonPackagesByTeam', $html);
        $this->assertStringContainsString('initDashboardTeamSwitcher', $html);
    }

    private function makeMultiTeamStudent(): User
    {
        return $this->makeStudentWithTeams([$this->teamA, $this->teamB], [
            'name'     => 'Filter',
            'lastname' => 'Student',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $packageOverrides
     */
    private function createAssignment(
        User $student,
        float $feeAmount,
        string $packageName,
        Team $team,
        array $overrides = [],
        array $packageOverrides = [],
    ): UserLessonPackage {
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create(array_merge([
            'name' => $packageName,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ], $packageOverrides));

        $lessons = (int) $package->lessons_count;

        return UserLessonPackage::query()->create(array_merge([
            'user_id'           => $student->id,
            'lesson_package_id' => $package->id,
            'team_id'           => $team->id,
            'starts_at'         => null,
            'ends_at'           => null,
            'lessons_total'     => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount_cents'  => (int) round($feeAmount * 100),
            'is_paid'           => false,
        ], $overrides));
    }

    private function revokeCabinetSeasonsPermission(User $student): void
    {
        $permId = $this->permissionId('setPrices.cabinetSeasons.view');
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $student->role_id)
            ->where('permission_id', $permId)
            ->delete();
    }

    private function cabinetHtmlFor(User $student): string
    {
        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        return is_string($content) ? $content : '';
    }
}
