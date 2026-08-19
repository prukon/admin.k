<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Backend safety-net: non-AJAX POST/PATCH ученика → 302 на /admin/users + запись в БД.
 * Если JS модалки не сработал, не должно быть пустого 200 / белого экрана.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersClientNamingNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_store_non_ajax_redirects_and_creates_student(): void
    {
        $email = 'client-nonajax-store-' . uniqid('', true) . '@example.test';

        $response = $this->post(route('admin.user.store'), [
            'name'       => 'NonAjax',
            'lastname'   => 'Клиентов',
            'email'      => $email,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ]);

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'email'      => $email,
            'name'       => 'NonAjax',
            'lastname'   => 'Клиентов',
            'role_id'    => $this->studentRoleId(),
        ]);
    }

    public function test_store_non_ajax_validation_redirects_back_with_name_and_lastname_errors(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'role_id'    => $this->studentRoleId(),
                'is_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name', 'lastname']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'Клиентов',
            'name'       => 'NonAjax',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_student(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'До',
            'lastname'   => 'NonAjax',
        ]);

        $response = $this->patch(route('admin.user.update', $student->id), [
            'name'       => 'После',
            'lastname'   => 'NonAjax',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ]);

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame('После', $student->fresh()->name);
    }

    public function test_update_non_ajax_validation_redirects_back_with_name_error(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'До',
            'lastname'   => 'NonAjax',
        ]);

        $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student->id), [
                'name'       => '',
                'lastname'   => 'NonAjax',
                'role_id'    => $this->studentRoleId(),
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertSame('До', $student->fresh()->name);
    }

    public function test_delete_without_ajax_header_still_removes_student_and_returns_client_json(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Удалить',
            'lastname'   => 'NonAjax',
        ]);

        $response = $this->delete(route('admin.user.delete', $student->id));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk()
            ->assertJsonPath('success', 'Клиент успешно удалён');

        $this->assertSoftDeleted('users', ['id' => $student->id]);
    }
}
