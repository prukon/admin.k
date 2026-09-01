<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * P1: UX ленты, колокольчика и карточки договора — разметка, @can, просрочка 7 суток.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSignedInAppNotificationUiContractsFeatureTest extends ContractSignedInAppNotificationTestCase
{
    public function test_inbox_renders_student_name_as_text_without_contract_link_and_hides_normal_badge(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Договор подписан', false)
            ->assertSee('непрочитано', false)
            ->getContent();

        $showHref = route('contracts.show', $contract, false);
        $this->assertStringContainsString('Комарова Ярослав. Договор №'.$contract->id.' подписан.', $html);
        $this->assertStringNotContainsString('Родитель:', $html);
        $this->assertStringNotContainsString('href="'.$showHref.'"', $html);
        $this->assertStringNotContainsString('>'.$student->full_name.'</a>', $html);
        $this->assertStringNotContainsString('ian-badge ian-badge--normal', $html);
        $this->assertStringNotContainsString('>Обычное</span>', $html);
        $this->assertStringNotContainsString('ian-badge ian-badge--important', $html);
    }

    public function test_inbox_renders_parent_and_child_lines_when_parent_name_is_set(): void
    {
        $student = $this->makeStudent();
        $this->attachParent($student);
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Родитель: Иванов Иван', false)
            ->assertSee('Ребёнок: Комарова Ярослав.', false)
            ->assertSee('Договор №'.$contract->id.' подписан.', false)
            ->assertDontSee('Комарова Ярослав. Договор', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/Родитель: Иванов Иван<br\s*\/?>/i', $html);
        $this->assertStringNotContainsString('href="'.route('contracts.show', $contract, false).'"', $html);
    }

    public function test_dashboard_bell_ssr_shows_event_without_normal_badge_and_links_to_inbox(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="inAppNotificationBell"', false)
            ->getContent();

        $bell = $this->bellMarkup($html);
        $this->assertStringContainsString('Договор подписан', $bell);
        $this->assertStringContainsString(
            'href="'.route('inAppNotifications.index', ['n' => $notification->id]).'"',
            $bell
        );
        $this->assertStringContainsString('Показать все', $bell);
        $this->assertStringNotContainsString('>Обычное</span>', $bell);
        $this->assertStringNotContainsString('ian-badge ian-badge--normal', $bell);
        $this->assertStringNotContainsString('js-in-app-bell-mark-read', $bell);
        $this->assertStringNotContainsString(route('contracts.show', $contract, false), $bell);
    }

    public function test_opening_inbox_without_click_does_not_mark_event_read(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('непрочитано', $html);
        $this->assertStringNotContainsString('is-focused', $html);
        $this->assertDatabaseMissing('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_click_from_bell_highlights_event_card_and_marks_read(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('inAppNotifications.index', ['n' => $notification->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="in-app-notification-'.$notification->id.'"', $html);
        $this->assertStringContainsString('is-focused', $html);
        $this->assertDatabaseHas('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        unset($notification);
    }

    public function test_staff_created_after_fanout_does_not_see_event(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $lateAdmin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Поздний',
            'name' => 'Админ',
        ]);
        $this->actingWith2fa($lateAdmin);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Договор подписан', false);
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_expired_event_after_seven_days_disappears_from_inbox_and_bell(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Carbon::setTestNow(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));
        try {
            Auth::logout();
            $this->postSignedWebhook($contract)->assertOk();
            $this->fanOutLatestEvent();

            Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:01', 'Europe/Moscow'));

            $this->actingWith2fa($admin);
            $this->get(route('inAppNotifications.index'))
                ->assertOk()
                ->assertDontSee('Договор подписан', false)
                ->assertSee('Пока тихо', false);
            $this->getJson(route('inAppNotifications.bell'))
                ->assertOk()
                ->assertJsonPath('unread_count', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_event_is_still_visible_before_seven_days_elapse(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Carbon::setTestNow(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));
        try {
            Auth::logout();
            $this->postSignedWebhook($contract)->assertOk();
            $this->fanOutLatestEvent();

            Carbon::setTestNow(Carbon::parse('2026-09-08 11:59:59', 'Europe/Moscow'));

            $this->actingWith2fa($admin);
            $this->get(route('inAppNotifications.index'))
                ->assertOk()
                ->assertSee('Договор подписан', false);
            $this->getJson(route('inAppNotifications.bell'))
                ->assertOk()
                ->assertJsonPath('unread_count', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_contract_show_renders_sync_button_when_admin_has_sync_permission(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        $this->actingWith2fa($admin);
        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertStringContainsString('id="syncStatusBtn"', $html);
        $this->assertStringContainsString('Синхронизировать с Подпислон', $html);
        $this->assertStringContainsString('data-id="'.$contract->id.'"', $html);
    }

    public function test_contract_show_hides_sync_button_when_admin_lacks_sync_permission(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        $this->actingWith2fa($admin);
        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="syncStatusBtn"', $html);
        $this->assertStringNotContainsString('Синхронизировать с Подпислон', $html);
    }

    public function test_contract_show_hides_sync_button_when_status_is_draft_without_provider(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student, [
            'status' => \App\Models\Contract::STATUS_DRAFT,
            'provider_doc_id' => null,
        ]);
        $admin = $this->grantSyncToAdmin();

        $this->actingWith2fa($admin);
        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="syncStatusBtn"', $html);
    }

    public function test_student_dashboard_does_not_show_school_admin_event(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($student);
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $bell = $this->bellMarkup($html);
        $this->assertStringNotContainsString('Договор подписан', $bell);
    }

    public function test_trainer_inbox_stays_empty_and_shows_quiet_placeholder(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $trainer = $this->createUserWithRole('trainer');
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($trainer);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Договор подписан', false)
            ->assertSee('Пока тихо', false);
    }

    public function test_dashboard_bell_preview_shows_parent_lines_with_pre_line(): void
    {
        $student = $this->makeStudent();
        $this->attachParent($student);
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $bell = $this->bellMarkup($html);

        $this->assertStringContainsString('white-space: pre-line', $html);
        $this->assertStringContainsString('bell-preview', $bell);
        $this->assertStringContainsString("Родитель: Иванов Иван\n", $bell);
        $this->assertStringContainsString('Договор подписан', $bell);
        $this->assertStringNotContainsString('>Обычное</span>', $bell);
    }

    public function test_contract_show_ssr_bell_shows_event_for_admin(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $bell = $this->bellMarkup($html);
        $this->assertStringContainsString('Договор подписан', $bell);
        $this->assertStringContainsString(
            'href="'.route('inAppNotifications.index', ['n' => $notification->id]).'"',
            $bell
        );
    }

    public function test_signed_without_school_admin_still_succeeds_and_superadmin_sees_event(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);

        Auth::logout();
        $this->postSignedWebhook($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $notification = $this->fanOutLatestEvent();
        $this->assertSame(0, (int) $notification->recipients_count);

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Договор подписан', false);
    }

    private function bellMarkup(string $html): string
    {
        if (! preg_match('/id="inAppNotificationBell"[\s\S]*?<\/li>/', $html, $match)) {
            $this->fail('На странице нет колокольчика inAppNotificationBell');
        }

        return $match[0];
    }
}
