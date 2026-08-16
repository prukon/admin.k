<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\InAppNotifications;

use App\Models\InAppNotification;

/**
 * P1: доступ ко всем endpoint'ам колокольчика — гость, без права, роли, manage ≠ compose.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class InAppNotificationsAccessFeatureTest extends InAppNotificationsTestCase
{
    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function protectedEndpoints(int $notificationId): array
    {
        return [
            ['GET', 'inAppNotifications.index', []],
            ['GET', 'inAppNotifications.bell', []],
            ['POST', 'inAppNotifications.readAll', []],
            ['GET', 'inAppNotifications.compose', []],
            ['GET', 'inAppNotifications.compose.roles', ['all_partners' => 1]],
            ['POST', 'inAppNotifications.store', ['title' => 'X', 'body' => 'Y']],
            ['POST', 'inAppNotifications.read', ['notification' => $notificationId]],
            ['GET', 'inAppNotifications.open', ['notification' => $notificationId]],
        ];
    }

    public function test_guest_is_redirected_from_every_inbox_endpoint(): void
    {
        auth()->logout();
        $placeholderId = 1;

        foreach ($this->protectedEndpoints($placeholderId) as [$method, $name, $params]) {
            $response = $this->hitEndpoint($method, $name, $params);
            $this->assertNotSame(500, $response->getStatusCode(), $name.' не должен отдавать 500 гостю');
            $this->assertTrue(
                $response->isRedirect(),
                $name.' для гостя должен редиректить на логин, статус '.$response->getStatusCode()
            );
        }
        $this->assertGuest();
    }

    public function test_user_without_view_cannot_hit_any_endpoint(): void
    {
        $user = $this->createUserWithoutPermission('inAppNotifications.view');
        $this->actingInPartner($user);

        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false
        );

        foreach ($this->protectedEndpoints((int) $notification->id) as [$method, $name, $params]) {
            $response = $this->hitEndpoint($method, $name, $params);
            $this->assertSame(
                403,
                $response->getStatusCode(),
                $name.' без inAppNotifications.view должен быть 403'
            );
        }
    }

    public function test_trainer_and_student_with_view_can_open_inbox_and_bell(): void
    {
        $trainer = $this->createUserWithRole('trainer');
        $student = $this->createUserWithRole('user');
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('trainer'), $this->roleId('user')],
            false,
            ['title' => 'Для тренера и ученика']
        );

        $this->actingInPartner($trainer);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Для тренера и ученика');
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingInPartner($student);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Для тренера и ученика');
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_admin_with_manage_permission_still_cannot_compose(): void
    {
        $this->grantManageToRole('admin');
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('inAppNotifications.compose'))->assertForbidden();
        $this->getJson(route('inAppNotifications.compose.roles', ['all_partners' => 1]))->assertForbidden();
        $this->post(route('inAppNotifications.store'), [
            'title' => 'Обход',
            'body' => 'Текст',
            'category' => 'normal',
            'all_partners' => '1',
            'role_ids' => [$this->roleId('admin')],
            'ttl_preset' => '7d',
        ])->assertForbidden();

        $this->assertDatabaseMissing('in_app_notifications', ['title' => 'Обход']);
    }

    public function test_opening_someone_elses_notification_is_not_found(): void
    {
        $admin = $this->createUserWithRole('admin');
        $foreign = $this->dispatchToRoles(
            [$this->foreignPartner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Чужое']
        );

        $this->actingInPartner($admin);
        $this->get(route('inAppNotifications.open', $foreign))->assertNotFound();
        $this->post(route('inAppNotifications.read', $foreign))->assertNotFound();
        $this->postJson(route('inAppNotifications.read', $foreign))->assertNotFound();
    }

    public function test_inbox_highlight_of_invisible_notification_does_not_500_or_mark_read(): void
    {
        $admin = $this->createUserWithRole('admin');
        $foreign = $this->dispatchToRoles(
            [$this->foreignPartner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Невидимое']
        );

        $this->actingInPartner($admin);
        $this->get(route('inAppNotifications.index', ['n' => $foreign->id]))
            ->assertOk()
            ->assertDontSee('Невидимое')
            ->assertDontSee('is-focused', false);

        $this->assertDatabaseMissing('in_app_notification_reads', [
            'in_app_notification_id' => $foreign->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_disabled_user_is_not_in_snapshot_and_does_not_see_message(): void
    {
        $disabled = $this->createUserWithRole('admin', $this->partner, [
            'is_enabled' => 0,
        ]);
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Только включённым']
        );

        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'user_id' => $disabled->id,
        ]);
    }

    public function test_superadmin_is_not_in_recipient_snapshot(): void
    {
        $this->asSuperadminReady();
        $notification = $this->dispatchToRoles(
            [],
            [$this->roleId('admin')],
            true,
            ['title' => 'Глобально админам']
        );

        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertSame(InAppNotification::STATUS_DISPATCHED, $notification->status);
    }

    private function hitEndpoint(string $method, string $name, array $params)
    {
        $url = route($name, $params);

        return strtoupper($method) === 'GET' ? $this->get($url) : $this->post($url);
    }
}
