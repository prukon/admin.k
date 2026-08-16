<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\ParentProfile;
use Illuminate\Support\Carbon;

/**
 * P1: UX ленты и колокольчика после attach — ФИО текстом без ссылки, без плашки «Обычное»,
 * SSR колокольчика, клик ?n=, просрочка, сотрудник после рассылки не видит.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class CabinetTeamAttachInAppNotificationUiContractsFeatureTest extends CabinetTeamAttachInAppNotificationTestCase
{
    public function test_inbox_renders_student_name_as_text_without_user_link_and_hides_normal_badge(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Ученик добавил группу', false)
            ->assertSee('непрочитано', false)
            ->getContent();

        $editHref = route('admin.user.edit', $student, false);
        $this->assertStringContainsString((string) $student->full_name, $html);
        $this->assertStringContainsString('Тестов Ученик добавил группу «НовГруппа»', $html);
        $this->assertStringNotContainsString('Родитель:', $html);
        $this->assertStringNotContainsString('href="'.$editHref.'"', $html);
        $this->assertStringNotContainsString('>'.$student->full_name.'</a>', $html);
        $this->assertStringContainsString((string) $candidate->title, $html);
        $this->assertStringContainsString('Тестовый объект', $html);
        $this->assertStringContainsString('ian-card-body', $html);
        $this->assertStringNotContainsString('ian-badge ian-badge--normal', $html);
        $this->assertStringNotContainsString('>Обычное</span>', $html);
        $this->assertStringNotContainsString('ian-badge ian-badge--important', $html);
        unset($currentTeam);
    }

    public function test_inbox_renders_parent_and_child_lines_when_parent_name_is_set(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванов',
            'firstname' => 'Иван',
            'middlename' => null,
        ]);
        $student->lastname = 'Комарова';
        $student->name = 'Ярослав';
        $student->parent_id = $parent->id;
        $student->save();

        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Родитель: Иванов Иван', false)
            ->assertSee('Ребёнок: Комарова Ярослав.', false)
            ->assertSee('Добавлена группа «НовГруппа» (объект «Тестовый объект»).', false)
            ->assertDontSee('Комарова Ярослав добавил группу', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/Родитель: Иванов Иван<br\s*\/?>/i', $html);
        unset($currentTeam);
    }

    public function test_dashboard_bell_ssr_shows_event_without_normal_badge_and_links_to_inbox(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="inAppNotificationBell"', false)
            ->getContent();

        $bell = $this->bellMarkup($html);
        $this->assertStringContainsString('Ученик добавил группу', $bell);
        $this->assertStringContainsString(
            'href="'.route('inAppNotifications.index', ['n' => $notification->id]).'"',
            $bell
        );
        $this->assertStringContainsString('Показать все', $bell);
        $this->assertStringNotContainsString('>Обычное</span>', $bell);
        $this->assertStringNotContainsString('ian-badge ian-badge--normal', $bell);
        $this->assertStringNotContainsString('js-in-app-bell-mark-read', $bell);
        unset($currentTeam);
    }

    public function test_opening_inbox_without_click_does_not_mark_event_read(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $html = $this->get(route('inAppNotifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('непрочитано', $html);
        $this->assertStringNotContainsString('is-focused', $html);
        $this->assertDatabaseMissing('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        unset($currentTeam);
    }

    public function test_click_from_bell_highlights_event_card_and_marks_read(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
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
        unset($currentTeam);
    }

    public function test_expired_event_disappears_from_inbox_and_bell(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        Carbon::setTestNow(Carbon::parse('2026-08-16 12:00:00', 'Europe/Moscow'));
        try {
            $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
            $this->fanOutLatestEvent();

            Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:01', 'Europe/Moscow'));

            $this->actingWith2fa($admin);
            $this->get(route('inAppNotifications.index'))
                ->assertOk()
                ->assertDontSee('Ученик добавил группу', false)
                ->assertSee('Пока тихо', false);
            $this->getJson(route('inAppNotifications.bell'))
                ->assertOk()
                ->assertJsonPath('unread_count', 0);
        } finally {
            Carbon::setTestNow();
        }
        unset($currentTeam);
    }

    public function test_admin_hired_after_attach_does_not_see_event(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $this->fanOutLatestEvent();

        $lateAdmin = $this->createUserWithRole('admin');
        $this->actingWith2fa($lateAdmin);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Ученик добавил группу', false);
        unset($currentTeam);
    }

    public function test_attach_without_school_staff_still_succeeds_and_superadmin_sees_event(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $notification = $this->fanOutLatestEvent();
        $this->assertSame(0, (int) $notification->recipients_count);

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Ученик добавил группу', false);
        unset($currentTeam);
    }

    private function bellMarkup(string $html): string
    {
        if (! preg_match('/id="inAppNotificationBell"[\s\S]*?<\/li>/', $html, $match)) {
            $this->fail('На странице нет колокольчика inAppNotificationBell');
        }

        return $match[0];
    }
}
