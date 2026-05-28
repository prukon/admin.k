<?php

namespace Tests\Feature\Crm\Parents;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\StudentParentSyncService;
use Tests\Feature\Crm\CrmTestCase;

final class StudentParentSyncServiceTest extends CrmTestCase
{
    public function test_update_without_parent_id_keeps_existing_parent_and_updates_fio(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        app(StudentParentSyncService::class)->syncForStudent($student, (int) $this->partner->id, [
            'parent_lastname'  => 'Сидоров',
            'parent_firstname' => 'Сидор',
            'parent_middlename'=> null,
        ]);

        $student->refresh();
        $parent->refresh();

        $this->assertSame($parent->id, $student->parent_id);
        $this->assertSame('Сидоров', $parent->lastname);
        $this->assertSame('Сидор', $parent->firstname);
        $this->assertSame('Сидоров Сидор', $student->fresh()->parent_full_name);
    }

    public function test_admin_form_with_empty_fio_and_without_parent_id_clears_link(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        app(StudentParentSyncService::class)->syncForStudent($student, (int) $this->partner->id, [
            'parent_lastname'   => '',
            'parent_firstname'  => '',
            'parent_middlename' => '',
        ]);

        $this->assertNull($student->fresh()->parent_id);
    }

    public function test_non_student_role_clears_parent_link(): void
    {
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $trainerRoleId,
            'parent_id'  => $parent->id,
        ]);

        app(StudentParentSyncService::class)->syncForStudent($user, (int) $this->partner->id, [
            'parent_id'        => $parent->id,
            'parent_lastname'  => 'Игнор',
            'parent_firstname' => 'Игнор',
        ]);

        $this->assertNull($user->fresh()->parent_id);
    }

    public function test_explicit_empty_parent_id_clears_link(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        app(StudentParentSyncService::class)->syncForStudent($student, (int) $this->partner->id, [
            'parent_id' => null,
        ]);

        $student->refresh();

        $this->assertNull($student->parent_id);
    }
}
