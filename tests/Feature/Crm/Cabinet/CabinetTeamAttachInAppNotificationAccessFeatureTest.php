<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * P1: доступ к attach и отсутствие уведомления при отказе (гость / 403 / 422).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class CabinetTeamAttachInAppNotificationAccessFeatureTest extends CabinetTeamAttachInAppNotificationTestCase
{
    public function test_guest_cannot_attach_and_does_not_create_notification(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        Auth::logout();

        $json = $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id]);
        $this->assertContains($json->getStatusCode(), [401, 302, 403, 419]);
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertNotSame(500, $json->getStatusCode());

        $web = $this->from(route('login'))->post(route('cabinet.teams.attach'), [
            'team_id' => $candidate->id,
        ]);
        $this->assertContains($web->getStatusCode(), [401, 302, 403, 419]);
        $this->assertNotSame(200, $web->getStatusCode());
        $this->assertNotSame(500, $web->getStatusCode());

        $this->assertSame(0, $this->eventNotificationCount());
        unset($student, $currentTeam);
    }

    public function test_student_without_permission_gets_403_and_no_notification(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertForbidden();

        $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertForbidden();

        $this->assertSame(0, $this->eventNotificationCount());
        unset($currentTeam);
    }

    public function test_admin_cannot_attach_even_with_permission_and_no_notification(): void
    {
        [, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $admin = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => 1,
        ]);
        $this->grantAttachPermission($admin);
        $this->actingWith2fa($admin);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->assertSame(0, $this->eventNotificationCount());
        unset($currentTeam);
    }

    public function test_student_with_permission_can_attach_and_creates_pending_event(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'teams_label']);

        $this->assertSame(1, $this->eventNotificationCount());
        $this->assertDatabaseHas('in_app_notifications', [
            'source' => InAppNotification::SOURCE_EVENT,
            'status' => InAppNotification::STATUS_PENDING,
            'created_by' => $student->id,
        ]);
        unset($currentTeam);
    }

    public function test_attach_and_inbox_endpoints_never_return_500(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $attachJson = $this->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id]);
        $this->assertNotSame(500, $attachJson->getStatusCode());
        $this->assertNotSame('', trim((string) $attachJson->getContent()));

        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        foreach ([
            ['GET', route('inAppNotifications.index')],
            ['GET', route('inAppNotifications.bell')],
            ['GET', route('dashboard')],
            ['POST', route('inAppNotifications.read', $notification)],
        ] as [$method, $url]) {
            $response = $method === 'GET'
                ? $this->get($url)
                : $this->post($url);
            $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url}");
            $this->assertNotSame('', trim((string) $response->getContent()), "{$method} {$url} пустой");
        }
        unset($currentTeam);
    }
}
