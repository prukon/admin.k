<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * AJAX-контракт create/edit ученика со скидкой:
 * postJson/patchJson + X-Requested-With → JSON (message, user), 200/422, не пустой 200.
 *
 * @see /docs/documentation/admin-users.html#user-percent-discount
 */
final class UserPercentDiscountAjaxContractFeatureTest extends CrmTestCase
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

    /** @return array<string, string> */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_store_ajax_returns_message_and_user_and_persists_discount(): void
    {
        $response = $this->postJson(route('admin.user.store'), [
            'name' => 'Ajax',
            'lastname' => 'Скидка',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота многодетной семьи',
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'is_enabled'],
            ]);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $student = User::query()->findOrFail((int) $response->json('user.id'));
        $this->assertSame(10, (int) $student->discount_percent);
        $this->assertSame('Льгота многодетной семьи', $student->discount_comment);
    }

    public function test_update_ajax_returns_message_and_persists_discount(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'name' => 'Было',
            'lastname' => 'AjaxPatch',
        ]);

        $response = $this->patchJson(route('admin.user.update', $student->id), [
            'name' => $student->name,
            'lastname' => $student->lastname,
            'role_id' => $student->role_id,
            'is_enabled' => '1',
            'discount_percent' => 25,
            'discount_comment' => 'Спортшкола',
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonStructure(['message']);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $student->refresh();
        $this->assertSame(25, (int) $student->discount_percent);
        $this->assertSame('Спортшкола', $student->discount_comment);
    }

    public function test_store_ajax_validation_returns_422_with_discount_comment_field_error(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Fail',
            'lastname' => 'Ajax',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => '',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_comment']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname' => 'Ajax',
            'name' => 'Fail',
        ]);
    }

    public function test_store_ajax_rejects_percent_above_100_under_discount_percent_field(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Слишком',
            'lastname' => 'Много',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 101,
            'discount_comment' => 'Нельзя',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent']);
    }

    public function test_store_ajax_rejects_negative_percent_under_discount_percent_field(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Минус',
            'lastname' => 'Процент',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => -1,
            'discount_comment' => 'Нельзя',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent']);
    }

    public function test_store_ajax_rejects_comment_longer_than_500_under_discount_comment_field(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Длинное',
            'lastname' => 'Основание',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 10,
            'discount_comment' => str_repeat('а', 501),
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_comment']);
    }

    public function test_store_ajax_accepts_100_percent_with_comment(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name' => 'Сто',
            'lastname' => 'Процентов',
            'role_id' => $this->studentRoleId(),
            'is_enabled' => '1',
            'discount_percent' => 100,
            'discount_comment' => 'Полная льгота',
        ], $this->ajaxHeaders())
            ->assertOk();

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Сто')
            ->firstOrFail();

        $this->assertSame(100, (int) $student->discount_percent);
        $this->assertSame('Полная льгота', $student->discount_comment);
    }

    public function test_update_ajax_validation_returns_422_with_discount_percent_field_error(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'discount_percent' => 10,
            'discount_comment' => 'Было',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name' => $student->name,
            'lastname' => $student->lastname,
            'role_id' => $student->role_id,
            'is_enabled' => '1',
            'discount_percent' => '7.5',
            'discount_comment' => 'Дробь',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent']);

        $student->refresh();
        $this->assertSame(10, (int) $student->discount_percent);
    }
}
