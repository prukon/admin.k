<?php

namespace Tests\Feature\Crm\Trainers;

use App\Models\Role;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к странице тренеров и всем HTTP-эндпоинтам CRUD.
 */
final class TrainersAccessSmokeFeatureTest extends CrmTestCase
{
    private ?int $trainerRoleId = null;

    private TrainerProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'email' => 'smoke-trainer-' . uniqid() . '@example.test',
        ]);

        $this->profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
        ]);
    }

    private function grantTrainersView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('trainers.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_all_trainer_routes_return_200_with_trainers_view_permission(): void
    {
        $this->grantTrainersView();

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'trainers')
            ->assertSee('id="usersSectionTabs"', false);

        $this->getJson(route('admin.trainers.show', $this->profile->id))
            ->assertOk()
            ->assertJsonPath('id', $this->profile->id);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Новый',
            'name' => 'Тренер',
            'email' => 'new-smoke-' . uniqid() . '@example.test',
            'password' => 'password123',
            'is_enabled' => 1,
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
                'email' => 'del-smoke-' . uniqid() . '@example.test',
            ])->id,
        ]);

        $this->deleteJson(route('admin.trainers.destroy', $disposable->id))
            ->assertOk();
    }

    public function test_all_trainer_routes_return_403_without_trainers_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('trainers.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.trainers.index'))->assertStatus(403);

        $this->getJson(route('admin.trainers.show', $this->profile->id))
            ->assertStatus(403);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Запрет',
            'name' => 'Тренер',
            'email' => 'forbidden-' . uniqid() . '@example.test',
            'password' => 'password123',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->putJson(route('admin.trainers.update', $this->profile->id), [
            'lastname' => 'Запрет',
            'name' => 'Тренер',
            'email' => $this->profile->user->email,
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->deleteJson(route('admin.trainers.destroy', $this->profile->id))
            ->assertStatus(403);
    }

    public function test_admin_has_trainers_page_by_default_base_permissions(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.trainers.index'))->assertOk();
        $this->getJson(route('admin.trainers.show', $this->profile->id))->assertOk();
    }

    public function test_trainer_system_role_exists_and_is_hidden(): void
    {
        $role = Role::query()->where('name', 'trainer')->first();

        $this->assertNotNull($role);
        $this->assertSame(0, (int) $role->is_visible);
        $this->assertSame('Тренер', $role->label);
    }
}
