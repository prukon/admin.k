<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\InAppNotifications;

use App\Models\InAppNotification;

/**
 * P1: UX ленты и выпадашки — плашки, даты, превью, клик, дефолты формы, JS-контракт.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class InAppNotificationsUiContractsFeatureTest extends InAppNotificationsTestCase
{
    public function test_opening_inbox_without_click_does_not_mark_notifications_read(): void
    {
        $admin = $this->createUserWithRole('admin');
        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Ещё не кликали']
        );

        $this->actingInPartner($admin);
        $html = $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Ещё не кликали')
            ->assertSee('непрочитано')
            ->assertSee('Прочитать все')
            ->getContent();

        $this->assertStringNotContainsString('is-focused', $html);
        $this->assertStringNotContainsString('scrollIntoView', $html);
        $this->assertDatabaseMissing('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_click_from_bell_highlights_card_marks_read_and_hides_unread_label(): void
    {
        $admin = $this->createUserWithRole('admin');
        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Кликнули']
        );

        $this->actingInPartner($admin);
        $html = $this->get(route('inAppNotifications.index', ['n' => $notification->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="in-app-notification-'.$notification->id.'"', $html);
        $this->assertStringContainsString('is-focused', $html);
        $this->assertStringContainsString('scrollIntoView', $html);
        $this->assertStringNotContainsString('непрочитано', $html);
        $this->assertDatabaseHas('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_inbox_shows_important_and_update_badges_but_hides_normal(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Важный инцидент', 'category' => InAppNotification::CATEGORY_IMPORTANT]
        );
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Релиз системы', 'category' => InAppNotification::CATEGORY_UPDATE]
        );
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Просто сообщение', 'category' => InAppNotification::CATEGORY_NORMAL]
        );

        $this->actingInPartner($admin);
        $html = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('ian-badge ian-badge--important', $html);
        $this->assertStringContainsString('>Важное</span>', $html);
        $this->assertStringContainsString('ian-badge ian-badge--update', $html);
        $this->assertStringContainsString('>Обновление</span>', $html);
        $this->assertStringNotContainsString('ian-badge ian-badge--normal', $html);
        $this->assertStringNotContainsString('>Обычное</span>', $html);
        $this->assertStringContainsString('Просто сообщение', $html);
    }

    public function test_inbox_date_uses_russian_day_month_year(): void
    {
        $admin = $this->createUserWithRole('admin');
        $notification = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Дата в ленте']
        );
        $expected = $notification->created_at
            ->timezone('Europe/Moscow')
            ->locale('ru')
            ->translatedFormat('j F Y');

        $this->actingInPartner($admin);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee($expected)
            ->assertDontSee($notification->created_at->timezone('Europe/Moscow')->format('d.m.Y H:i'));
    }

    public function test_dropdown_on_cabinet_uses_same_badges_links_to_inbox_and_hides_normal(): void
    {
        $admin = $this->createUserWithRole('admin');
        $important = $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Важно в колокольчике', 'category' => InAppNotification::CATEGORY_IMPORTANT]
        );
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            ['title' => 'Обычное в колокольчике', 'category' => InAppNotification::CATEGORY_NORMAL]
        );

        $this->actingInPartner($admin);
        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="inAppNotificationBell"', false)
            ->getContent();

        $this->assertStringContainsString('ian-badge ian-badge--important', $html);
        $this->assertStringContainsString('>Важное</span>', $html);
        $this->assertStringNotContainsString('ian-badge ian-badge--normal', $html);
        $this->assertStringNotContainsString('js-in-app-bell-mark-read', $html);
        $this->assertStringContainsString(
            'href="'.route('inAppNotifications.index', ['n' => $important->id]).'"',
            $html
        );
        $this->assertStringContainsString('Показать все', $html);

        $bellChunk = $this->bellMarkup($html);
        $this->assertStringNotContainsString('>Обычное</span>', $bellChunk);
        $this->assertStringContainsString('Обычное в колокольчике', $bellChunk);
    }

    public function test_dropdown_shows_three_items_and_admin_does_not_see_compose_button(): void
    {
        $admin = $this->createUserWithRole('admin');
        foreach (['N1', 'N2', 'N3', 'N4'] as $title) {
            $this->dispatchToRoles(
                [$this->partner->id],
                [$this->roleId('admin')],
                false,
                ['title' => $title]
            );
        }

        $this->actingInPartner($admin);
        $cabinet = $this->get(route('dashboard'))->assertOk()->getContent();
        $bellChunk = $this->bellMarkup($cabinet);
        $this->assertSame(3, substr_count($bellChunk, 'in-app-bell-item'));
        $this->assertStringContainsString('N4', $bellChunk);
        $this->assertStringNotContainsString('N1', $bellChunk);

        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee(route('inAppNotifications.compose'), false)
            ->assertSee('N1');
    }

    public function test_superadmin_compose_first_open_defaults_and_has_no_standalone_url(): void
    {
        $this->asSuperadminReady();
        $html = $this->get(route('inAppNotifications.compose'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="inAppNotificationComposeForm"', $html);
        $this->assertStringContainsString('name="title"', $html);
        $this->assertStringContainsString('id="inAppBody"', $html);
        $this->assertStringContainsString("['insert', ['link']]", $html);
        $this->assertStringNotContainsString('name="action_url"', $html);
        $this->assertStringContainsString('Выделите фрагмент и нажмите иконку ссылки', $html);

        $this->assertMatchesRegularExpression(
            '/name="category"[^>]*>[\s\S]*value="normal"[^>]*\bselected\b|\bselected\b[^>]*value="normal"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="inAppTtlPreset"[\s\S]*value="7d"[^>]*\bselected\b|\bselected\b[^>]*value="7d"/',
            $html
        );
        $this->assertTrue(
            (bool) preg_match('/id="inAppAllPartners"[\s\S]*?>/', $html, $checkbox),
            'Нет чекбокса «Все школы»'
        );
        $this->assertStringNotContainsString('checked', $checkbox[0]);
        $this->assertStringContainsString('id="inAppPartnersWrap"', $html);
        $this->assertStringContainsString('id="inAppCustomExpiresWrap"', $html);

        $titlePos = strpos($html, 'name="title"') ?: PHP_INT_MAX;
        $bodyPos = strpos($html, 'id="inAppBody"') ?: PHP_INT_MAX;
        $categoryPos = strpos($html, 'name="category"') ?: PHP_INT_MAX;
        $partnersPos = strpos($html, 'name="partner_ids[]"') ?: PHP_INT_MAX;
        $rolesPos = strpos($html, 'name="role_ids[]"') ?: PHP_INT_MAX;
        $ttlPos = strpos($html, 'name="ttl_preset"') ?: PHP_INT_MAX;
        $this->assertLessThan($bodyPos, $titlePos);
        $this->assertLessThan($categoryPos, $bodyPos);
        $this->assertLessThan($partnersPos, $categoryPos);
        $this->assertLessThan($rolesPos, $partnersPos);
        $this->assertLessThan($ttlPos, $rolesPos);

        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee(route('inAppNotifications.compose'), false);
    }

    public function test_compose_and_bell_js_keep_agreed_behaviour_on_rebuild(): void
    {
        $compose = (string) file_get_contents(resource_path('views/admin/in_app_notifications/compose.blade.php'));
        $echo = (string) file_get_contents(resource_path('views/includes/in_app_notifications/echo.blade.php'));
        $item = (string) file_get_contents(resource_path('views/includes/in_app_notifications/bell_item.blade.php'));

        $this->assertStringContainsString('togglePartners', $compose);
        $this->assertStringContainsString("partnersWrap.style.display = checked ? 'none' : ''", $compose);
        $this->assertStringContainsString("ttlPreset.value === 'custom'", $compose);
        $this->assertStringContainsString('reloadRoles', $compose);
        $this->assertStringContainsString("selected.indexOf(String(role.id)) !== -1", $compose);
        $this->assertStringContainsString("\$body.val(\$body.summernote('code'))", $compose);
        $this->assertStringNotContainsString('preventDefault', $compose);
        $this->assertStringNotContainsString('name="action_url"', $compose);
        $this->assertStringContainsString("['insert', ['link']]", $compose);

        $this->assertStringContainsString('item.page_url', $echo);
        $this->assertStringContainsString("category === 'update' || category === 'important'", $echo);
        $this->assertStringNotContainsString('open_url', $echo);
        $this->assertStringNotContainsString('js-in-app-bell-mark-read', $echo);
        $this->assertStringNotContainsString('action_url', $echo);

        $this->assertStringContainsString('page_url', $item);
        $this->assertStringContainsString("in_array(\$category, ['update', 'important'], true)", $item);
        $this->assertStringNotContainsString('js-in-app-bell-mark-read', $item);
    }

    public function test_empty_inbox_shows_quiet_state_without_read_all(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);

        $html = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Пока тихо', $html);
        $this->assertStringNotContainsString('Прочитать все', $html);
        $this->assertStringNotContainsString('ian-feed', $html);
    }

    public function test_https_link_in_body_opens_in_new_tab_internal_stays_in_crm(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->dispatchToRoles(
            [$this->partner->id],
            [$this->roleId('admin')],
            false,
            [
                'title' => 'Ссылки в тексте',
                'body' => 'Внутри <a href="/cabinet">кабинет</a> и снаружи <a href="https://example.com/help">сайт</a>',
            ]
        );

        $this->actingInPartner($admin);
        $html = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('href="/cabinet"', $html);
        $this->assertStringContainsString('href="https://example.com/help"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);

        $this->assertDoesNotMatchRegularExpression(
            '/href="\/cabinet"[^>]*target="_blank"/',
            $html
        );
    }

    private function bellMarkup(string $html): string
    {
        if (! preg_match('/id="inAppNotificationBell"[\s\S]*?<\/li>/', $html, $match)) {
            $this->fail('На странице нет колокольчика inAppNotificationBell');
        }

        return $match[0];
    }
}
