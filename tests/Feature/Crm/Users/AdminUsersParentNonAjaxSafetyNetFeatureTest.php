<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Non-AJAX safety-net для store/update ученика с правкой ФИО родителя из справочника.
 * POST/PATCH без X-Requested-With → 302 на /admin/users, запись в БД создана/обновлена (не пустой 200).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/admin-users.html §2.1
 */
final class AdminUsersParentNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantUsersView($this->user);
    }

    public function test_store_non_ajax_redirects_and_creates_student_with_new_parent_fio(): void
    {
        $payload = [
            'name'              => 'NonAjax',
            'lastname'          => 'СРодителем',
            'role_id'           => $this->studentRoleId(),
            'parent_lastname'   => 'СозданныйNonAjax',
            'parent_firstname'  => 'Родитель',
            'parent_middlename' => 'Тест',
            'is_enabled'        => 1,
        ];

        $this->post(route('admin.user.store'), $payload)
            ->assertRedirect(route('admin.user1'));

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'СРодителем')
            ->first();

        $this->assertNotNull($student);
        $this->assertNotNull($student->parent_id);
        $this->assertDatabaseHas('parents', [
            'id'         => $student->parent_id,
            'partner_id' => $this->partner->id,
            'lastname'   => 'СозданныйNonAjax',
            'firstname'  => 'Родитель',
            'middlename' => 'Тест',
        ]);
    }

    public function test_store_non_ajax_with_parent_id_redirects_and_updates_directory_fio(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ДоNonAjaxStore',
            'firstname'  => 'Родитель',
        ]);

        $this->post(route('admin.user.store'), [
            'name'             => 'Ребёнок',
            'lastname'         => 'NonAjaxLink',
            'role_id'          => $this->studentRoleId(),
            'parent_id'        => $parent->id,
            'parent_lastname'  => 'ПослеNonAjaxStore',
            'parent_firstname' => 'Родитель',
            'parent_middlename'=> 'Обновлён',
            'is_enabled'       => 1,
        ])->assertRedirect(route('admin.user1'));

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'NonAjaxLink')
            ->firstOrFail();

        $this->assertSame($parent->id, $student->parent_id);
        $parent->refresh();
        $this->assertSame('ПослеNonAjaxStore', $parent->lastname);
        $this->assertSame('Обновлён', $parent->middlename);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $tooLong = str_repeat('а', 101);

        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'name'             => 'Fail',
                'lastname'         => 'NonAjax',
                'role_id'          => $this->studentRoleId(),
                'parent_lastname'  => $tooLong,
                'parent_firstname' => 'Тест',
                'is_enabled'       => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['parent_lastname']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'NonAjax',
            'name'       => 'Fail',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_directory_parent_fio(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ДоNonAjaxUpdate',
            'firstname'  => 'Родитель',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Ученик',
            'lastname'   => 'NonAjaxPatch',
        ]);

        $this->patch(route('admin.user.update', $student->id), [
            'name'              => $student->name,
            'lastname'          => $student->lastname,
            'role_id'           => $student->role_id,
            'parent_id'         => $parent->id,
            'parent_lastname'   => 'ПослеNonAjaxUpdate',
            'parent_firstname'  => 'Родитель',
            'parent_middlename' => 'Патч',
            'parent_passport'   => '1111 222333',
            'is_enabled'        => 1,
        ])->assertRedirect(route('admin.user1'));

        $parent->refresh();
        $this->assertSame('ПослеNonAjaxUpdate', $parent->lastname);
        $this->assertSame('Патч', $parent->middlename);
        $this->assertSame('1111 222333', $parent->passport);
        $this->assertSame($parent->id, $student->fresh()->parent_id);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ВалидацияNonAjax',
            'firstname'  => 'Родитель',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Ученик',
            'lastname'   => 'Валид',
        ]);

        $tooLong = str_repeat('б', 101);

        $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student->id), [
                'name'             => $student->name,
                'lastname'         => $student->lastname,
                'role_id'          => $student->role_id,
                'parent_id'        => $parent->id,
                'parent_lastname'  => $tooLong,
                'parent_firstname' => 'Родитель',
                'is_enabled'       => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['parent_lastname']);

        $this->assertSame('ВалидацияNonAjax', $parent->fresh()->lastname);
    }
}
