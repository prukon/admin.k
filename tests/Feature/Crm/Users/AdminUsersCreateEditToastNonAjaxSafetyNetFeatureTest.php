<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

/**
 * Backend safety-net: non-AJAX POST/PATCH ученика → 302 на /admin/users + запись в БД.
 * Если JS модалки не сработал, не должно быть пустого 200 / белого экрана.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersCreateEditToastNonAjaxSafetyNetFeatureTest extends AdminUsersCreateEditToastTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUsersViewer();
    }

    public function test_store_non_ajax_redirects_and_creates_student(): void
    {
        $email = 'toast-nonajax-store-'.uniqid('', true).'@example.test';

        $response = $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), $this->studentPayload([
                'email' => $email,
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Create без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.user1'));

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'email'      => $email,
            'name'       => 'Иван',
            'lastname'   => 'Тостов',
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
            'lastname'   => 'Тостов',
            'name'       => 'Иван',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_student(): void
    {
        $student = $this->makeStudent(['name' => 'До']);

        $response = $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student->id), [
                'name'       => 'После',
                'lastname'   => 'Тостов',
                'role_id'    => $this->studentRoleId(),
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'PATCH без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.user1'));
        $this->assertSame('После', $student->fresh()->name);
    }

    public function test_update_non_ajax_validation_redirects_back_with_name_error(): void
    {
        $student = $this->makeStudent(['name' => 'До']);

        $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student->id), [
                'name'       => '',
                'lastname'   => 'Тостов',
                'role_id'    => $this->studentRoleId(),
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertSame('До', $student->fresh()->name);
    }
}
