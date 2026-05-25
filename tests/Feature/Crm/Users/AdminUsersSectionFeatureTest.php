<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Пользователи»: вкладки (Все пользователи / Тренеры), сайдбар, доступ к страницам и endpoint’ам.
 */
final class AdminUsersSectionFeatureTest extends CrmTestCase
{
    private ?int $trainerRoleId = null;

    private TrainerProfile $trainerProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $trainerUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->trainerRoleId,
            'email'      => 'section-tabs-trainer-' . uniqid('', true) . '@example.test',
        ]);

        $this->trainerProfile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $trainerUser->id,
        ]);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantUsersView(User $actor): void
    {
        $this->grantPermission($actor, 'users.view');
    }

    private function grantTrainersView(User $actor): void
    {
        $this->grantPermission($actor, 'trainers.view');
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->firstOrFail()->id;
    }

    // --- UI вкладок и сайдбар ---

    public function test_users_index_renders_section_tabs_with_users_tab_active(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantTrainersView($this->user);

        $usersUrl = route('admin.user1');

        $this->get($usersUrl)
            ->assertOk()
            ->assertViewHas('activeTab', 'users')
            ->assertSee('id="usersSectionTabs"', false)
            ->assertSee('>Все пользователи</a>', false)
            ->assertSee('href="' . $usersUrl . '"', false)
            ->assertSee('nav-link active', false)
            ->assertSee('role="tab">Тренеры</a>', false);
    }

    public function test_trainers_index_renders_section_tabs_with_trainers_tab_active(): void
    {
        $this->asAdmin();
        $this->grantTrainersView($this->user);

        $usersUrl = route('admin.user1');
        $trainersUrl = route('admin.trainers.index');

        $this->get($trainersUrl)
            ->assertOk()
            ->assertViewHas('activeTab', 'trainers')
            ->assertSee('id="usersSectionTabs"', false)
            ->assertSee('>Все пользователи</a>', false)
            ->assertSee('href="' . $usersUrl . '"', false)
            ->assertSee('href="' . $trainersUrl . '"', false)
            ->assertSee('role="tab">Тренеры</a>', false)
            ->assertSee('nav-link active', false);
    }

    public function test_users_index_shows_trainers_tab_with_trainers_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);
        $this->grantTrainersView($actor);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('role="tab">Тренеры</a>', false)
            ->assertSee('href="' . route('admin.trainers.index') . '"', false);
    }

    public function test_users_index_hides_trainers_tab_without_trainers_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('activeTab', 'users')
            ->assertSee('id="usersSectionTabs"', false)
            ->assertSee('>Все пользователи</a>', false)
            ->assertDontSee('role="tab">Тренеры</a>', false)
            ->assertDontSee('href="' . route('admin.trainers.index') . '"', false);
    }

    public function test_both_pages_show_shared_section_heading(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantTrainersView($this->user);

        $heading = '>Пользователи</h4>';

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee($heading, false);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertSee($heading, false);
    }

    public function test_sidebar_has_users_menu_without_separate_trainers_item(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantTrainersView($this->user);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('href="/admin/users"', false)
            ->assertSee('<p>Пользователи', false)
            ->assertDontSee('<p>Тренеры</p>', false)
            ->assertDontSee('fa-person-running', false);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertSee('href="/admin/users"', false)
            ->assertDontSee('<p>Тренеры</p>', false);
    }

    // --- Доступ: страницы и endpoint’ы ---

    public function test_guest_cannot_access_users_or_trainers_pages(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertStatus(302);
        $this->get(route('admin.trainers.index'))->assertStatus(302);
    }

    public function test_users_page_and_endpoints_return_ok_with_users_view_only(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $roleId = $this->studentRoleId();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('activeTab', 'users');

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['name' => true],
        ])->assertOk();

        $this->getJson(route('logs.data.user', ['draw' => 1]))->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Раздел',
            'lastname'   => 'Пользователи',
            'email'      => 'section-users-' . uniqid('', true) . '@example.test',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $this->getJson(route('admin.user.edit', $userId), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->patchJson(route('admin.user.update', $userId), [
            'name'     => 'Раздел',
            'lastname' => 'Обновлён',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->get(route('admin.trainers.index'))->assertForbidden();
        $this->getJson(route('admin.trainers.show', $this->trainerProfile->id))->assertForbidden();
    }

    public function test_trainers_page_and_endpoints_return_ok_with_trainers_view_only(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);
        $this->grantTrainersView($actor);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'trainers')
            ->assertSee('trainerCreateModal', false);

        $this->getJson(route('admin.trainers.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.trainers.columns-settings.get'))->assertOk();
        $this->postJson(route('admin.trainers.columns-settings.save'), [
            'columns' => ['full_name' => true, 'email' => true],
        ])->assertOk();

        $this->getJson(route('admin.trainers.show', $this->trainerProfile->id))
            ->assertOk()
            ->assertJsonPath('id', $this->trainerProfile->id);

        $this->postJson(route('admin.trainers.store'), [
            'lastname'   => 'Новый',
            'name'       => 'Тренер',
            'email'      => 'section-new-trainer-' . uniqid('', true) . '@example.test',
            'password'   => 'password123',
            'is_enabled' => 1,
        ])->assertOk();

        $this->putJson(route('admin.trainers.update', $this->trainerProfile->id), [
            'lastname'   => 'Обновлён',
            'name'       => 'Тренер',
            'email'      => $this->trainerProfile->user->email,
            'is_enabled' => 1,
        ])->assertOk();

        $disposable = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => User::factory()->create([
                'partner_id' => $this->partner->id,
                'role_id'    => $this->trainerRoleId,
                'email'      => 'section-del-trainer-' . uniqid('', true) . '@example.test',
            ])->id,
        ]);

        $this->deleteJson(route('admin.trainers.destroy', $disposable->id))->assertOk();

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson('/admin/users/data?draw=1')->assertForbidden();
    }

    public function test_combined_section_pages_and_all_endpoints_return_ok_for_admin_with_both_permissions(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantTrainersView($this->user);

        $roleId = $this->studentRoleId();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('activeTab', 'users')
            ->assertSee('role="tab">Тренеры</a>', false);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'trainers');

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['name' => true, 'email' => true],
        ])->assertOk();
        $this->getJson(route('logs.data.user', ['draw' => 1]))->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Комбо',
            'lastname'   => 'Доступ',
            'email'      => 'section-combo-' . uniqid('', true) . '@example.test',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $userId = (int) $store->json('user.id');
        $this->getJson(route('admin.user.edit', $userId))->assertOk();
        $this->patchJson(route('admin.user.update', $userId), [
            'name'     => 'Комбо',
            'lastname' => 'Патч',
        ])->assertOk();

        $this->getJson(route('admin.trainers.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('admin.trainers.columns-settings.get'))->assertOk();
        $this->postJson(route('admin.trainers.columns-settings.save'), [
            'columns' => ['full_name' => true],
        ])->assertOk();

        $this->getJson(route('admin.trainers.show', $this->trainerProfile->id))
            ->assertOk()
            ->assertJsonPath('id', $this->trainerProfile->id);

        $this->postJson(route('admin.trainers.store'), [
            'lastname'   => 'Комбо',
            'name'       => 'Тренер',
            'email'      => 'section-combo-trainer-' . uniqid('', true) . '@example.test',
            'password'   => 'password123',
            'is_enabled' => 1,
        ])->assertOk();

        $this->putJson(route('admin.trainers.update', $this->trainerProfile->id), [
            'lastname'   => 'Комбо',
            'name'       => 'Тренер',
            'email'      => $this->trainerProfile->user->email,
            'is_enabled' => 1,
        ])->assertOk();

        $disposable = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => User::factory()->create([
                'partner_id' => $this->partner->id,
                'role_id'    => $this->trainerRoleId,
                'email'      => 'section-combo-del-' . uniqid('', true) . '@example.test',
            ])->id,
        ]);

        $this->deleteJson(route('admin.trainers.destroy', $disposable->id))->assertOk();
    }

    public function test_users_section_endpoints_return_forbidden_without_users_view(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $peer = User::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = $this->studentRoleId();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson('/admin/users/data?draw=1')->assertForbidden();
        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['name' => true],
        ])->assertForbidden();
        $this->postJson(route('admin.user.store'), [
            'name'       => 'Нет',
            'lastname'   => 'Права',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ])->assertForbidden();
        $this->getJson(route('admin.user.edit', $peer->id))->assertForbidden();
        $this->patchJson(route('admin.user.update', $peer->id), [
            'name'     => 'Н',
            'lastname' => 'Т',
        ])->assertForbidden();
        $this->getJson(route('logs.data.user', ['draw' => 1]))->assertForbidden();
    }

    public function test_trainers_section_endpoints_return_forbidden_without_trainers_view(): void
    {
        $denied = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.trainers.index'))->assertForbidden();
        $this->getJson(route('admin.trainers.data', ['draw' => 1]))->assertForbidden();
        $this->getJson(route('admin.trainers.columns-settings.get'))->assertForbidden();
        $this->postJson(route('admin.trainers.columns-settings.save'), [
            'columns' => ['full_name' => true],
        ])->assertForbidden();
        $this->getJson(route('admin.trainers.show', $this->trainerProfile->id))->assertForbidden();
        $this->postJson(route('admin.trainers.store'), [
            'lastname'   => 'Запрет',
            'name'       => 'Тренер',
            'email'      => 'section-forbidden-' . uniqid('', true) . '@example.test',
            'password'   => 'password123',
            'is_enabled' => 1,
        ])->assertForbidden();
        $this->putJson(route('admin.trainers.update', $this->trainerProfile->id), [
            'lastname'   => 'Запрет',
            'name'       => 'Тренер',
            'email'      => $this->trainerProfile->user->email,
            'is_enabled' => 1,
        ])->assertForbidden();
        $this->deleteJson(route('admin.trainers.destroy', $this->trainerProfile->id))->assertForbidden();
    }
}
