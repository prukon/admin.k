<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Team;
use App\Models\User;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Консоль (/cabinet): селект «Выбор группы» при 2+ группах ученика (client-side фильтр сезонов).
 *
 * @see resources/views/includes/dashboard_team_switcher.blade.php
 * @see resources/views/dashboard.blade.php
 */
final class DashboardTeamSwitcherFeatureTest extends StudentTeamPivotTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
    }

    public function test_cabinet_shows_team_switcher_when_student_has_two_or_more_teams(): void
    {
        [$student, $teamA, $teamB] = $this->makeMultiTeamStudentWithPrices();

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $response = $this->get(route('dashboard'))->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertStringContainsString('Выбор группы', $html);
        $this->assertStringContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('data-multi-team="1"', $html);
        $this->assertStringContainsString('value="' . $teamA->id . '"', $html);
        $this->assertStringContainsString('value="' . $teamB->id . '"', $html);
        $this->assertStringContainsString((string) $teamA->title, $html);
        $this->assertStringContainsString((string) $teamB->title, $html);
        $this->assertStringContainsString('var userPriceAll', $html);
        $this->assertStringContainsString('var dashboardTeams', $html);
        $this->assertStringContainsString('dashboardTeamStorageKey', $html);
        $this->assertStringContainsString("'dashboard_active_team_id_' + dashboardStudentId", $html);
        $this->assertStringContainsString('var dashboardStudentId = ' . $student->id, $html);
        $this->assertStringContainsString('"team_id":' . $teamA->id, $html);
        $this->assertStringContainsString('"team_id":' . $teamB->id, $html);
    }

    public function test_cabinet_hides_team_switcher_when_student_has_single_team(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Single-Team',
        ]);

        $student = $this->makeStudentWithTeams([$team], [
            'name'     => 'One',
            'lastname' => 'Group',
        ]);

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringNotContainsString('Выбор группы', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/class="group-value"\s+data-multi-team="1"/',
            $html
        );
        $this->assertStringContainsString('Single-Team', $html);
    }

    public function test_cabinet_hides_team_switcher_for_admin_with_users_view_even_with_multiple_teams(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Admin-A',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Admin-B',
        ]);

        $this->asAdmin();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($this->user, [
            (int) $teamA->id,
            (int) $teamB->id,
        ]);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringNotContainsString('Выбор группы', $html);
        $this->assertStringContainsString('id="single-select-user"', $html);
        $this->assertStringContainsString('id="single-select-team"', $html);
    }

    public function test_cabinet_group_header_lists_all_teams_for_multi_team_student(): void
    {
        [$student, $teamA, $teamB] = $this->makeMultiTeamStudentWithPrices();

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('class="dashboard-group-name"', $html);
        $this->assertStringContainsString('data-team-id="' . $teamA->id . '"', $html);
        $this->assertStringContainsString('data-team-id="' . $teamB->id . '"', $html);
        $this->assertStringContainsString((string) $teamA->title, $html);
        $this->assertStringContainsString((string) $teamB->title, $html);
        $this->assertStringContainsString('initDashboardTeamSwitcher', $html);
        $this->assertStringContainsString('updateDashboardGroupLabel', $html);
    }

    public function test_cabinet_includes_partial_before_seasons_block(): void
    {
        [$student] = $this->makeMultiTeamStudentWithPrices();

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $switcherPos = strpos($html, 'id="dashboard-active-team"');
        $seasonsPos = strpos($html, 'class="row seasons"');

        $this->assertNotFalse($switcherPos);
        $this->assertNotFalse($seasonsPos);
        $this->assertLessThan($seasonsPos, $switcherPos);
    }

    public function test_cabinet_team_switcher_is_client_side_only_without_backend_route(): void
    {
        $routes = app('router')->getRoutes();
        $matched = collect($routes)->filter(function ($route) {
            $uri = $route->uri();

            return str_contains($uri, 'dashboard-active-team')
                || str_contains($uri, 'cabinet/active-team')
                || str_contains($uri, 'cabinet/team');
        });

        $this->assertCount(
            0,
            $matched,
            'Селект группы на консоли не должен добавлять отдельный backend-маршрут (фильтрация в JS + sessionStorage).'
        );
    }

    /**
     * @return array{0: User, 1: Team, 2: Team}
     */
    private function makeMultiTeamStudentWithPrices(): array
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Switcher-A',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Switcher-B',
        ]);

        $student = $this->makeStudentWithTeams([$teamA, $teamB], [
            'name'     => 'Multi',
            'lastname' => 'Switcher',
        ]);

        $this->grantPermissionForUser($student, 'setPrices.cabinetSeasons.view');

        $this->insertUserPrice($student, [
            'new_month' => '2025-09-01',
            'price'     => 5000,
            'is_paid'   => 0,
        ], $teamA);

        $this->insertUserPrice($student, [
            'new_month' => '2025-09-01',
            'price'     => 7000,
            'is_paid'   => 0,
        ], $teamB);

        return [$student, $teamA, $teamB];
    }
}
