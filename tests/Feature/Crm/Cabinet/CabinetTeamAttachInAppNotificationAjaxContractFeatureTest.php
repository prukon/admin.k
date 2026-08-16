<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Jobs\FanOutInAppNotificationJob;
use App\Models\InAppNotification;
use Illuminate\Support\Facades\Queue;

/**
 * P1: AJAX-контракт attach → колокольчик: JSON, errors[team_id], очередь, pending не в ленте.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class CabinetTeamAttachInAppNotificationAjaxContractFeatureTest extends CabinetTeamAttachInAppNotificationTestCase
{
    public function test_ajax_missing_team_id_returns_field_error_and_no_notification(): void
    {
        [$student] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_ajax_zero_team_id_returns_field_error_and_no_notification(): void
    {
        [$student] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_ajax_success_queues_fanout_job(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->actingWith2fa($student);

        Queue::fake();

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Группа добавлена.');

        Queue::assertPushed(FanOutInAppNotificationJob::class);
        $this->assertSame(1, $this->eventNotificationCount());
        unset($currentTeam);
    }

    public function test_pending_event_is_hidden_from_bell_until_worker_runs(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk();

        $this->assertDatabaseHas('in_app_notifications', [
            'source' => InAppNotification::SOURCE_EVENT,
            'status' => InAppNotification::STATUS_PENDING,
        ]);

        $this->actingWith2fa($admin);
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('items', []);

        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Ученик добавил группу', false)
            ->assertSee('Пока тихо', false);

        $notification = $this->fanOutLatestEvent();
        $this->assertSame(InAppNotification::STATUS_DISPATCHED, $notification->status);

        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('items.0.title', 'Ученик добавил группу')
            ->assertJsonPath('items.0.category', InAppNotification::CATEGORY_NORMAL)
            ->assertJsonPath('items.0.is_read', false);

        $item = $this->getJson(route('inAppNotifications.bell'))->json('items.0');
        $this->assertStringContainsString((string) $student->full_name, (string) $item['body']);
        $this->assertStringContainsString('добавил группу', (string) $item['body']);
        $this->assertStringNotContainsString('Родитель:', (string) $item['body']);
        $this->assertStringNotContainsString('<a href', (string) $item['body']);
        $this->assertStringNotContainsString(route('admin.user.edit', $student, false), (string) $item['body']);
        $this->assertSame(
            route('inAppNotifications.index', ['n' => $notification->id]),
            $item['page_url']
        );
        $this->assertArrayNotHasKey('action_url', $item);
        $this->assertArrayNotHasKey('open_url', $item);
        unset($currentTeam);
    }

    public function test_student_bell_does_not_count_school_staff_event(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $this->fanOutLatestEvent();

        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        unset($currentTeam);
    }

    public function test_trainer_bell_shows_event_after_fanout(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $trainer = $this->createUserWithRole('trainer');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($trainer);
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('items.0.title', 'Ученик добавил группу');
        unset($currentTeam);
    }

    public function test_ajax_mark_read_decrements_counter_for_event(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $this->postJson(route('inAppNotifications.read', $notification), [])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseHas('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        unset($currentTeam);
    }
}
