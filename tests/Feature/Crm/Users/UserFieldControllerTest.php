<?php

namespace Tests\Feature\Crm\Users;

use App\Models\MyLog;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Crm\CrmTestCase;
use Illuminate\Support\Facades\DB;

class UserFieldControllerTest extends CrmTestCase
{
    /**
     * Хелпер: супер-админ текущего партнёра.
     */
    protected function createSuperAdminForCurrentPartner(): User
    {
        $roleId = Role::where('name', 'superadmin')->firstOrFail()->id;

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
        ]);
    }

    /**
     * Хелпер: пользователь без права users.view.
     * Если вдруг у выбранной роли есть это право, тест упадёт — это будет сигналом, что сидеры/права настроены криво.
     */

    protected function createUserWithoutUsersViewPermission(): User
    {
        $permId = DB::table('permissions')->where('name', 'users.view')->value('id');

        // Если в базе вообще нет такого permission — это тоже проблема сидеров,
        // но тогда "users.view" (Gate) нигде не работает.
        $this->assertNotNull($permId, "Permission 'users.view' не найден в таблице permissions");

        // Ищем роль, у которой НЕТ users.view
        $roleWithoutView = Role::query()
            ->where('name', '!=', 'superadmin')
            ->whereNotExists(function ($q) use ($permId) {
                $q->select(DB::raw(1))
                    ->from('permission_role')
                    ->whereColumn('permission_role.role_id', 'roles.id')
                    ->where('permission_role.permission_id', $permId);
            })
            ->first();

        // Если такой роли нет — создаём тестовую роль без прав
        if (!$roleWithoutView) {
            $roleWithoutView = Role::create([
                'name'       => 'test_no_users_view',
                'label'      => 'Test No Users View',
                'is_sistem'  => 0,
                'is_visible' => 0,
            ]);
        }

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleWithoutView->id,
        ]);

        $this->assertFalse(
            Gate::forUser($user)->allows('users.view'),
            "Роль '{$roleWithoutView->name}' неожиданно имеет право 'users.view'"
        );

        return $user;
    }

    /** @test */
    public function store_fields_forbidden_without_users_view_permission(): void
    {
        $user = $this->createUserWithoutUsersViewPermission();
        $this->actingAs($user);

        $payload = [
            'fields' => [
                [
                    'id'         => null,
                    'name'       => 'Рост',
                    'field_type' => 'string',
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(403);

        $this->assertSame(0, UserField::count(), 'Не должно создаваться полей при 403');
        $this->assertSame(0, MyLog::count(), 'Не должно создаваться логов при 403');
    }

    /** @test */
    public function store_fields_creates_single_field_without_roles(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $payload = [
            'fields' => [
                [
                    'id'         => null,
                    'name'       => 'Рост',
                    'field_type' => 'string',
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Поля успешно сохранены',
            ]);

        $this->assertSame(1, UserField::count());

        /** @var UserField $field */
        $field = UserField::first();

        $this->assertEquals('Рост', $field->name);
        $this->assertEquals('string', $field->field_type);
        $this->assertEquals($this->partner->id, $field->partner_id);

        // slug должен быть непустым и содержать id партнёра (конкретный формат slug не фиксируем жёстко)
        $this->assertNotEmpty($field->slug);
        $this->assertStringContainsString((string) $this->partner->id, $field->slug);

        $this->assertCount(0, $field->roles, 'У поля не должно быть ролей, если мы их не передавали');

        $this->assertDatabaseHas('my_logs', [
            'type'        => 2,
            'action'      => 210,
            'target_type' => UserField::class,
            'target_id'   => $field->id,
        ]);
    }

    /** @test */
    public function store_fields_creates_multiple_fields_with_roles(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $role1 = Role::query()->firstOrFail();
        $role2 = Role::query()
            ->where('id', '!=', $role1->id)
            ->first() ?? $role1;

        $payload = [
            'fields' => [
                [
                    'id'         => null,
                    'name'       => 'Рост',
                    'field_type' => 'string',
                    'roles'      => [$role1->id],
                ],
                [
                    'id'         => null,
                    'name'       => 'Вес',
                    'field_type' => 'text',
                    'roles'      => [$role1->id, $role2->id],
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);

        $this->assertSame(2, UserField::count());

        $fields = UserField::orderBy('id')->get();

        $this->assertEqualsCanonicalizing(
            [$role1->id],
            $fields[0]->roles()->pluck('roles.id')->all()
        );

        $this->assertEqualsCanonicalizing(
            array_values(array_unique([$role1->id, $role2->id])),
            $fields[1]->roles()->pluck('roles.id')->all()
        );

        $this->assertEquals(2, MyLog::count());

        MyLog::all()->each(function (MyLog $log) {
            $this->assertEquals(2, $log->type);
            $this->assertEquals(210, $log->action);
            $this->assertEquals(UserField::class, $log->target_type);
            $this->assertStringContainsString('Создано поле', (string) $log->description);
        });
    }

    /** @test */
    public function store_fields_updates_existing_field_name_and_type_without_changing_roles(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $role = Role::query()->firstOrFail();

        $field = UserField::create([
            'name'       => 'Рост',
            'slug'       => 'rost-' . $this->partner->id,
            'field_type' => 'string',
            'partner_id' => $this->partner->id,
        ]);

        $field->roles()->sync([$role->id]);

        $oldSlug = $field->slug;

        $payload = [
            'fields' => [
                [
                    'id'         => $field->id,
                    'name'       => 'Рост ребёнка',
                    'field_type' => 'text',
                    'roles'      => [$role->id],
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);

        $field->refresh();

        $this->assertEquals('Рост ребёнка', $field->name);
        $this->assertEquals('text', $field->field_type);
        $this->assertNotEquals($oldSlug, $field->slug);

        $this->assertEqualsCanonicalizing(
            [$role->id],
            $field->roles()->pluck('roles.id')->all()
        );

        $log = MyLog::where('target_id', $field->id)
            ->where('target_type', UserField::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Название:', (string) $log->description);
        $this->assertStringContainsString('Тип:', (string) $log->description);
        $this->assertStringNotContainsString('Роли:', (string) $log->description);
    }

    /** @test */
    public function store_fields_updates_only_roles_of_existing_field(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $role1 = Role::query()->firstOrFail();
        $role2 = Role::query()
            ->where('id', '!=', $role1->id)
            ->first() ?? $role1;

        $field = UserField::create([
            'name'       => 'Рост',
            'slug'       => 'rost-' . $this->partner->id,
            'field_type' => 'string',
            'partner_id' => $this->partner->id,
        ]);

        $field->roles()->sync([$role1->id]);

        $payload = [
            'fields' => [
                [
                    'id'         => $field->id,
                    'name'       => 'Рост',      // без изменений
                    'field_type' => 'string',    // без изменений
                    'roles'      => [$role1->id, $role2->id],
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);

        $field->refresh();

        $this->assertEqualsCanonicalizing(
            array_values(array_unique([$role1->id, $role2->id])),
            $field->roles()->pluck('roles.id')->all()
        );

        $this->assertEquals('Рост', $field->name);
        $this->assertEquals('string', $field->field_type);

        $log = MyLog::where('target_id', $field->id)
            ->where('target_type', UserField::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Роли:', (string) $log->description);
    }

    /** @test */
    public function store_fields_deletes_fields_missing_from_payload_for_current_partner(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $fieldA = UserField::create([
            'name'       => 'A',
            'slug'       => 'a-' . $this->partner->id,
            'field_type' => 'string',
            'partner_id' => $this->partner->id,
        ]);

        $fieldB = UserField::create([
            'name'       => 'B',
            'slug'       => 'b-' . $this->partner->id,
            'field_type' => 'string',
            'partner_id' => $this->partner->id,
        ]);

        $fieldC = UserField::create([
            'name'       => 'C',
            'slug'       => 'c-' . $this->partner->id,
            'field_type' => 'string',
            'partner_id' => $this->partner->id,
        ]);

        $payload = [
            'fields' => [
                [
                    'id'         => $fieldB->id,
                    'name'       => $fieldB->name,
                    'field_type' => $fieldB->field_type,
                    'roles'      => [],
                ],
                [
                    'id'         => $fieldC->id,
                    'name'       => $fieldC->name,
                    'field_type' => $fieldC->field_type,
                    'roles'      => [],
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('user_fields', ['id' => $fieldA->id]);
        $this->assertDatabaseHas('user_fields', ['id' => $fieldB->id]);
        $this->assertDatabaseHas('user_fields', ['id' => $fieldC->id]);

        $deleteLogs = MyLog::where('target_type', UserField::class)
            ->where('type', 2)
            ->where('action', 210)
            ->where('target_id', $fieldA->id)
            ->get();

        $this->assertCount(1, $deleteLogs);
        $this->assertStringContainsString('Удалено поле', (string) $deleteLogs->first()->description);
    }

    /** @test */
    public function store_fields_cannot_update_field_of_another_partner(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $otherPartner = Partner::factory()->create();

        $foreignField = UserField::create([
            'name'       => 'Чужое поле',
            'slug'       => 'foreign-' . $otherPartner->id,
            'field_type' => 'string',
            'partner_id' => $otherPartner->id,
        ]);

        $payload = [
            'fields' => [
                [
                    'id'         => $foreignField->id,
                    'name'       => 'Попытка изменить',
                    'field_type' => 'text',
                    'roles'      => [],
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // findOrFail по partner_id должен выбросить ModelNotFound, что превратится в 404
        $response->assertStatus(404);

        $foreignField->refresh();
        $this->assertEquals('Чужое поле', $foreignField->name);
        $this->assertEquals('string', $foreignField->field_type);

        $this->assertSame(0, MyLog::count(), 'При неуспешной попытке изменений логов быть не должно');
    }

    /** @test */
    public function store_fields_validation_requires_fields_key(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $response = $this->postJson(route('admin.field.store'), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);

        $this->assertSame(0, UserField::count());
        $this->assertSame(0, MyLog::count());
    }

    /** @test */
    public function store_fields_validation_rejects_invalid_field_type(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $payload = [
            'fields' => [
                [
                    'id'         => null,
                    'name'       => 'Рост',
                    'field_type' => 'number', // не из string|text|select
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0.field_type']);

        $this->assertSame(0, UserField::count());
        $this->assertSame(0, MyLog::count());
    }

    /** @test */
    public function store_fields_validation_rejects_nonexistent_role_id(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $invalidRoleId = 999999;

        $payload = [
            'fields' => [
                [
                    'id'         => null,
                    'name'       => 'Рост',
                    'field_type' => 'string',
                    'roles'      => [$invalidRoleId],
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0.roles.0']);

        $this->assertSame(0, UserField::count());
        $this->assertSame(0, MyLog::count());
    }

    /** @test */
    public function store_fields_generates_unique_slugs_for_fields_with_same_name(): void
    {
        $admin = $this->createSuperAdminForCurrentPartner();
        $this->actingAs($admin);

        $payload = [
            'fields' => [
                [
                    'id'         => null,
                    'name'       => 'Рост',
                    'field_type' => 'string',
                ],
                [
                    'id'         => null,
                    'name'       => 'Рост',
                    'field_type' => 'text',
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);

        $this->assertSame(2, UserField::count());

        $fields = UserField::orderBy('id')->get();

        $this->assertNotEquals($fields[0]->slug, $fields[1]->slug);
    }

    /** @test */
    public function non_superadmin_cannot_switch_current_partner_via_session(): void
    {
        // Админ (не superadmin) должен быть ограничен своим partner_id
        $this->asAdmin();

        // Пытаемся подменить текущего партнёра в сессии на чужого
        $this->withSession(['current_partner' => $this->foreignPartner->id]);

        $foreignField = UserField::create([
            'name'       => 'Чужое поле',
            'slug'       => 'foreign-' . $this->foreignPartner->id,
            'field_type' => 'string',
            'partner_id' => $this->foreignPartner->id,
        ]);

        $payload = [
            'fields' => [
                [
                    'id'         => $foreignField->id,
                    'name'       => 'Попытка изменить',
                    'field_type' => 'text',
                    'roles'      => [],
                ],
            ],
        ];

        $response = $this->postJson(route('admin.field.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // Корректное поведение: текущий партнёр должен быть принудительно = partner_id юзера (и тогда будет 404).
        $response->assertStatus(404);

        $this->assertSame($this->partner->id, session('current_partner'));

        $foreignField->refresh();
        $this->assertEquals('Чужое поле', $foreignField->name);
        $this->assertEquals('string', $foreignField->field_type);

        $this->assertSame(0, MyLog::count(), 'При неуспешной попытке изменений логов быть не должно');
    }
}