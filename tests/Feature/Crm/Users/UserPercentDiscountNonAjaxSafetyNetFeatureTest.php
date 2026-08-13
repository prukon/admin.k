<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Non-AJAX safety-net для store/update ученика со скидкой.
 * POST/PATCH без X-Requested-With → 302 на /admin/users, запись в БД (не пустой 200).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/admin-users.html#user-percent-discount
 */
final class UserPercentDiscountNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantPermission($this->user, 'users.discount.manage');
    }

    public function test_store_non_ajax_redirects_and_creates_student_with_discount(): void
    {
        $response = $this->post(route('admin.user.store'), [
            'name' => 'NonAjax',
            'lastname' => 'Скидка',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Скидка')
            ->where('name', 'NonAjax')
            ->first();

        $this->assertNotNull($student);
        $this->assertSame(10, (int) $student->discount_percent);
        $this->assertSame('Льгота', $student->discount_comment);
    }

    public function test_store_non_ajax_validation_redirects_with_discount_comment_error(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'name' => 'Fail',
                'lastname' => 'NonAjax',
                'role_id' => $this->studentRoleId(),
                'is_enabled' => '1',
                'discount_percent' => 10,
                'discount_comment' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['discount_comment']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname' => 'NonAjax',
            'name' => 'Fail',
        ]);
    }

    public function test_update_non_ajax_redirects_and_persists_discount(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'name' => 'Ученик',
            'lastname' => 'NonAjaxPatch',
        ]);

        $this->patch(route('admin.user.update', $student->id), [
            'name' => $student->name,
            'lastname' => $student->lastname,
            'role_id' => $student->role_id,
            'is_enabled' => '1',
            'discount_percent' => 15,
            'discount_comment' => 'Спортшкола',
        ])->assertRedirect(route('admin.user1'));

        $student->refresh();
        $this->assertSame(15, (int) $student->discount_percent);
        $this->assertSame('Спортшкола', $student->discount_comment);
    }

    public function test_update_non_ajax_validation_redirects_with_discount_percent_error(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'discount_percent' => 10,
            'discount_comment' => 'Было',
        ]);

        $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student->id), [
                'name' => $student->name,
                'lastname' => $student->lastname,
                'role_id' => $student->role_id,
                'is_enabled' => '1',
                'discount_percent' => 101,
                'discount_comment' => 'Нельзя',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['discount_percent']);

        $student->refresh();
        $this->assertSame(10, (int) $student->discount_percent);
        $this->assertSame('Было', $student->discount_comment);
    }
}
