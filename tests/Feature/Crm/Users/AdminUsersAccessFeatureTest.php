<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
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
     * Страница списка, DataTables, настройки колонок, создание без пароля, карточка редактирования, логи — 200 для роли с users.view.
     */
    public function test_users_section_core_endpoints_return_ok_for_user_with_users_view(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->get('/admin/users')->assertOk();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'avatar' => true,
            ],
        ])->assertOk();

        $email = 'access-smoke-' . uniqid('', true) . '@example.com';

        $storeResp = $this->postJson('/admin/users', [
            'name'       => 'Доступ',
            'lastname'   => 'Проверка',
            'email'      => $email,
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'birthday'   => '2015-01-01',
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $storeResp->assertOk();
        $newId = $storeResp->json('user.id');
        $this->assertNotNull($newId);

        $this->getJson('/admin/users/' . $newId . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('logs.data.user', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
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
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->getJson('/admin/users/' . $peer->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->getJson(route('logs.data.user', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertForbidden();
    }
}
