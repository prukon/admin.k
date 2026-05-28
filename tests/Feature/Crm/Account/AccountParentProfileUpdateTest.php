<?php

namespace Tests\Feature\Crm\Account;

use App\Models\ParentProfile;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;

class AccountParentProfileUpdateTest extends CrmTestCase
{
    public function test_student_can_update_linked_parent_profile_in_account(): void
    {
        $this->actingAs($this->user);

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
            'middlename' => 'Иванович',
        ]);

        $this->user->forceFill(['parent_id' => $parent->id])->save();

        $payload = [
            'name'              => $this->user->name,
            'lastname'          => $this->user->lastname,
            'parent_lastname'   => 'Петров',
            'parent_firstname'  => 'Пётр',
            'parent_middlename' => 'Петрович',
        ];

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ])
            ->withHeaders([
                'Accept'           => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $parent->refresh();
        $this->user->refresh();

        $this->assertSame('Петров', $parent->lastname);
        $this->assertSame('Пётр', $parent->firstname);
        $this->assertSame('Петрович', $parent->middlename);
        $this->assertSame('Петров', $this->user->parentProfile?->lastname);
        $this->assertSame('Пётр', $this->user->parentProfile?->firstname);
        $this->assertSame('Петрович', $this->user->parentProfile?->middlename);
        $this->assertSame('Петров Пётр Петрович', $this->user->parent_full_name);
    }

    public function test_student_without_permission_cannot_update_parent_via_account(): void
    {
        $this->revokePermissionFromRole('user', 'account.user.parent.update');
        $this->actingAs($this->user);

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
        ]);

        $this->user->forceFill(['parent_id' => $parent->id])->save();

        $payload = [
            'name'            => $this->user->name,
            'lastname'        => $this->user->lastname,
            'parent_lastname' => 'Петров',
        ];

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ])
            ->withHeaders([
                'Accept'           => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload)
            ->assertOk();

        $parent->refresh();
        $this->assertSame('Иванов', $parent->lastname);
    }

    public function test_sibling_sees_parent_profile_updated_by_brother(): void
    {
        $this->actingAs($this->user);

        $profile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Сидоров',
            'firstname'  => 'Сидор',
        ]);

        $this->user->forceFill(['parent_id' => $profile->id])->save();

        $sibling = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
            'parent_id'  => $profile->id,
        ]);

        $payload = [
            'name'              => $this->user->name,
            'lastname'          => $this->user->lastname,
            'parent_lastname'   => 'Сидорова',
            'parent_firstname'  => 'Анна',
            'parent_middlename' => null,
        ];

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ])
            ->withHeaders([
                'Accept'           => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload)
            ->assertOk();

        $profile->refresh();
        $sibling->refresh();

        $this->assertSame('Сидорова', $profile->lastname);
        $this->assertSame('Анна', $profile->firstname);
        $sibling->load('parentProfile');
        $this->assertSame('Сидорова', $sibling->parentProfile?->lastname);
        $this->assertSame('Анна', $sibling->parentProfile?->firstname);
    }

    public function test_account_edit_page_shows_parent_section_when_permission_granted(): void
    {
        $this->actingAs($this->user);

        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('Данные родителя')
            ->assertSee('parent_lastname', false);
    }

    private function revokePermissionFromRole(string $roleName, string $permissionName): void
    {
        $roleId = $this->roleId($roleName);
        $permId = $this->permissionId($permissionName);

        \Illuminate\Support\Facades\DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $permId)
            ->delete();
    }
}
