<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\InAppNotifications;

use App\Models\InAppNotification;

final class InAppNotificationsFeatureTest extends InAppNotificationsTestCase
{
    public function test_guest_is_redirected_from_inbox(): void
    {
        auth()->logout();

        $this->get(route('inAppNotifications.index'))
            ->assertRedirect();
        $this->assertGuest();
    }

    public function test_user_without_view_permission_gets_403(): void
    {
        $user = $this->createUserWithoutPermission('inAppNotifications.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('inAppNotifications.index'))->assertForbidden();
        $this->get(route('inAppNotifications.compose'))->assertForbidden();
    }

    public function test_admin_sees_bell_and_inbox_but_cannot_compose(): void
    {
        $this->asAdmin();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="inAppNotificationBell"', false);

        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Уведомления')
            ->assertDontSee(route('inAppNotifications.compose'), false);

        $this->get(route('inAppNotifications.compose'))->assertForbidden();
        $this->post(route('inAppNotifications.store'), [])->assertForbidden();
    }

    public function test_superadmin_compose_validates_fields(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->from(route('inAppNotifications.compose'))
            ->post(route('inAppNotifications.store'), [
                'all_partners' => '0',
                'ttl_preset' => '7d',
                'category' => 'normal',
            ])
            ->assertRedirect(route('inAppNotifications.compose'))
            ->assertSessionHasErrors(['title', 'body', 'role_ids']);

        $this->from(route('inAppNotifications.compose'))
            ->post(route('inAppNotifications.store'), [
                'title' => 'Заголовок',
                'body' => 'Текст',
                'category' => 'normal',
                'all_partners' => '0',
                'role_ids' => [$this->roleId('admin')],
                'ttl_preset' => '7d',
            ])
            ->assertRedirect(route('inAppNotifications.compose'))
            ->assertSessionHasErrors(['partner_ids']);
    }

    public function test_superadmin_can_send_to_admins_of_one_school(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('user');
        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);

        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Инцидент эквайринга', 'category' => InAppNotification::CATEGORY_IMPORTANT]
        );

