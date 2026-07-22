<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * CRUD ученика с родителем: создание по ФИО, привязка, отвязка, смена роли.
 */
final class AdminUsersParentCrudFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    private function trainerRoleId(): int
    {
        return (int) Role::query()->where('name', 'trainer')->value('id');
    }

    public function test_store_creates_new_parent_profile_from_fio_without_parent_id(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'              => 'Аня',
            'lastname'          => 'Ученица',
            'role_id'           => $this->studentRoleId(),
            'parent_lastname'   => 'Новосозданный',
            'parent_firstname'  => 'Родитель',
            'parent_middlename' => 'Тестович',
            'is_enabled'        => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Ученица')
            ->firstOrFail();

        $this->assertNotNull($student->parent_id);
        $this->assertDatabaseHas('parents', [
            'id'         => $student->parent_id,
            'partner_id' => $this->partner->id,
            'lastname'   => 'Новосозданный',
            'firstname'  => 'Родитель',
            'middlename' => 'Тестович',
        ]);
    }

    public function test_update_changes_linked_parent_fio(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Старый',
            'firstname'  => 'Родитель',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Пётр',
            'lastname'   => 'Ученик',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'              => $student->name,
            'lastname'          => $student->lastname,
            'role_id'           => $student->role_id,
            'parent_id'         => $parent->id,
            'parent_lastname'   => 'Обновлённый',
            'parent_firstname'  => 'Родитель',
            'parent_middlename' => '',
            'is_enabled'        => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $parent->refresh();
        $this->assertSame('Обновлённый', $parent->lastname);
        $this->assertSame($parent->id, $student->fresh()->parent_id);
    }

    public function test_update_parent_fio_via_one_student_is_shared_with_sibling(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Общий',
            'firstname'  => 'Родитель',
            'middlename' => 'Иванович',
        ]);

        $childA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Анна',
            'lastname'   => 'Общая',
        ]);

        $childB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Борис',
            'lastname'   => 'Общий',
        ]);

        $this->patchJson(route('admin.user.update', $childA->id), [
            'name'              => $childA->name,
            'lastname'          => $childA->lastname,
            'role_id'           => $childA->role_id,
            'parent_id'         => $parent->id,
            'parent_lastname'   => 'НоваяФамилия',
            'parent_firstname'  => 'Родитель',
            'parent_middlename' => 'Петрович',
            'is_enabled'        => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $parent->refresh();
        $this->assertSame('НоваяФамилия', $parent->lastname);
        $this->assertSame('Петрович', $parent->middlename);
        $this->assertSame($parent->id, $childA->fresh()->parent_id);
        $this->assertSame($parent->id, $childB->fresh()->parent_id);
        $this->assertSame('НоваяФамилия Родитель Петрович', $childB->fresh()->parent_full_name);
    }

    public function test_update_with_explicit_null_parent_id_unlinks_student(): void
    {
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'              => $student->name,
            'lastname'          => $student->lastname,
            'role_id'           => $student->role_id,
            'parent_id'         => null,
            'parent_lastname'   => '',
            'parent_firstname'  => '',
            'parent_middlename' => '',
            'is_enabled'        => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertNull($student->fresh()->parent_id);
    }

    public function test_update_student_to_trainer_clears_parent_id(): void
    {
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Иван',
            'lastname'   => 'Учеников',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => $student->name,
            'lastname'   => $student->lastname,
            'role_id'    => $this->trainerRoleId(),
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertNull($student->fresh()->parent_id);
    }

    public function test_store_trainer_role_does_not_link_parent_even_if_parent_id_sent(): void
    {
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Тренер',
            'lastname'   => 'Новый',
            'role_id'    => $this->trainerRoleId(),
            'parent_id'  => $parent->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $trainer = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Новый')
            ->firstOrFail();

        $this->assertNull($trainer->parent_id);
    }
}
