<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Доступ к персональной скидке ученика: гость, без users.view, чужой партнёр.
 *
 * @see /docs/documentation/admin-users.html#user-percent-discount
 */
final class UserPercentDiscountAccessFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_open_users_page_or_save_discount(): void
    {
        $student = $this->createStudent([
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);

        Auth::logout();

        $this->get(route('admin.user1'))->assertStatus(302);

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertUnauthorized();

        $this->postJson(route('admin.user.store'), [
            'name' => 'Гость',
            'lastname' => 'Скидка',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Не должно сохраниться',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnauthorized();

        $this->patchJson(route('admin.user.update', $student->id), [
            'name' => $student->name,
            'lastname' => $student->lastname,
            'role_id' => $student->role_id,
            'is_enabled' => '1',
            'discount_percent' => 50,
            'discount_comment' => 'Взлом',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnauthorized();

        $student->refresh();
        $this->assertSame(10, (int) $student->discount_percent);
        $this->assertSame('Льгота', $student->discount_comment);
    }

    public function test_manager_without_users_view_gets_403_even_with_discount_permission(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->grantPermission($denied, 'users.discount.manage');
        $this->actingAs($denied);

        $student = $this->createStudent();

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();

        $this->postJson(route('admin.user.store'), [
            'name' => 'БезРаздела',
            'lastname' => 'Скидка',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();

        $this->patchJson(route('admin.user.update', $student->id), [
            'name' => $student->name,
            'lastname' => $student->lastname,
            'role_id' => $student->role_id,
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();
    }

    public function test_manager_with_users_view_and_discount_permission_can_open_section(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantPermission($this->user, 'users.discount.manage');
        $this->withoutVite();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canManageUserDiscount', true);
    }

    public function test_foreign_student_edit_and_update_are_not_found(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantPermission($this->user, 'users.discount.manage');

        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->studentRoleId(),
            'discount_percent' => 10,
            'discount_comment' => 'Чужая льгота',
        ]);

        $this->getJson(route('admin.user.edit', $foreign->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();

        $this->patchJson(route('admin.user.update', $foreign->id), [
            'name' => 'Взлом',
            'lastname' => 'Чужой',
            'role_id' => $foreign->role_id,
            'is_enabled' => '1',
            'discount_percent' => 90,
            'discount_comment' => 'Не должно сохраниться',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertNotFound();

        $foreign->refresh();
        $this->assertSame(10, (int) $foreign->discount_percent);
        $this->assertSame('Чужая льгота', $foreign->discount_comment);
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
}
