<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\InAppNotification;
use Illuminate\Support\Facades\DB;

/**
 * P1: native POST attach без X-Requested-With тоже создаёт уведомление (не только AJAX 200).
 *
 * UX-регресс: если notify повесить только на JSON-ветку контроллера, нативный submit
 * отдаст 302 и сохранит группу, а колокольчик админа останется пустым.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class CabinetTeamAttachInAppNotificationNonAjaxSafetyNetFeatureTest extends CabinetTeamAttachInAppNotificationTestCase
{
    public function test_native_submit_redirects_persists_group_and_notifies_school_staff(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $admin = $this->createUserWithRole('admin');
        $trainer = $this->createUserWithRole('trainer');
        $this->actingWith2fa($student);

        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), [
                'team_id' => $candidate->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');

        $this->assertTrue(
            DB::table('team_user')
                ->where('user_id', $student->id)
                ->where('team_id', $candidate->id)
                ->exists()
        );

        $notification = $this->fanOutLatestEvent();
        $this->assertSame(InAppNotification::SOURCE_EVENT, $notification->source);
        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $trainer->id,
        ]);
        unset($currentTeam);
    }

    public function test_native_validation_failure_has_team_id_error_and_no_notification(): void
    {
        [$student, $currentTeam] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), []);

        $this->assertContains($response->getStatusCode(), [302, 422]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['team_id']);
        } else {
            $response->assertJsonValidationErrors(['team_id']);
            $this->assertNotSame('', trim((string) $response->getContent()));
        }

        $this->assertSame(0, $this->eventNotificationCount());
        unset($currentTeam);
    }

    public function test_native_already_assigned_team_has_field_error_and_no_notification(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantAttachPermission($student);
        $this->createUserWithRole('admin');
        $this->actingWith2fa($student);

        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), [
                'team_id' => $currentTeam->id,
            ]);

        $this->assertContains($response->getStatusCode(), [302, 422]);
        $this->assertNotSame(200, $response->getStatusCode());

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['team_id']);
        } else {
            $response->assertJsonValidationErrors(['team_id']);
        }

        $this->assertSame(0, $this->eventNotificationCount());
        unset($candidate);
    }
}
