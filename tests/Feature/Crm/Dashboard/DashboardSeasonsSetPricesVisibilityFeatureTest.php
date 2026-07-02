<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Team;
use App\Models\User;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Консоль (/cabinet): блок сезонов и селект группы доступны только при setPrices.cabinetSeasons.view.
 */
final class DashboardSeasonsSetPricesVisibilityFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Seasons-Perm-Team',
        ]);
    }

    public function test_cabinet_hides_seasons_block_without_cabinet_seasons_permission(): void
    {
        $student = $this->makeStudentWithoutCabinetSeasonsPermission();
        $this->insertUserPrice($student, [
            'new_month' => '2025-09-01',
            'price'     => 4_500,
            'is_paid'   => 0,
        ], $this->team);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('class="row seasons"', $html);
        $this->assertStringNotContainsString('id="season-2026"', $html);
        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
        $this->assertStringNotContainsString('У вас образовалась задолженность', $html);
    }

    public function test_cabinet_shows_seasons_block_with_cabinet_seasons_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertUserPrice($student, [
            'new_month' => '2025-09-01',
            'price'     => 4_500,
            'is_paid'   => 0,
        ], $this->team);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('id="season-2026"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $html);
        $this->assertStringContainsString('createSeasons()', $html);
    }

    public function test_cabinet_shows_team_switcher_only_with_cabinet_seasons_permission_and_multiple_teams(): void
    {
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Seasons-Perm-Team-B',
        ]);

        $student = $this->makeStudentWithoutCabinetSeasonsPermission();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($student, [
            (int) $this->team->id,
            (int) $teamB->id,
        ]);
        $student = $student->fresh(['teams']);

        $this->insertUserPrice($student, [
            'new_month' => '2025-09-01',
            'price'     => 5_000,
            'is_paid'   => 0,
        ], $this->team);

        $htmlWithoutPermission = $this->cabinetHtmlFor($student);
        $this->assertStringNotContainsString('id="dashboard-active-team"', $htmlWithoutPermission);

        $this->grantPermissionForUser($student, 'setPrices.cabinetSeasons.view');

        $htmlWithPermission = $this->cabinetHtmlFor($student);
        $this->assertStringContainsString('id="dashboard-active-team"', $htmlWithPermission);
        $this->assertStringContainsString('Выбор группы', $htmlWithPermission);
    }

    public function test_student_role_has_cabinet_seasons_permission_by_default(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);

        $this->assertTrue($student->can('setPrices.cabinetSeasons.view'));
    }

    public function test_revoking_cabinet_seasons_permission_does_not_require_set_prices_view(): void
    {
        $student = $this->makeStudentWithoutCabinetSeasonsPermission();
        $this->assertFalse($student->can('setPrices.cabinetSeasons.view'));
        $this->assertFalse($student->can('setPrices.view'));
    }

    public function test_admin_with_cabinet_seasons_permission_sees_seasons_on_cabinet(): void
    {
        $this->asAdmin();
        $this->insertUserPrice($this->user, [
            'new_month' => '2025-10-01',
            'price'     => 6_000,
            'is_paid'   => 0,
        ], $this->team);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $html);
    }

    public function test_refresh_price_scoped_selectors_present_in_dashboard_script(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString(
            "document.querySelectorAll('.seasons .border_price .price-value')",
            $html
        );
    }

    private function makeStudentWithoutCabinetSeasonsPermission(): User
    {
        $student = $this->makeStudentWithTeams([$this->team]);

        $permId = $this->permissionId('setPrices.cabinetSeasons.view');
        \Illuminate\Support\Facades\DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $student->role_id)
            ->where('permission_id', $permId)
            ->delete();

        return $student->fresh();
    }

    private function cabinetHtmlFor(User $student): string
    {
        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        return is_string($content) ? $content : '';
    }
}
