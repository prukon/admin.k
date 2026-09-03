<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use Illuminate\Support\Facades\Auth;

/**
 * Доступ к create/edit ученика, чей успех UI показывает toast без reload:
 * гость, без users.view, со правом — не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersCreateEditToastAccessFeatureTest extends AdminUsersCreateEditToastTestCase
{
    public function test_guest_cannot_open_users_or_create_or_update_student(): void
    {
        Auth::logout();
        $student = $this->makeStudent();

        $webPage = $this->get(route('admin.user1'));
        $this->assertContains($webPage->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $webPage->getStatusCode());
        $this->assertNotSame(200, $webPage->getStatusCode(), 'Гость не должен видеть /admin/users');

        $jsonPage = $this->getJson(route('admin.user1'));
        $this->assertContains($jsonPage->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $jsonPage->getStatusCode());

        $guestEmail = 'guest-toast-store-'.uniqid('', true).'@example.test';
        $store = $this->postJson(route('admin.user.store'), $this->studentPayload([
            'email' => $guestEmail,
        ]), $this->ajaxHeaders());
        $this->assertContains($store->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $store->getStatusCode(), 'Гость не должен создать клиента');
        $this->assertNotSame(500, $store->getStatusCode());
        $this->assertDatabaseMissing('users', ['email' => $guestEmail]);

        $update = $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => 'Взлом',
            'lastname'   => 'Тостов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders());
        $this->assertContains($update->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $update->getStatusCode(), 'Гость не должен обновить клиента');
        $this->assertNotSame(500, $update->getStatusCode());
        $this->assertSame('Ученик', $student->fresh()->name);
    }

    public function test_manager_without_users_view_gets_403_on_create_and_update(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $student = $this->makeStudent();

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson(route('admin.user1'))->assertForbidden();

        $this->postJson(route('admin.user.store'), $this->studentPayload([
            'email' => 'forbidden-toast-'.uniqid('', true).'@example.test',
        ]), $this->ajaxHeaders())->assertForbidden();

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => 'Запрет',
            'lastname'   => 'Тостов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertForbidden();

        $this->assertSame('Ученик', $student->fresh()->name);
        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'lastname'   => 'Тостов',
        ]);
    }

    public function test_authorized_user_opens_users_page_with_hidden_toast_and_can_mutate_student(): void
    {
        $this->actingAsUsersViewer();

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="kidsMainToast"[^>]*\bshow\b/',
            $html,
            'При первом открытии всплывайка не должна быть уже показана'
        );

        $email = 'toast-access-store-'.uniqid('', true).'@example.test';
        $store = $this->postJson(route('admin.user.store'), $this->studentPayload([
            'email' => $email,
        ]), $this->ajaxHeaders());
        $this->assertNotSame(500, $store->getStatusCode());
        $store->assertOk();
        $this->assertNotSame('', trim((string) $store->getContent()));

        $id = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $id);

        $update = $this->patchJson(route('admin.user.update', $id), [
            'name'       => 'После',
            'lastname'   => 'Тостов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders());
        $this->assertNotSame(500, $update->getStatusCode());
        $update->assertOk();
        $this->assertNotSame('', trim((string) $update->getContent()));
    }
}
