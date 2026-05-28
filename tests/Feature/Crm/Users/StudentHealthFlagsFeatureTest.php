<?php

namespace Tests\Feature\Crm\Users;

use App\Models\MyLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Сведения об ученике (мед./особенности): флаги, право users.other.update, доступ к разделу.
 *
 * @see /docs/documentation/admin-users.html
 */
final class StudentHealthFlagsFeatureTest extends CrmTestCase
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
        $this->grantPermission($this->user, 'users.role.update');
        $this->grantPermission($this->user, 'users.other.update');
    }

    public function test_gate_allows_users_other_update_for_admin_with_permission(): void
    {
        $this->assertTrue(
            \Gate::forUser($this->user)->allows('users.other.update')
        );
    }

    public function test_edit_json_includes_health_fields_for_student(): void
    {
        $student = $this->createStudent([
            'is_individual_traits'   => true,
            'is_on_medical_register' => false,
            'is_with_disability'     => null,
        ]);

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.is_individual_traits', true)
            ->assertJsonPath('user.is_on_medical_register', false)
            ->assertJsonPath('user.is_with_disability', null);
    }

    public function test_update_persists_health_flags_for_student(): void
    {
        $student = $this->createStudent();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '0',
            'is_with_disability'     => '1',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertTrue($student->is_individual_traits);
        $this->assertFalse($student->is_on_medical_register);
        $this->assertTrue($student->is_with_disability);
    }

    public function test_update_can_clear_health_flags_to_null(): void
    {
        $student = $this->createStudent([
            'is_individual_traits'   => true,
            'is_on_medical_register' => true,
            'is_with_disability'     => true,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'is_individual_traits'   => '',
            'is_on_medical_register' => '',
            'is_with_disability'     => '',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertNull($student->is_individual_traits);
        $this->assertNull($student->is_on_medical_register);
        $this->assertNull($student->is_with_disability);
    }

    public function test_update_ignores_health_fields_without_users_other_update_permission(): void
    {
        $this->revokePermission($this->user, 'users.other.update');
        $this->assertFalse(\Gate::forUser($this->user)->allows('users.other.update'));

        $student = $this->createStudent([
            'is_individual_traits'   => null,
            'is_on_medical_register' => null,
            'is_with_disability'     => null,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '1',
            'is_with_disability'     => '1',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertNull($student->is_individual_traits);
        $this->assertNull($student->is_on_medical_register);
        $this->assertNull($student->is_with_disability);
    }

    public function test_update_ignores_health_fields_for_trainer_role(): void
    {
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('trainer'),
            'is_individual_traits'   => null,
            'is_on_medical_register' => null,
            'is_with_disability'     => null,
        ]);

        $this->patchJson(route('admin.user.update', $trainer->id), [
            'name'                   => $trainer->name,
            'lastname'               => $trainer->lastname,
            'role_id'                => $trainer->role_id,
            'is_enabled'             => 1,
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '1',
            'is_with_disability'     => '1',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $trainer->refresh();

        $this->assertNull($trainer->is_individual_traits);
        $this->assertNull($trainer->is_on_medical_register);
        $this->assertNull($trainer->is_with_disability);
    }

    public function test_update_strips_health_fields_when_role_changed_to_trainer(): void
    {
        $student = $this->createStudent([
            'is_individual_traits' => true,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'role_id'                => $this->roleId('trainer'),
            'is_individual_traits'   => '0',
            'is_on_medical_register' => '0',
            'is_with_disability'     => '0',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertSame('trainer', $student->role?->name);
        $this->assertTrue($student->is_individual_traits);
    }

    public function test_update_health_fields_validation_rejects_invalid_value(): void
    {
        $student = $this->createStudent();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'is_individual_traits' => 'maybe',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_individual_traits']);
    }

    public function test_update_logs_health_field_changes(): void
    {
        $student = $this->createStudent([
            'is_individual_traits'   => null,
            'is_on_medical_register' => false,
            'is_with_disability'     => null,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '1',
            'is_with_disability'     => '0',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $log = MyLog::query()
            ->where('target_type', User::class)
            ->where('target_id', $student->id)
            ->where('action', 22)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $desc = (string) $log->description;
        $this->assertStringContainsString('Индивидуальные особенности', $desc);
        $this->assertStringContainsString('Учёт у медспециалистов', $desc);
        $this->assertStringContainsString('Инвалидность', $desc);
    }

    public function test_users_page_shows_health_block_when_actor_has_users_other_update(): void
    {
        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertNotFalse($html);
        $this->assertStringContainsString('name="is_individual_traits"', $html);
        $this->assertStringContainsString('name="is_on_medical_register"', $html);
        $this->assertStringContainsString('name="is_with_disability"', $html);
        $this->assertStringContainsString('Сведения об ученике', $html);
    }

    public function test_users_page_hides_health_block_without_users_other_update(): void
    {
        $this->revokePermission($this->user, 'users.other.update');

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertNotFalse($html);
        $this->assertStringNotContainsString('name="is_individual_traits"', $html);
        $this->assertStringNotContainsString('Сведения об ученике', $html);
    }

    // --- Доступ к разделу и endpoint’ам (200 при users.view) ---

    public function test_guest_cannot_access_users_section_endpoints(): void
    {
        Auth::logout();

        $student = $this->createStudent();

        $this->get(route('admin.user1'))->assertStatus(302);

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertUnauthorized();

        $this->patchJson(route('admin.user.update', $student->id), [
            'name' => $student->name,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnauthorized();
    }

    public function test_user_without_users_view_gets_403_on_section_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
        ]);

        $this->get(route('admin.user1'))->assertForbidden();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();
    }

    public function test_users_section_endpoints_return_ok_with_users_view_and_health_update(): void
    {
        $student = $this->createStudent();

        $this->get(route('admin.user1'))->assertOk();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'is_individual_traits',
                    'is_on_medical_register',
                    'is_with_disability',
                ],
            ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '0',
            'is_with_disability'     => '',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();
        $this->assertTrue($student->is_individual_traits);
        $this->assertFalse($student->is_on_medical_register);
        $this->assertNull($student->is_with_disability);
    }

    private function studentRoleId(): int
    {
        return $this->roleId('user');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Тест',
            'lastname'   => 'Ученик',
            'is_enabled' => true,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function studentPatchPayload(User $student, array $extra = []): array
    {
        return array_merge([
            'name'       => $student->name,
            'lastname'   => $student->lastname,
            'role_id'    => $student->role_id,
            'is_enabled' => (int) $student->is_enabled,
        ], $extra);
    }

    private function grantPermission(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function revokePermission(User $user, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $user->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }
}
