<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Персональная скидка ученика: users.discount.manage.
 *
 * @see /docs/documentation/admin-users.html#user-percent-discount
 */
final class UserPercentDiscountFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.discount.manage');
    }

    public function test_gate_allows_users_discount_manage_with_permission(): void
    {
        $this->assertTrue(\Gate::forUser($this->user)->allows('users.discount.manage'));
    }

    public function test_gate_denies_users_discount_manage_without_permission(): void
    {
        $this->revokePermission($this->user, 'users.discount.manage');

        $this->assertFalse(\Gate::forUser($this->user)->allows('users.discount.manage'));
    }

    public function test_permission_is_visible_and_not_in_role_base_permissions(): void
    {
        $row = DB::table('permissions')->where('name', 'users.discount.manage')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->is_visible);

        $names = config('role_base_permissions.roles.admin', []);
        $this->assertNotContains('users.discount.manage', $names);
        $this->assertNotContains('users.discount.manage', config('role_base_permissions.roles.user', []));
    }

    public function test_users_page_shows_discount_fields_with_permission(): void
    {
        $this->withoutVite();

        $html = (string) $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canManageUserDiscount', true)
            ->getContent();

        $this->assertStringContainsString('id="create-discount_percent"', $html);
        $this->assertStringContainsString('id="edit-discount_percent"', $html);
        $this->assertStringContainsString('id="create-discount_comment"', $html);
        $this->assertStringContainsString('name="discount_percent"', $html);
        $this->assertStringNotContainsString('data-column-key="discount_percent"', $html);
    }

    public function test_users_page_hides_discount_fields_without_permission(): void
    {
        $this->revokePermission($this->user, 'users.discount.manage');
        $this->withoutVite();

        $html = (string) $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canManageUserDiscount', false)
            ->getContent();

        $this->assertStringNotContainsString('id="create-discount_percent"', $html);
        $this->assertStringNotContainsString('id="edit-discount_percent"', $html);
    }

    public function test_store_persists_percent_and_comment_for_student(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Скидкин',
            'lastname' => 'Иванов',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота многодетной семьи',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Скидкин')
            ->firstOrFail();

        $this->assertSame(10, (int) $student->discount_percent);
        $this->assertSame('Льгота многодетной семьи', $student->discount_comment);
    }

    public function test_store_requires_comment_when_percent_positive(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'БезОснования',
            'lastname' => 'Иванов',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => '',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_comment']);
    }

    public function test_store_rejects_non_integer_percent(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Дробь',
            'lastname' => 'Иванов',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => '7.5',
            'discount_comment' => 'Нельзя',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent']);
    }

    public function test_update_strips_discount_without_permission(): void
    {
        $student = $this->createStudent([
            'discount_percent' => 10,
            'discount_comment' => 'Было',
        ]);
        $this->revokePermission($this->user, 'users.discount.manage');

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'discount_percent' => 50,
            'discount_comment' => 'Не сохранится',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();
        $this->assertSame(10, (int) $student->discount_percent);
        $this->assertSame('Было', $student->discount_comment);
    }

    public function test_update_clears_comment_when_percent_zero(): void
    {
        $student = $this->createStudent([
            'discount_percent' => 10,
            'discount_comment' => 'Было',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'discount_percent' => 0,
            'discount_comment' => 'Игнор',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();
        $this->assertNull($student->discount_percent);
        $this->assertNull($student->discount_comment);
    }

    public function test_edit_json_includes_discount_only_with_permission(): void
    {
        $student = $this->createStudent([
            'discount_percent' => 15,
            'discount_comment' => 'Спортшкола',
        ]);

        $this->getJson(route('admin.user.edit', $student->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('ui.canManageUserDiscount', true)
            ->assertJsonPath('user.discount_percent', 15)
            ->assertJsonPath('user.discount_comment', 'Спортшкола');

        $this->revokePermission($this->user, 'users.discount.manage');

        $json = $this->getJson(route('admin.user.edit', $student->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('ui.canManageUserDiscount', false)
            ->json('user');

        $this->assertArrayNotHasKey('discount_percent', $json);
        $this->assertArrayNotHasKey('discount_comment', $json);
    }

    public function test_update_writes_audit_lines_for_percent_and_reason(): void
    {
        $student = $this->createStudent([
            'discount_percent' => null,
            'discount_comment' => null,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $log = MyLog::query()
            ->where('event', AuditEvent::UserUpdated->value)
            ->where('target_id', $student->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Скидка, %:', (string) $log->description);
        $this->assertStringContainsString('Основание скидки:', (string) $log->description);
    }

    public function test_data_table_does_not_include_discount_column(): void
    {
        $student = $this->createStudent([
            'lastname' => 'DiscountDt',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);

        $row = collect(
            $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=DiscountDt')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $student->id);

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('discount_percent', $row);
        $this->assertArrayNotHasKey('discount_comment', $row);
    }

    public function test_store_strips_discount_without_permission(): void
    {
        $this->revokePermission($this->user, 'users.discount.manage');

        $this->postJson(route('admin.user.store'), [
            'name' => 'БезПрава',
            'lastname' => 'Скидка',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Не должно сохраниться',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'БезПрава')
            ->firstOrFail();

        $this->assertNull($student->discount_percent);
        $this->assertNull($student->discount_comment);
    }

    public function test_store_ignores_discount_for_trainer_role(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Тренер',
            'lastname' => 'Скидка',
            'role_id' => $this->roleId('trainer'),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Не для тренера',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $trainer = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Тренер')
            ->where('lastname', 'Скидка')
            ->firstOrFail();

        $this->assertNull($trainer->discount_percent);
        $this->assertNull($trainer->discount_comment);
    }

    public function test_update_ignores_discount_for_trainer_role(): void
    {
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('trainer'),
            'discount_percent' => null,
            'discount_comment' => null,
        ]);

        $this->patchJson(route('admin.user.update', $trainer->id), [
            'name' => $trainer->name,
            'lastname' => $trainer->lastname,
            'role_id' => $trainer->role_id,
            'is_enabled' => 1,
            'discount_percent' => 10,
            'discount_comment' => 'Не для тренера',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $trainer->refresh();
        $this->assertNull($trainer->discount_percent);
        $this->assertNull($trainer->discount_comment);
    }

    public function test_update_keeps_stored_discount_when_role_changed_to_trainer(): void
    {
        $student = $this->createStudent([
            'discount_percent' => 10,
            'discount_comment' => 'Остаётся в БД',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'role_id' => $this->roleId('trainer'),
            'discount_percent' => 50,
            'discount_comment' => 'Не применится',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();
        $this->assertSame('trainer', $student->role?->name);
        $this->assertSame(10, (int) $student->discount_percent);
        $this->assertSame('Остаётся в БД', $student->discount_comment);
    }

    public function test_users_page_renders_discount_fields_after_comment_with_error_slots(): void
    {
        $this->grantPermission($this->user, 'users.comment');
        $this->withoutVite();

        $html = (string) $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $commentPos = strpos($html, 'id="create-comment"');
        $discountPos = strpos($html, 'id="create-discount_percent"');
        $this->assertNotFalse($commentPos);
        $this->assertNotFalse($discountPos);
        $this->assertGreaterThan($commentPos, $discountPos);

        $this->assertStringContainsString('data-error-for="discount_percent"', $html);
        $this->assertStringContainsString('data-error-for="discount_comment"', $html);
        $this->assertStringContainsString('js-user-discount-comment-required', $html);
        $this->assertStringContainsString('js-user-discount-wrap', $html);
        $this->assertStringContainsString('maxlength="500"', $html);
        $this->assertStringContainsString('max="100"', $html);
    }

    public function test_create_modal_discount_wrap_is_visible_for_locked_student_role_edit_starts_hidden(): void
    {
        $this->withoutVite();

        $html = (string) $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<div class="col-12 js-user-discount-wrap\s+"[^>]*data-discount-prefix="create"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<div class="col-12 js-user-discount-wrap d-none"[^>]*data-discount-prefix="edit"/',
            $html
        );
        $this->assertStringContainsString('id="create-discount_percent"', $html);
        $this->assertStringContainsString('value=""', $html);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function revokePermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $actor->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function createStudent(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
        ], $attrs));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function studentPatchPayload(User $student, array $extra = []): array
    {
        return array_merge([
            'name' => $student->name,
            'lastname' => $student->lastname,
            'role_id' => $student->role_id,
            'is_enabled' => $student->is_enabled ? '1' : '0',
        ], $extra);
    }
}