        $this->assertSame(InAppNotification::STATUS_DISPATCHED, $notification->status);
        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $foreignAdmin->id,
        ]);

        $this->actingAs($admin);
        $this->withSession(['current_partner' => $this->partner->id]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Инцидент эквайринга');

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Инцидент эквайринга');

        $this->actingAs($foreignAdmin);
        $this->withSession(['current_partner' => $this->foreignPartner->id]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Инцидент эквайринга');
    }

    public function test_user_created_after_dispatch_does_not_see_notification(): void
    {
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Старое объявление']
        );

        $lateAdmin = $this->createUserWithRole('admin');

        $this->actingAs($lateAdmin);
        $this->withSession(['current_partner' => $this->partner->id]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Старое объявление');
    }

    public function test_superadmin_sees_current_school_plus_global_only(): void
    {
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Только школа A']
        );
        $this->dispatchToRoles(
            [$this->foreignPartner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Только школа B']
        );
        $this->dispatchToRoles(
            [],
            [$this->roleId('admin')],
            true,
            ['title' => 'Глобальное обновление', 'category' => InAppNotification::CATEGORY_UPDATE]
        );

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Только школа A', $html);
        $this->assertStringContainsString('Глобальное обновление', $html);
        $this->assertStringNotContainsString('Только школа B', $html);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);
        $htmlB = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Только школа B', $htmlB);
        $this->assertStringContainsString('Глобальное обновление', $htmlB);
        $this->assertStringNotContainsString('Только школа A', $htmlB);
    }

    public function test_expired_notification_is_hidden_until_read_stays(): void
    {
        $admin = $this->createUserWithRole('admin');
        $expired = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Просрочено', 'ttl_preset' => InAppNotification::TTL_CUSTOM, 'custom_expires_at' => now()->toDateString()]
        );
        $expired->update(['expires_at' => now()->subMinute()]);

        $untilRead = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Пока не прочитают', 'ttl_preset' => InAppNotification::TTL_UNTIL_READ]
        );
        $this->assertNull($untilRead->expires_at);

        $this->actingAs($admin);
        $this->withSession(['current_partner' => $this->partner->id]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Просрочено')
            ->assertSee('Пока не прочитают');
    }

    public function test_mark_read_is_per_user_and_mark_all_clears_counter(): void
    {
        $adminA = $this->createUserWithRole('admin');
        $adminB = $this->createUserWithRole('admin');
        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Общее']
        );

        $this->actingAs($adminA);
        $this->withSession(['current_partner' => $this->partner->id]);
        $this->postJson(route('inAppNotifications.read', $notification), [])
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($adminB);
        $this->withSession(['current_partner' => $this->partner->id]);
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->postJson(route('inAppNotifications.readAll'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_clicking_notification_marks_read_and_opens_inbox(): void
    {
        $admin = $this->createUserWithRole('admin');
        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Кликнули в колокольчике']
        );

        $this->actingAs($admin);
        $this->withSession(['current_partner' => $this->partner->id]);

        $bell = $this->getJson(route('inAppNotifications.bell'))->assertOk()->json();
        $this->assertNotEmpty($bell['items']);
        $this->assertSame($notification->id, $bell['items'][0]['id']);
        $this->assertSame(
            route('inAppNotifications.index', ['n' => $notification->id]),
            $bell['items'][0]['page_url']
        );

        $this->get(route('inAppNotifications.open', $notification))
            ->assertRedirect(route('inAppNotifications.index', ['n' => $notification->id]));

        $this->get(route('inAppNotifications.index', ['n' => $notification->id]))
            ->assertOk()
            ->assertSee('id="in-app-notification-'.$notification->id.'"', false)
            ->assertSee('is-focused', false);

        $this->assertDatabaseHas('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_body_keeps_safe_links_and_strips_xss(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            [
                'title' => 'Со ссылкой внутри',
                'body' => 'Откройте <a href="/cabinet">кабинет</a> и <a href="javascript:alert(1)">xss</a><script>alert(1)</script>',
            ]
        );

        $this->actingAs($admin);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('href="/cabinet"', $html);
        $this->assertStringContainsString('>кабинет</a>', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('name="action_url"', $html);
    }

    public function test_compose_has_no_standalone_url_and_rejects_blank_html(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('inAppNotifications.compose'))
            ->assertOk()
            ->assertDontSee('name="action_url"', false)
            ->assertSee('Выделите фрагмент и нажмите иконку ссылки', false);

        $this->from(route('inAppNotifications.compose'))
            ->post(route('inAppNotifications.store'), [
                'title' => 'Пустое тело',
                'body' => '<p><br></p>',
                'category' => 'normal',
                'all_partners' => '1',
                'role_ids' => [$this->roleId('admin')],
                'ttl_preset' => '7d',
            ])
            ->assertRedirect(route('inAppNotifications.compose'))
            ->assertSessionHasErrors(['body']);
    }

    public function test_custom_role_is_available_only_for_single_school(): void
    {
        $custom = $this->createCustomRole($this->partner, 'Менеджер');
        $foreignCustom = $this->createCustomRole($this->foreignPartner, 'Чужой менеджер');

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $oneSchool = $this->getJson(route('inAppNotifications.compose.roles', [
            'all_partners' => 0,
            'partner_ids' => [$this->partner->id],
        ]))->assertOk()->json('roles');
        $oneIds = array_column($oneSchool, 'id');
        $this->assertContains($custom->id, $oneIds);
        $this->assertNotContains($foreignCustom->id, $oneIds);

        $multi = $this->getJson(route('inAppNotifications.compose.roles', [
            'all_partners' => 0,
            'partner_ids' => [$this->partner->id, $this->foreignPartner->id],
        ]))->assertOk()->json('roles');
        $multiIds = array_column($multi, 'id');
        $this->assertNotContains($custom->id, $multiIds);
        $this->assertContains($this->roleId('admin'), $multiIds);

        $this->from(route('inAppNotifications.compose'))
            ->post(route('inAppNotifications.store'), [
                'title' => 'Кастом',
                'body' => 'Текст',
                'category' => 'normal',
                'all_partners' => '0',
                'partner_ids' => [$this->partner->id, $this->foreignPartner->id],
                'role_ids' => [$custom->id],
                'ttl_preset' => '7d',
            ])
            ->assertRedirect(route('inAppNotifications.compose'))
            ->assertSessionHasErrors(['role_ids']);
    }

    public function test_http_store_queues_fanout_for_superadmin(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->post(route('inAppNotifications.store'), [
            'title' => 'Обновление системы',
            'body' => 'Завтра работы на сервере.',
            'category' => 'update',
            'all_partners' => '1',
            'role_ids' => [$this->roleId('admin')],
            'ttl_preset' => '7d',
        ])->assertRedirect(route('inAppNotifications.index'));

        $this->assertDatabaseHas('in_app_notifications', [
            'title' => 'Обновление системы',
            'is_global' => 1,
            'status' => InAppNotification::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('jobs', [
            'queue' => 'default',
        ]);
    }
}
