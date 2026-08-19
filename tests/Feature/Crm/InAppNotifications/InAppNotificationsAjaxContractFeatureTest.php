<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\InAppNotifications;

use App\Models\InAppNotification;

/**
 * P1: JSON-контракт колокольчика, прочтения и списка ролей формы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class InAppNotificationsAjaxContractFeatureTest extends InAppNotificationsTestCase
{
    public function test_bell_json_returns_three_latest_items_with_page_url_and_without_action_url(): void
    {
        $admin = $this->createUserWithRole('admin');
        foreach (['Первое', 'Второе', 'Третье', 'Четвёртое', 'Пятое'] as $title) {
            $this->dispatchToRoles(
                [$this->partner->id],
                [$this->roleId('admin')],
                false,
                ['title' => $title]
            );
        }

        $this->actingInPartner($admin);
        $response = $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 5)
            ->assertJsonStructure([
                'unread_count',
                'items' => [
                    [
                        'id',
                        'title',
                        'body',
                        'body_preview',
                        'category',
                        'category_label',
                        'is_read',
                        'created_at',
                        'created_at_human',
                        'page_url',
                    ],
                ],
            ]);

        $items = $response->json('items');
        $this->assertCount(3, $items);
        $this->assertSame(['Пятое', 'Четвёртое', 'Третье'], array_column($items, 'title'));

        foreach ($items as $item) {
            $this->assertSame(
                route('inAppNotifications.index', ['n' => $item['id']]),
                $item['page_url']
            );
            $this->assertArrayNotHasKey('open_url', $item);
            $this->assertArrayNotHasKey('action_url', $item);
        }
    }

    public function test_bell_preview_is_half_as_long_and_date_is_human_russian(): void
    {
        $admin = $this->createUserWithRole('admin');
        $body = str_repeat('А', 90);
        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Длинный текст', 'body' => $body]
        );

        $this->actingInPartner($admin);
        $item = $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->json('items.0');

        $this->assertSame($notification->id, $item['id']);
        $this->assertLessThanOrEqual(63, mb_strlen((string) $item['body_preview']));
        $this->assertStringEndsWith('...', (string) $item['body_preview']);
        $this->assertSame(
            $notification->created_at->timezone('Europe/Moscow')->locale('ru')->translatedFormat('j F Y'),
            $item['created_at_human']
        );
        $this->assertDoesNotMatchRegularExpression('/\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}/', (string) $item['created_at_human']);
    }

    public function test_bell_preview_keeps_line_breaks_from_paragraphs(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            [
                'title' => 'С переносами',
                'body' => '<p>Первая строка</p><p>Вторая строка</p>',
            ]
        );

        $this->actingInPartner($admin);
        $item = $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->json('items.0');

        $this->assertSame("Первая строка\nВторая строка", $item['body_preview']);
        $this->assertStringNotContainsString('<p>', (string) $item['body_preview']);
    }

    public function test_ajax_mark_read_returns_updated_counter_and_items(): void
    {
        $admin = $this->createUserWithRole('admin');
        $first = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'A']
        );
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'B']
        );

        $this->actingInPartner($admin);
        $this->postJson(route('inAppNotifications.read', $first), [])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonStructure(['success', 'unread_count', 'items']);

        $this->assertDatabaseHas('in_app_notification_reads', [
            'in_app_notification_id' => $first->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_ajax_read_all_clears_counter_without_empty_200(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'К прочтению']
        );

        $this->actingInPartner($admin);
        $response = $this->postJson(route('inAppNotifications.readAll'), [])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_ajax_store_validation_returns_field_errors(): void
    {
        $this->asSuperadminReady();

        $this->postJson(route('inAppNotifications.store'), [
            'all_partners' => '0',
            'ttl_preset' => '7d',
            'category' => 'normal',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body', 'role_ids']);

        $this->postJson(route('inAppNotifications.store'), [
            'title' => 'Заголовок',
            'body' => 'Текст',
            'category' => 'unknown',
            'all_partners' => '1',
            'role_ids' => [$this->roleId('admin')],
            'ttl_preset' => '7d',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);

        $this->postJson(route('inAppNotifications.store'), [
            'title' => 'Своя дата',
            'body' => 'Текст',
            'category' => 'normal',
            'all_partners' => '1',
            'role_ids' => [$this->roleId('admin')],
            'ttl_preset' => 'custom',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_expires_at']);

        $this->postJson(route('inAppNotifications.store'), [
            'title' => 'Прошлая дата',
            'body' => 'Текст',
            'category' => 'normal',
            'all_partners' => '1',
            'role_ids' => [$this->roleId('admin')],
            'ttl_preset' => 'custom',
            'custom_expires_at' => now('Europe/Moscow')->subDay()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_expires_at']);
    }

    public function test_ajax_roles_endpoint_returns_system_roles_for_all_schools(): void
    {
        $custom = $this->createCustomRole($this->partner, 'Менеджер школы');
        $this->asSuperadminReady();

        $roles = $this->getJson(route('inAppNotifications.compose.roles', [
            'all_partners' => 1,
        ]))
            ->assertOk()
            ->assertJsonStructure(['roles' => [['id', 'name', 'label', 'is_sistem']]])
            ->json('roles');

        $ids = array_column($roles, 'id');
        $this->assertContains($this->roleId('admin'), $ids);
        $this->assertContains($this->roleId('user'), $ids);
        $this->assertContains($this->roleId('trainer'), $ids);
        $this->assertNotContains($custom->id, $ids);
        $this->assertNotContains($this->roleId('superadmin'), $ids);
    }

    public function test_ajax_roles_rejects_unknown_school(): void
    {
        $this->asSuperadminReady();

        $this->getJson(route('inAppNotifications.compose.roles', [
            'all_partners' => 0,
            'partner_ids' => [999999],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_ids.0']);
    }
}
