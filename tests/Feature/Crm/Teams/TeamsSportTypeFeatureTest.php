<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\SportType;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TeamsSportTypeFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();
        $this->grantCorePermissions();
    }

    private function grantCorePermissions(): void
    {
        foreach (['groups.view', 'sport_types.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_teams_index_includes_sport_type_options_in_view(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Баскетбол',
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewHas('sportTypeOptions', fn ($options) => $options->contains('id', $sportType->id))
            ->assertSee('id="sport_type_id"', false)
            ->assertSee('id="filter-sport-type"', false)
            ->assertSee('Баскетбол', false);
    }

    public function test_store_team_with_sport_type_id(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Дзюдо',
        ]);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Группа дзюдо',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'sport_type_id' => $sportType->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Группа дзюдо',
            'sport_type_id' => $sportType->id,
        ]);
    }

    public function test_store_rejects_foreign_partner_sport_type(): void
    {
        $foreignSportType = SportType::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Bad sport link',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'sport_type_id' => $foreignSportType->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sport_type_id']);
    }

    public function test_store_rejects_disabled_sport_type(): void
    {
        $disabled = SportType::factory()->disabled()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Скрытый вид',
        ]);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Disabled sport team',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'sport_type_id' => $disabled->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sport_type_id']);
    }

    public function test_data_includes_sport_type_label_and_filters_by_sport_type(): void
    {
        $football = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Футбол',
        ]);
        $swim = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Плавание',
        ]);

        $footballTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Football team',
            'sport_type_id' => $football->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Swim team',
            'sport_type_id' => $swim->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'No sport team',
            'sport_type_id' => null,
        ]);

        $filtered = $this->getJson('/admin/teams/data?draw=1&start=0&length=10&sport_type_id=' . $football->id)
            ->assertOk()
            ->json();

        $this->assertSame(1, $filtered['recordsFiltered']);
        $this->assertSame($footballTeam->id, $filtered['data'][0]['id']);
        $this->assertSame('Футбол', $filtered['data'][0]['sport_type_label']);

        $noneFiltered = $this->getJson('/admin/teams/data?draw=1&start=0&length=10&sport_type_id=none')
            ->assertOk()
            ->json();

        $this->assertSame(1, $noneFiltered['recordsFiltered']);
        $this->assertSame('No sport team', $noneFiltered['data'][0]['title']);
    }

    public function test_edit_returns_sport_type_id(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'sport_type_id' => $sportType->id,
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('sport_type_id', $sportType->id);
    }

    public function test_update_team_sport_type_id(): void
    {
        $oldSport = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Old sport',
        ]);
        $newSport = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'New sport',
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'sport_type_id' => $oldSport->id,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => $team->order_by,
            'is_enabled' => (int) $team->is_enabled,
            'sport_type_id' => $newSport->id,
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'sport_type_id' => $newSport->id,
        ]);
    }

    public function test_without_sport_types_view_sport_type_id_is_not_accepted_on_store(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $response = $this->postJson(route('admin.team.store'), [
            'title' => 'Team without sport permission',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'sport_type_id' => $sportType->id,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Team without sport permission',
            'sport_type_id' => null,
        ]);
    }
}
