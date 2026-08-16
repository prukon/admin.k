<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\InAppNotifications;

use App\Models\InAppNotification;

/**
 * P1: non-AJAX POST формы ленты и создания — 302, запись есть, не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class InAppNotificationsNonAjaxSafetyNetFeatureTest extends InAppNotificationsTestCase
{
    public function test_store_non_ajax_redirects_to_inbox_and_queues_notification(): void
    {
        $this->asSuperadminReady();

        $response = $this->from(route('inAppNotifications.compose'))
            ->post(route('inAppNotifications.store'), [
                'title' => 'Non-ajax рассылка',
                'body' => 'Текст <a href="/cabinet">кабинет</a>',
                'category' => 'update',
                'all_partners' => '0',
                'partner_ids' => [$this->partner->id],
                'role_ids' => [$this->roleId('admin')],
                'ttl_preset' => '1d',
                'action_url' => '/cabinet',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Форма создания — обычный POST, не пустой 200');
        $response->assertRedirect(route('inAppNotifications.index'));

        $this->assertDatabaseHas('in_app_notifications', [
            'title' => 'Non-ajax рассылка',
            'category' => InAppNotification::CATEGORY_UPDATE,
            'is_global' => 0,
            'status' => InAppNotification::STATUS_PENDING,
            'action_url' => null,
        ]);
        $this->assertDatabaseHas('jobs', ['queue' => 'default']);
    }

    public function test_store_non_ajax_validation_returns_to_compose_with_field_errors(): void
    {
        $this->asSuperadminReady();

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

    public function test_mark_read_without_ajax_header_redirects_to_inbox_and_persists(): void
    {
        $admin = $this->createUserWithRole('admin');
        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Прочитать формой']
        );

        $this->actingInPartner($admin);
        $response = $this->from(route('inAppNotifications.index'))
            ->post(route('inAppNotifications.read', $notification));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('inAppNotifications.index'));

        $this->assertDatabaseHas('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_mark_all_read_without_ajax_header_redirects_and_clears_unread(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Пачка']
        );

        $this->actingInPartner($admin);
        $this->from(route('inAppNotifications.index'))
            ->post(route('inAppNotifications.readAll'))
            ->assertRedirect(route('inAppNotifications.index'));

        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_custom_ttl_non_ajax_persists_end_of_moscow_day(): void
    {
        $this->asSuperadminReady();
        $date = now('Europe/Moscow')->addDays(3)->toDateString();

        $this->post(route('inAppNotifications.store'), [
            'title' => 'До даты',
            'body' => 'Текст',
            'category' => 'normal',
            'all_partners' => '1',
            'role_ids' => [$this->roleId('admin')],
            'ttl_preset' => 'custom',
            'custom_expires_at' => $date,
        ])->assertRedirect(route('inAppNotifications.index'));

        $row = InAppNotification::query()->where('title', 'До даты')->first();
        $this->assertNotNull($row);
        $this->assertSame(InAppNotification::TTL_CUSTOM, $row->ttl_preset);
        $this->assertSame(
            $date.' 23:59:59',
            $row->expires_at?->timezone('Europe/Moscow')->format('Y-m-d H:i:s')
        );
    }
}
