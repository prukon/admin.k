<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Создание пользователя с телефоном (POST /admin/users):
 * - нормализация masked-ввода в +7XXXXXXXXXX
 * - валидация формата
 * - контроль прав users.phone.update (без права — phone игнорируется)
 */
class AdminUsersPhoneOnCreateFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
    }

    public function test_store_accepts_masked_ru_phone_and_persists_canonical_when_actor_has_permission(): void
    {
        $this->assertTrue(\Gate::forUser($this->user)->allows('users.phone.update'));

        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $resp = $this->postJson('/admin/users', [
            'name'       => 'Иван',
            'lastname'   => 'С телефоном',
            'email'      => 'phone-create-' . uniqid('', true) . '@example.com',
            'phone'      => '+7 (999) 111-22-33',
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $id = $resp->json('user.id');
        $this->assertNotNull($id);

        $created = User::findOrFail($id);
        $this->assertSame('+79991112233', $created->phone);
    }

    public function test_store_invalid_phone_is_normalized_to_null_when_actor_has_permission(): void
    {
        $this->assertTrue(\Gate::forUser($this->user)->allows('users.phone.update'));

        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $resp = $this->postJson('/admin/users', [
            'name'       => 'Иван',
            'lastname'   => 'Невалидный',
            'email'      => 'phone-invalid-' . uniqid('', true) . '@example.com',
            'phone'      => '+7 (123)', // не приведётся к +7XXXXXXXXXX
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // Поведение как в UpdateRequest: невалидный ввод приводится к null (без 422)
        $resp->assertOk();
        $id = $resp->json('user.id');
        $this->assertNotNull($id);

        $created = User::findOrFail($id);
        $this->assertNull($created->phone);
    }

    public function test_store_ignores_phone_when_actor_has_no_users_phone_update_permission(): void
    {
        // Убираем permission users.phone.update у текущего актора для партнёра
        $permId = $this->permissionId('users.phone.update');
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $permId)
            ->delete();

        $this->assertFalse(\Gate::forUser($this->user)->allows('users.phone.update'));

        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $resp = $this->postJson('/admin/users', [
            'name'       => 'Иван',
            'lastname'   => 'Без права',
            'email'      => 'phone-denied-' . uniqid('', true) . '@example.com',
            // отправляем телефон в том же виде, что UI
            'phone'      => '+7 (999) 111-22-33',
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // Важно: телефон НЕ должен заваливать валидацию — он игнорируется
        $resp->assertOk();
        $id = $resp->json('user.id');
        $this->assertNotNull($id);

        $created = User::findOrFail($id);
        $this->assertNull($created->phone);
    }
}

