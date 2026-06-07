<?php

namespace Tests\Feature\Crm\Parents;

use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Изоляция партнёра (STRICT_CURRENT) для родителей, семейного кабинета и CRUD в админке.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html §7
 * @see /docs/documentation/partner-scope-guide.html
 */
final class ParentsAndFamilyCabinetPartnerIsolationFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_parents_search_by_id_returns_own_partner_parent(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $ownParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПоискПоId',
            'firstname'  => 'Свой',
        ]);

        $results = $this->getJson(route('admin.users.parents.search', ['id' => $ownParent->id]))
            ->assertOk()
            ->json('results') ?? [];

        $this->assertNotEmpty($results);
        $this->assertSame($ownParent->id, (int) ($results[0]['id'] ?? 0));
    }

    public function test_parents_search_by_id_returns_empty_for_foreign_partner_parent(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'ForeignIdOnly',
            'firstname'  => 'Parent',
        ]);

        $this->getJson(route('admin.users.parents.search', ['id' => $foreignParent->id]))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_parents_search_with_foreign_session_still_returns_only_own_partner(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'СвойИзолированный',
            'firstname'  => 'Родитель',
        ]);

        ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'СвойИзолированный',
            'firstname'  => 'Чужой',
        ]);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed'      => true,
        ]);

        $texts = collect(
            $this->getJson(route('admin.users.parents.search', ['q' => 'СвойИзолированный']))
                ->assertOk()
                ->json('results') ?? []
        )->pluck('text')->all();

        $this->assertContains('СвойИзолированный Родитель', $texts);
        $this->assertNotContains('СвойИзолированный Чужой', $texts);
    }

    public function test_admin_store_with_foreign_parent_id_returns_422(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Ребёнок',
            'lastname'   => 'Изоляция',
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $foreignParent->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_admin_update_with_foreign_parent_id_returns_422(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
        ]);

        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => $student->name,
            'lastname'   => $student->lastname,
            'role_id'    => $student->role_id,
            'parent_id'  => $foreignParent->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_admin_edit_foreign_partner_student_returns_404(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
        ]);

        $this->getJson(route('admin.user.edit', $foreignStudent->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();
    }

    public function test_admin_users_data_does_not_leak_foreign_partner_by_parent_name(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'УтечкаЧужойПартнер',
            'firstname'  => 'X',
        ]);

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $foreignParent->id,
        ]);

        $json = $this->getJson('/admin/users/data?name=УтечкаЧужойПартнер')
            ->assertOk()
            ->json();

        $this->assertSame([], $json['data'] ?? []);
    }

    public function test_switch_to_foreign_partner_sibling_returns_403(): void
    {
        [$brother1, $foreignSibling] = $this->createLocalAndForeignSiblings();

        $this->actingAs($brother1)
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $foreignSibling->id,
            ])
            ->assertForbidden();
    }

    public function test_switch_to_disabled_sibling_returns_403(): void
    {
        $roleId = $this->studentRoleId();
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);

        $brother = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
            'is_enabled' => true,
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
            'is_enabled' => true,
        ]);

        $disabledSibling = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
            'is_enabled' => false,
        ]);

        $this->actingAs($brother)
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $disabledSibling->id,
            ])
            ->assertForbidden();
    }

    public function test_switch_to_unrelated_student_same_partner_returns_403(): void
    {
        $roleId = $this->studentRoleId();
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);

        $brother = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        $stranger = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
        ]);

        $this->actingAs($brother)
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $stranger->id,
            ])
            ->assertForbidden();
    }

    public function test_stale_active_student_session_from_other_family_falls_back_to_actor(): void
    {
        $roleId = $this->studentRoleId();

        $parentA = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);
        $parentB = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);

        $childA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentA->id,
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentA->id,
        ]);

        $lonelyB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentB->id,
        ]);

        $this->withSession([
            FamilyStudentContextService::SESSION_KEY => $childA->id,
        ]);

        $active = app(FamilyStudentContextService::class)->activeStudent($lonelyB);

        $this->assertSame($lonelyB->id, $active->id);
    }

    public function test_account_update_rejects_foreign_parent_profile_for_student(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'Чужой',
            'firstname'  => 'Родитель',
        ]);

        $this->user->forceFill(['parent_id' => $foreignParent->id])->save();
        $this->actingAs($this->user);

        $this->patchJson(route('account.user.update'), [
            'name'             => $this->user->name,
            'lastname'         => $this->user->lastname,
            'parent_lastname'  => 'Взлом',
            'parent_firstname' => 'Родитель',
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname']);

        $foreignParent->refresh();
        $this->assertSame('Чужой', $foreignParent->lastname);
    }

    public function test_regular_user_ignores_foreign_current_partner_on_parent_update(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'До',
            'firstname'  => 'Партнёра',
        ]);

        $this->user->forceFill(['parent_id' => $parent->id])->save();
        $this->actingAs($this->user);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed'      => true,
        ])
            ->patchJson(route('account.user.update'), [
                'name'             => $this->user->name,
                'lastname'         => $this->user->lastname,
                'parent_lastname'  => 'После',
                'parent_firstname' => 'Партнёра',
            ], $this->jsonHeaders())
            ->assertOk();

        $parent->refresh();
        $this->assertSame('После', $parent->lastname);
        $this->assertSame($this->partner->id, (int) $parent->partner_id);
    }

    public function test_get_user_payments_does_not_return_foreign_partner_sibling_payments(): void
    {
        [$brother1, $foreignSibling] = $this->createLocalAndForeignSiblings();

        Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $brother1->id,
            'summ'       => 100,
        ]);

        Payment::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id'    => $foreignSibling->id,
            'summ'       => 999,
        ]);

        $this->actingAs($brother1);
        $this->grantPermission($brother1, 'myPayments.view');

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $foreignSibling->id,
        ])->assertForbidden();

        $rows = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson('/getUserPayments?draw=1&start=0&length=10')
            ->assertOk()
            ->json('data') ?? [];

        $this->assertCount(1, $rows);
        $this->assertSame($brother1->id, (int) ($rows[0]['user_id'] ?? 0));
    }

    public function test_guest_cannot_switch_active_student(): void
    {
        Auth::logout();

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => 1,
        ])->assertRedirect(route('login'));
    }

    public function test_switch_requires_valid_student_user_id(): void
    {
        $roleId = $this->studentRoleId();
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);

        $brother = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        $this->actingAs($brother)
            ->post(route('cabinet.active-student.switch'), [])
            ->assertSessionHasErrors(['student_user_id']);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function createLocalAndForeignSiblings(): array
    {
        $roleId = $this->studentRoleId();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
            'is_enabled' => true,
        ]);

        $foreignSibling = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
            'is_enabled' => true,
        ]);

        return [$brother1, $foreignSibling];
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
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
}
