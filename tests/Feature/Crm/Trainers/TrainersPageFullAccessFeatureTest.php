<?php

namespace Tests\Feature\Crm\Trainers;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к странице /admin/trainers и всем связанным эндпоинтам (trainers.view → 200, без права → 403).
 */
final class TrainersPageFullAccessFeatureTest extends CrmTestCase
{
    private ?int $trainerRoleId = null;

    private TrainerProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();

        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'lastname' => 'Full access',
            'name' => 'Layout',
            'email' => 'full-access-trainer-' . uniqid('', true) . '@example.test',
        ]);

        $this->profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
            'sort_order' => 3,
            'default_base_salary' => 15000,
            'default_rate_per_training' => 2500,
        ]);
    }

    public function test_trainers_index_page_returns_200_with_trainers_view(): void
    {
        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'trainers')
            ->assertViewHas('teamOptions')
            ->assertSee('id="trainers-table"', false)
            ->assertSee('trainerCreateModal', false)
            ->assertSee('trainersReportFiltersCollapse', false)
            ->assertSee('trainersColumnsDropdown', false);
    }

    public function test_all_trainers_page_endpoints_return_200_for_user_with_trainers_view(): void
    {
        $this->get(route('admin.trainers.index'))->assertOk();

        $this->getJson(route('admin.trainers.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.trainers.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.trainers.columns-settings.save'), [
            'columns' => [
                'avatar' => true,
                'full_name' => true,
                'teams_label' => true,
                'email' => true,
                'default_base_salary' => true,
                'default_rate_per_training' => true,
                'sort_order' => true,
                'is_enabled' => true,
                'actions' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.trainers.show', $this->profile->id))
            ->assertOk()
            ->assertJsonPath('id', $this->profile->id);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Новый',
            'name' => 'Тренер',
            'email' => 'full-access-new-' . uniqid('', true) . '@example.test',
            'password' => 'password123',
        ])->assertOk();

        $this->putJson(route('admin.trainers.update', $this->profile->id), [
            'lastname' => 'Обновлён',
            'name' => 'Тренер',
            'email' => $this->profile->user->email,
            'is_enabled' => 1,
        ])->assertOk();

        $disposable = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => User::factory()->create([
                'partner_id' => $this->partner->id,
                'role_id' => $this->trainerRoleId,
                'email' => 'full-access-del-' . uniqid('', true) . '@example.test',
            ])->id,
        ]);

        $this->deleteJson(route('admin.trainers.destroy', $disposable->id))
            ->assertOk();
    }

    public function test_data_endpoint_with_full_column_layout_returns_200(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Full access layout',
        ]);

        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'trainer_profile_id' => $this->profile->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = http_build_query([
            'order' => [['column' => 2, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'avatar_url'],
                ['name' => 'full_name'],
                ['name' => 'teams_label'],
                ['name' => 'email'],
                ['name' => 'default_base_salary'],
                ['name' => 'default_rate_per_training'],
                ['name' => 'sort_order'],
                ['name' => 'is_enabled'],
                ['name' => 'actions'],
            ],
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
            'name' => 'Full access',
        ]);

        $json = $this->get('/admin/trainers/data?' . $query)
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $row = collect($json['data'])->firstWhere('id', $this->profile->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('avatar_url', $row);
        $this->assertArrayHasKey('teams_label', $row);
        $this->assertArrayHasKey('default_base_salary', $row);
        $this->assertArrayHasKey('status_label', $row);
    }

    public function test_trainers_index_returns_403_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.trainers.index'))->assertStatus(403);
    }

    public function test_trainers_data_returns_403_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->get('/admin/trainers/data?draw=1')->assertStatus(403);
    }

    public function test_columns_settings_get_and_post_return_403_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('admin.trainers.columns-settings.get'))->assertStatus(403);

        $this->postJson(route('admin.trainers.columns-settings.save'), [
            'columns' => ['full_name' => true],
        ])->assertStatus(403);
    }

    public function test_trainer_show_returns_403_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('admin.trainers.show', $this->profile->id))->assertStatus(403);
    }

    public function test_trainer_store_returns_403_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Forbidden',
            'name' => 'Trainer',
            'email' => 'forbidden-store-' . uniqid('', true) . '@example.test',
            'password' => 'password123',
        ])->assertStatus(403);
    }

    public function test_trainer_update_returns_403_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->putJson(route('admin.trainers.update', $this->profile->id), [
            'lastname' => 'Forbidden',
            'name' => 'Trainer',
            'email' => $this->profile->user->email,
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_trainer_destroy_returns_403_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->deleteJson(route('admin.trainers.destroy', $this->profile->id))->assertStatus(403);
    }

    public function test_guest_cannot_access_trainers_page_or_endpoints(): void
    {
        Auth::logout();

        $this->get(route('admin.trainers.index'))->assertStatus(302);
        $this->get('/admin/trainers/data?draw=1')->assertStatus(302);
        $this->get(route('admin.trainers.columns-settings.get'))->assertStatus(302);
    }
}
