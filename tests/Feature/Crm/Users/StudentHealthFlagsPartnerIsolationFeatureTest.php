<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Изоляция партнёра для сведений об ученике (мед./особенности) и CRUD ученика.
 *
 * @see /docs/documentation/partner-scope-guide.html
 * @see /docs/documentation/admin-users.html §2.1.3
 */
final class StudentHealthFlagsPartnerIsolationFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.name.update');
        $this->grantPermission($this->user, 'users.activity.update');
        $this->grantPermission($this->user, 'users.other.update');
    }

    public function test_edit_foreign_partner_student_returns_404(): void
    {
        $foreignStudent = $this->createForeignStudent([
            'is_individual_traits' => true,
        ]);

        $this->getJson(route('admin.user.edit', $foreignStudent->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();
    }

    public function test_update_foreign_partner_student_returns_404(): void
    {
        $foreignStudent = $this->createForeignStudent([
            'is_individual_traits'   => null,
            'is_on_medical_register' => null,
            'is_with_disability'     => null,
        ]);

        $this->patchJson(route('admin.user.update', $foreignStudent->id), [
            'name'                   => $foreignStudent->name,
            'lastname'               => $foreignStudent->lastname,
            'role_id'                => $foreignStudent->role_id,
            'is_enabled'             => 1,
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '1',
            'is_with_disability'     => '1',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertNotFound();

        $foreignStudent->refresh();

        $this->assertNull($foreignStudent->is_individual_traits);
        $this->assertNull($foreignStudent->is_on_medical_register);
        $this->assertNull($foreignStudent->is_with_disability);
    }

    public function test_users_data_does_not_include_foreign_partner_student(): void
    {
        $local = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'LocalHealthMarker',
        ]);

        $foreign = $this->createForeignStudent([
            'name'                 => 'ForeignHealthMarker',
            'is_individual_traits' => true,
        ]);

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=500')
            ->assertOk()
            ->json();

        $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($local->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_users_data_filter_by_foreign_student_id_returns_empty(): void
    {
        $foreign = $this->createForeignStudent();

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=10&id=' . $foreign->id)
            ->assertOk()
            ->json();

        $this->assertSame(0, (int) ($json['recordsFiltered'] ?? -1));
        $ids = collect($json['data'] ?? [])->pluck('id')->all();
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_own_partner_student_health_update_succeeds(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Свой',
            'lastname'   => 'Ученик',
        ]);

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $student->id);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'                   => $student->name,
            'lastname'               => $student->lastname,
            'role_id'                => $student->role_id,
            'is_enabled'             => 1,
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '0',
            'is_with_disability'     => '',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertTrue($student->is_individual_traits);
        $this->assertFalse($student->is_on_medical_register);
        $this->assertNull($student->is_with_disability);
    }

    public function test_foreign_partner_admin_cannot_edit_own_student_from_other_partner_session(): void
    {
        $localStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'is_individual_traits' => true,
        ]);

        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $this->grantPermission($foreignAdmin, 'users.view', $this->foreignPartner);
        $this->grantPermission($foreignAdmin, 'users.other.update', $this->foreignPartner);

        $this->actingAs($foreignAdmin);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('admin.user.edit', $localStudent->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();

        $this->patchJson(route('admin.user.update', $localStudent->id), [
            'name'                 => 'Взлом',
            'lastname'             => 'Имя',
            'role_id'              => $localStudent->role_id,
            'is_enabled'           => 1,
            'is_individual_traits' => '0',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertNotFound();

        $localStudent->refresh();
        $this->assertTrue($localStudent->is_individual_traits);
    }

    public function test_superadmin_with_current_partner_cannot_update_foreign_student_health(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $foreignStudent = $this->createForeignStudent();

        $this->patchJson(route('admin.user.update', $foreignStudent->id), [
            'name'                 => 'Hack',
            'lastname'             => 'Name',
            'role_id'              => $foreignStudent->role_id,
            'is_enabled'           => 1,
            'is_individual_traits' => '1',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertNotFound();
    }

    public function test_admin_users_index_and_data_endpoints_are_accessible_with_users_view(): void
    {
        $this->get(route('admin.user1'))->assertOk();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createForeignStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => true,
        ], $attributes));
    }

    private function grantPermission(User $user, string $permissionName, ?\App\Models\Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
