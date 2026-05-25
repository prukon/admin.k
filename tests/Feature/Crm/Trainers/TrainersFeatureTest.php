<?php

namespace Tests\Feature\Crm\Trainers;

use App\Models\Role;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TrainersFeatureTest extends CrmTestCase
{
    private ?int $trainerRoleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTrainerProfile(array $userOverrides = [], array $profileOverrides = []): TrainerProfile
    {
        $user = User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'team_id' => null,
        ], $userOverrides));

        return TrainerProfile::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
        ], $profileOverrides));
    }

    public function test_index_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('trainers.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.trainers.index'))->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('trainers.view');

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'trainers')
            ->assertSee('Все пользователи', false)
            ->assertSee('Тренеры', false);
    }

    public function test_index_ok_for_admin_by_default_base_permissions(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.trainers.index'))
            ->assertOk();
    }

    public function test_store_creates_user_with_trainer_role_and_profile(): void
    {
        $this->grantPermission('trainers.view');

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'email' => 'trainer-' . uniqid() . '@example.test',
            'password' => 'password123',
            'description' => 'Опытный тренер',
            'is_enabled' => 1,
            'sort_order' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'lastname' => 'Иванов',
            'name' => 'Иван',
        ]);

        $userId = (int) DB::table('users')
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Иванов')
            ->value('id');

        $this->assertDatabaseHas('trainer_profiles', [
            'partner_id' => $this->partner->id,
            'user_id' => $userId,
            'description' => 'Опытный тренер',
            'sort_order' => 5,
        ]);
    }

    public function test_store_forbidden_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('trainers.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'email' => 'x@example.test',
            'password' => 'password123',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_show_returns_404_for_foreign_partner_trainer(): void
    {
        $this->grantPermission('trainers.view');

        $foreign = TrainerProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => User::factory()->create([
                'partner_id' => $this->foreignPartner->id,
                'role_id' => $this->trainerRoleId,
            ])->id,
        ]);

        $this->getJson(route('admin.trainers.show', $foreign->id))
            ->assertStatus(404);
    }

    public function test_show_returns_200_for_own_trainer(): void
    {
        $this->grantPermission('trainers.view');

        $profile = $this->createTrainerProfile(
            ['lastname' => 'Петров', 'name' => 'Пётр', 'email' => 'petrov@example.test'],
        );

        $this->getJson(route('admin.trainers.show', $profile->id))
            ->assertOk()
            ->assertJsonPath('id', $profile->id)
            ->assertJsonPath('full_name', 'Петров Пётр')
            ->assertJsonPath('email', 'petrov@example.test');
    }

    public function test_update_returns_200_and_updates_user_and_profile(): void
    {
        $this->grantPermission('trainers.view');

        $profile = $this->createTrainerProfile(
            ['name' => 'Старый', 'email' => 'old@example.test'],
            ['description' => 'Было'],
        );

        $this->putJson(route('admin.trainers.update', $profile->id), [
            'lastname' => 'Новиков',
            'name' => 'Новый',
            'email' => 'new@example.test',
            'description' => 'Стало',
            'is_enabled' => 0,
            'sort_order' => 10,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $profile->user_id,
            'lastname' => 'Новиков',
            'name' => 'Новый',
            'email' => 'new@example.test',
            'is_enabled' => 0,
        ]);

        $this->assertDatabaseHas('trainer_profiles', [
            'id' => $profile->id,
            'description' => 'Стало',
            'is_enabled' => 0,
            'sort_order' => 10,
        ]);
    }

    public function test_destroy_soft_deletes_profile_and_user(): void
    {
        $this->grantPermission('trainers.view');

        $profile = $this->createTrainerProfile();
        $userId = $profile->user_id;

        $this->deleteJson(route('admin.trainers.destroy', $profile->id))
            ->assertOk();

        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_store_validation_errors_return_422(): void
    {
        $this->grantPermission('trainers.view');

        $this->postJson(route('admin.trainers.store'), [
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'lastname']);
    }

    public function test_store_without_email_and_password(): void
    {
        $this->grantPermission('trainers.view');

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Без',
            'name' => 'Почты',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'lastname' => 'Без',
            'name' => 'Почты',
            'email' => null,
        ]);
    }

    public function test_superadmin_can_update_trainer_via_form_request(): void
    {
        $profile = $this->createTrainerProfile(
            ['name' => 'Трен', 'email' => 'super-' . uniqid() . '@example.test'],
            ['description' => 'До'],
        );

        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->putJson(route('admin.trainers.update', $profile->id), [
            'lastname' => 'Тренеров',
            'name' => 'Трен',
            'email' => $profile->user->email,
            'description' => 'После',
            'is_enabled' => 1,
            'sort_order' => 2,
        ])->assertOk();

        $this->assertDatabaseHas('trainer_profiles', [
            'id' => $profile->id,
            'description' => 'После',
        ]);
    }

    public function test_update_via_form_data_post_with_method_spoof(): void
    {
        $this->grantPermission('trainers.view');

        $profile = $this->createTrainerProfile(
            ['name' => 'Иван', 'email' => 'form-' . uniqid() . '@example.test'],
            ['description' => 'Было'],
        );

        $this->post(route('admin.trainers.update', $profile->id), [
            '_method' => 'PUT',
            'lastname' => 'Петров',
            'name' => 'Пётр',
            'email' => $profile->user->email,
            'description' => 'Стало через форму',
            'is_enabled' => 1,
            'sort_order' => 3,
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->assertOk();

        $this->assertDatabaseHas('trainer_profiles', [
            'id' => $profile->id,
            'description' => 'Стало через форму',
            'sort_order' => 3,
        ]);
    }

    public function test_update_works_when_user_role_is_not_trainer_but_profile_exists(): void
    {
        $this->grantPermission('trainers.view');

        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');

        $profile = $this->createTrainerProfile(
            ['role_id' => $adminRoleId, 'email' => 'admin-trainer-' . uniqid() . '@example.test'],
            ['description' => 'Профиль есть'],
        );

        $this->putJson(route('admin.trainers.update', $profile->id), [
            'lastname' => 'Сидоров',
            'name' => 'Сидор',
            'email' => $profile->user->email,
            'description' => 'Обновлено',
            'is_enabled' => 1,
            'sort_order' => 1,
        ])->assertOk();

        $profile->user->refresh();

        $this->assertSame($this->trainerRoleId, (int) $profile->user->role_id);
        $this->assertDatabaseHas('trainer_profiles', [
            'id' => $profile->id,
            'description' => 'Обновлено',
        ]);
    }

    public function test_user_role_changed_to_trainer_appears_on_trainers_page(): void
    {
        $this->grantPermission('trainers.view');
        $this->grantPermission('users.view');
        $this->grantPermission('users.role.update');

        $staff = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
            'email' => 'staff-trainer-sync-' . uniqid() . '@example.test',
            'team_id' => null,
        ]);

        $this->assertDatabaseMissing('trainer_profiles', [
            'user_id' => $staff->id,
        ]);

        $this->patchJson(route('admin.user.update', $staff->id), [
            'name' => $staff->name,
            'lastname' => $staff->lastname,
            'email' => $staff->email,
            'role_id' => $this->trainerRoleId,
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $staff->id,
            'partner_id' => $this->partner->id,
        ]);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertSee($staff->email, false);
    }
}
