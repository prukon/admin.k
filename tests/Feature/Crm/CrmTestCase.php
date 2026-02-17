<?php

namespace Tests\Feature\Crm;

use App\Models\Role;
use App\Models\User;
use App\Models\Partner;
use Database\Seeders\AdminRoleBasePermissionsSeeder;
use Database\Seeders\DevAdminsSeeder;
use Database\Seeders\DevPartnersSeeder;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\UserRoleBasePermissionsSeeder;
use Database\Seeders\WeekdaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class CrmTestCase extends TestCase
{
    use RefreshDatabase;

    protected Partner $partner;
    protected User $user;

    /**
     * Второй партнёр/юзер — для тестов изоляции (anti-leak).
     */
    protected Partner $foreignPartner;
    protected User $foreignUser;

    protected function setUp(): void
    {
        parent::setUp();

        // В некоторых окружениях storage/ может быть недоступен для записи тестовым процессом.
        // Переносим compiled Blade views в системный tmp (writable).
        // Важно: используем уникальную папку на каждый прогон, чтобы не упираться в чужие права на /tmp/kidscrm_compiled_views.
        $compiled = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_compiled_views_'
            . (string) Str::uuid();

        if (!is_dir($compiled)) {
            @mkdir($compiled, 0777, true);
        }
        // На случай если mkdir создал с "жёсткими" правами (umask) — пробуем расширить.
        @chmod($compiled, 0777);
        config(['view.compiled' => $compiled]);

        // 1) Референсы
        $this->seed(WeekdaysSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        // Создаём партнёра
        $this->partner = Partner::factory()->create();

        // Создаем роль
        $userRoleId = Role::where('name', 'user')->value('id');

        // Создаем юзера
        $this->user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $userRoleId,
        ]);

        // Создаём чужого партнёра
        $this->foreignPartner = Partner::factory()->create();

        // Создаём чужого юзера
        $this->foreignUser = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $userRoleId,
        ]);

        // 4) Сидим права
        $this->seed(UserRoleBasePermissionsSeeder::class);
        $this->seed(AdminRoleBasePermissionsSeeder::class);

        // 5) Авторизация
        $this->actingAs($this->user);

        // 6)current_partner в session
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    //Авторизация admin
    protected function asAdmin(): self
    {
        $adminRoleId = Role::where('name', 'admin')->value('id');

        $this->user->role_id = $adminRoleId;
        $this->user->save();

        $this->actingAs($this->user);

        return $this;
    }

    //Авторизация superadmin
    protected function asSuperadmin(): self
    {
        $adminRoleId = Role::where('name', 'superadmin')->value('id');

        $this->user->role_id = $adminRoleId;
        $this->user->save();

        $this->actingAs($this->user);

        return $this;
    }

    /**
     * Быстро переключиться на “чужого” пользователя.
     */
    protected function asForeignUser(): self
    {
        $this->actingAs($this->foreignUser);
        $this->withSession(['current_partner' => $this->foreignPartner->id]);

        return $this;
    }

    protected function roleId(string $name): int
    {
        return (int) Role::query()->where('name', $name)->firstOrFail()->id;
    }

    protected function permissionId(string $name): int
    {
        $id = DB::table('permissions')->where('name', $name)->value('id');
        $this->assertNotNull($id, "Permission '{$name}' не найден в таблице permissions");
        return (int) $id;
    }

    protected function createUserWithRole(string $roleName, ?Partner $partner = null, array $attributes = []): User
    {
        $partner ??= $this->partner;

        return User::factory()->create(array_merge([
            'partner_id' => $partner->id,
            'role_id'    => $this->roleId($roleName),
        ], $attributes));
    }

    /**
     * Создаёт пользователя текущего партнёра с ролью, у которой НЕТ указанного permission (по pivot permission_role).
     *
     * Важно: учитывает partner_id в permission_role.
     */
    protected function createUserWithoutPermission(string $permissionName, ?Partner $partner = null, array $attributes = []): User
    {
        $partner ??= $this->partner;

        $permId = $this->permissionId($permissionName);

        $role = Role::query()
            ->where('name', '!=', 'superadmin')
            ->whereNotExists(function ($q) use ($permId, $partner) {
                $q->select(DB::raw(1))
                    ->from('permission_role')
                    ->whereColumn('permission_role.role_id', 'roles.id')
                    ->where('permission_role.permission_id', $permId)
                    ->where('permission_role.partner_id', $partner->id);
            })
            ->first();

        // Если подходящей роли нет — создаём тестовую роль без прав
        if (!$role) {
            $now = now();
            $roleId = DB::table('roles')->insertGetId([
                'name'       => 'test_no_' . str_replace('.', '_', $permissionName) . '_' . Str::lower(Str::random(6)),
                'label'      => 'Test No ' . $permissionName,
                'is_sistem'  => 0,
                'order_by'   => 0,
                // is_visible появилось миграцией 2025_04_10..., поэтому на старых схемах игнорируется
                'is_visible' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $role = Role::query()->findOrFail($roleId);
        }

        $user = User::factory()->create(array_merge([
            'partner_id' => $partner->id,
            'role_id'    => $role->id,
        ], $attributes));

        return $user;
    }
}