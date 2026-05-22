<?php

namespace Tests\Feature\Crm\Teams;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Колонка «Тренер» на /admin/teams: UI, JSON data, сортировка, настройки полей списка.
 */
class TeamTrainerColumnFeatureTest extends CrmTestCase
{
    private ?int $trainerRoleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();

        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
    }

    public function test_index_shows_trainer_column_header_and_fields_toggle_with_trainers_view(): void
    {
        $this->get('/admin/teams')
            ->assertOk()
            ->assertSee('<th>Тренер</th>', false)
            ->assertSee('data-column-key="trainer_label"', false)
            ->assertSee('id="colTrainer"', false);
    }

    public function test_index_hides_trainer_column_and_fields_toggle_without_trainers_view(): void
    {
        $actor = $this->userWithOnlyGroupsView();
        $this->actingAs($actor);

        $this->get('/admin/teams')
            ->assertOk()
            ->assertDontSee('<th>Тренер</th>', false)
            ->assertDontSee('data-column-key="trainer_label"', false)
            ->assertDontSee('id="colTrainer"', false);
    }

    public function test_data_returns_trainer_label_key_for_all_rows(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Ключ trainer_label',
        ]);

        $json = $this->get('/admin/teams/data')->assertOk()->json();

        $this->assertNotEmpty($json['data']);
        foreach ($json['data'] as $row) {
            $this->assertArrayHasKey('trainer_label', $row);
        }

        $row = collect($json['data'])->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertSame('', $row['trainer_label']);
    }

    public function test_data_returns_trainer_full_name_when_team_has_trainer(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'С тренером',
        ]);

        $profile = $this->makeTrainerProfile('Пётр', 'Сидоров');

        DB::table('team_trainer')->insert([
            'partner_id'          => $this->partner->id,
            'team_id'             => $team->id,
            'trainer_profile_id'  => $profile->id,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $row = collect($this->get('/admin/teams/data')->assertOk()->json('data'))
            ->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertSame('Сидоров Пётр', $row['trainer_label']);
    }

    public function test_data_still_returns_trainer_label_in_json_for_user_without_trainers_view(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'JSON без права trainers',
        ]);

        $profile = $this->makeTrainerProfile('Анна', 'Козлова');

        DB::table('team_trainer')->insert([
            'partner_id'         => $this->partner->id,
            'team_id'            => $team->id,
            'trainer_profile_id' => $profile->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $actor = $this->userWithOnlyGroupsView();
        $this->actingAs($actor);

        $row = collect($this->get('/admin/teams/data')->assertOk()->json('data'))
            ->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('trainer_label', $row);
        $this->assertSame('Козлова Анна', $row['trainer_label']);
    }

    public function test_data_ordering_by_trainer_label_column_name_returns_200(): void
    {
        $this->createTeamWithTrainer('Группа A', 'Ааа', 'Тренер');
        $this->createTeamWithTrainer('Группа B', 'Яяя', 'Тренер');

        $query = http_build_query([
            'order'   => [['column' => 3, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'trainer_label'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]);

        $this->get('/admin/teams/data?' . $query)
            ->assertOk()
            ->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_data_ordering_by_trainer_label_sorts_by_trainer_lastname(): void
    {
        $teamEarly = $this->createTeamWithTrainer('Sort early', 'Ааа', 'Имя');
        $teamLate  = $this->createTeamWithTrainer('Sort late', 'Яяя', 'Имя');

        $queryAsc = http_build_query([
            'order'   => [['column' => 3, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'trainer_label'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]);

        $idsAsc = collect($this->get('/admin/teams/data?' . $queryAsc)->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $posEarly = array_search($teamEarly->id, $idsAsc, true);
        $posLate  = array_search($teamLate->id, $idsAsc, true);

        $this->assertNotFalse($posEarly);
        $this->assertNotFalse($posLate);
        $this->assertLessThan($posLate, $posEarly);

        $queryDesc = http_build_query([
            'order'   => [['column' => 3, 'dir' => 'desc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'trainer_label'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]);

        $idsDesc = collect($this->get('/admin/teams/data?' . $queryDesc)->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $posEarlyDesc = array_search($teamEarly->id, $idsDesc, true);
        $posLateDesc  = array_search($teamLate->id, $idsDesc, true);

        $this->assertLessThan($posEarlyDesc, $posLateDesc);
    }

    public function test_columns_settings_save_and_load_trainer_label_visibility(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'teams_index')
            ->delete();

        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'order_by'      => true,
                'title'         => true,
                'trainer_label' => false,
                'status_label'  => true,
                'actions'       => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson('/admin/teams/columns-settings')
            ->assertOk()
            ->assertExactJson([
                'order_by'      => true,
                'title'         => true,
                'trainer_label' => false,
                'status_label'  => true,
                'actions'       => true,
            ]);
    }

    public function test_columns_settings_trainer_label_forbidden_without_groups_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson('/admin/teams/columns-settings')->assertStatus(403);
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['trainer_label' => true],
        ])->assertStatus(403);
    }

    private function userWithOnlyGroupsView(): User
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return $actor;
    }

    private function makeTrainerProfile(string $name, string $lastname): TrainerProfile
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->trainerRoleId,
            'name'       => $name,
            'lastname'   => $lastname,
            'email'      => strtolower($name) . '-' . uniqid() . '@example.test',
        ]);

        return TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $user->id,
        ]);
    }

    private function createTeamWithTrainer(string $teamTitle, string $trainerLastname, string $trainerName): Team
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => $teamTitle,
        ]);

        $profile = $this->makeTrainerProfile($trainerName, $trainerLastname);

        DB::table('team_trainer')->insert([
            'partner_id'         => $this->partner->id,
            'team_id'            => $team->id,
            'trainer_profile_id' => $profile->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return $team;
    }
}
