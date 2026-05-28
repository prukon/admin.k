<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к разделу «Пользователи»: middleware can:users.view и успешные ответы основных endpoint’ов.
 */
class AdminUsersAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
    }

    /**
     * Страница списка отдаёт данные для модалок (fields, userFieldsPayload).
     */
    public function test_users_index_view_has_field_collections_for_modals(): void
    {
        UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('modal_payload')
            ->create(['name' => 'Тест']);

        $this->get('/admin/users')
            ->assertOk()
            ->assertViewHas('fields')
            ->assertViewHas('userFieldsPayload')
            ->assertViewHas('activeTab', 'users')
            ->assertSee('Все пользователи', false);
    }

    /**
     * Страница списка, DataTables, настройки колонок, создание (в т.ч. с доп. полями), редактирование JSON, PATCH, логи — 200 для роли с users.view.
     */
    public function test_users_section_core_endpoints_return_ok_for_user_with_users_view(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $this->get('/admin/users')->assertOk();

        $dataResponse = $this->getJson('/admin/users/data?draw=1&start=0&length=10', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $dataResponse->assertOk();
        $dataResponse->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'avatar' => true,
                'name'   => true,
            ],
        ])->assertOk();

        $email = 'access-smoke-' . uniqid('', true) . '@example.com';

        $storeResp = $this->postJson('/admin/users', [
            'name'       => 'Доступ',
            'lastname'   => 'Проверка',
            'email'      => $email,
            // телефон: новый функционал (в UI вводится с маской)
            'phone'      => '+7 (999) 111-22-33',
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'birthday'   => '2015-01-01',
            'is_enabled'  => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $storeResp->assertOk();
        $newId = $storeResp->json('user.id');
        $this->assertNotNull($newId);

        $created = User::findOrFail($newId);
        $this->assertSame('+79991112233', $created->phone);
        $editResponse = $this->getJson('/admin/users/' . $newId . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $editResponse->assertOk();
        $editResponse->assertJsonStructure([
            'user',
            'currentUser' => ['role_id', 'isSuperadmin'],
            'fields',
            'roles',
        ]);
        $this->patchJson('/admin/users/' . $newId, [
            'name'     => $created->name,
            'lastname' => $created->lastname,
        ])->assertOk();

        $this->getJson(route('logs.data.user', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        // Создание с доп. полем + PATCH — тот же флоу, что в UI
        $adminRoleId = $this->roleId('admin');
        $cf = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('access_smoke_cf')
            ->create(['name' => 'Smoke CF']);

        DB::table('user_field_role')->insert([
            'user_field_id' => $cf->id,
            'role_id'       => $adminRoleId,
        ]);

        $emailCf = 'access-smoke-cf-' . uniqid('', true) . '@example.com';

        $storeWithCustom = $this->postJson('/admin/users', [
            'name'       => 'С',
            'lastname'   => 'Кастом',
            'email'      => $emailCf,
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'custom'     => [
                'access_smoke_cf' => 'hello',
            ],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $storeWithCustom->assertOk();
        $cfUserId = $storeWithCustom->json('user.id');
        $this->assertNotNull($cfUserId);
        $this->assertNotNull(
            UserFieldValue::where('user_id', $cfUserId)->where('field_id', $cf->id)->first()
        );

        $u = User::findOrFail($cfUserId);
        $this->patchJson('/admin/users/' . $cfUserId, [
            'name'     => $u->name,
            'lastname' => $u->lastname,
            'custom'   => [
                'access_smoke_cf' => 'updated',
            ],
        ])->assertOk();
    }

    /**
     * Без права users.view все те же endpoint’ы закрыты (403).
     */
    public function test_users_section_core_endpoints_return_forbidden_without_users_view(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);
        session(['current_partner' => $this->partner->id]);

        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $peer = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->get('/admin/users')->assertForbidden();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();

        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['avatar' => true],
        ])->assertForbidden();

        $this->postJson('/admin/users', [
            'name'       => 'Нет',
            'lastname'   => 'Права',
            'email'      => 'denied-users-view@example.com',
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'custom'     => [
                'any' => 'x',
            ],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->getJson('/admin/users/' . $peer->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->patchJson('/admin/users/' . $peer->id, [
            'name'     => $peer->name ?? 'Н',
            'lastname' => $peer->lastname ?? 'Т',
            'custom'   => ['note' => 'x'],
        ])->assertForbidden();

        $this->getJson(route('logs.data.user', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertForbidden();
    }

    /**
     * Страница и API пользователей доступны с users.view без locations.view (локация в UI не обязательна).
     */
    public function test_users_section_core_endpoints_return_ok_with_users_view_only_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->get('/admin/users')
            ->assertOk()
            ->assertDontSee('id="filter-location"', false)
            ->assertDontSee('id="create-location"', false);

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();

        $storeResp = $this->postJson('/admin/users', [
            'name'       => 'Без',
            'lastname'   => 'Локации',
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $newId = $storeResp->json('user.id');
        $this->assertNotNull($newId);

        $this->getJson('/admin/users/' . $newId . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
        $this->patchJson('/admin/users/' . $newId, [
            'name'     => 'Без',
            'lastname' => 'Локации',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['name' => true],
        ])->assertOk();
    }
}
