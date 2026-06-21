<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Dashboard: pivot team_user — несколько групп, фильтр по группе.
 */
final class DashboardTeamPivotFeatureTest extends CrmTestCase
{
    public function test_get_team_details_includes_student_in_any_of_their_groups(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'is_enabled' => 1,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $responseA = $this->getJson(route('getTeamDetails', [
            'teamId' => $teamA->id,
            'teamName' => $teamA->title,
        ]))->assertOk()->json();

        $responseB = $this->getJson(route('getTeamDetails', [
            'teamId' => $teamB->id,
            'teamName' => $teamB->title,
        ]))->assertOk()->json();

        $idsA = collect($responseA['usersTeam'] ?? [])->pluck('id')->all();
        $idsB = collect($responseB['usersTeam'] ?? [])->pluck('id')->all();

        $this->assertContains($student->id, $idsA);
        $this->assertContains($student->id, $idsB);
    }

    public function test_get_user_details_returns_comma_separated_teams_label(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Dash-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Dash-B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $json = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->assertJson(['success' => true])
            ->json();

        $label = (string) ($json['userTeamsLabel'] ?? '');
        $this->assertStringContainsString('Dash-A', $label);
        $this->assertStringContainsString('Dash-B', $label);
    }
}
