<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\InAppNotification;
use App\Models\Location;
use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Carbon;

/**
 * ЛК attach группы → in-app уведомление admin/trainer этой школы (ядро).
 */
final class CabinetTeamAttachInAppNotificationFeatureTest extends CabinetTeamAttachInAppNotificationTestCase
{

    public function test_attach_notifies_school_admin_and_trainer_not_student_or_foreign(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Школьный',
            'name' => 'Админ',
        ]);
        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'lastname' => 'Школьный',
            'name' => 'Тренер',
        ]);
        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $foreignTrainer = $this->createUserWithRole('trainer', $this->foreignPartner);

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $notification = $this->fanOutLatestEvent();

        $this->assertSame(InAppNotification::SOURCE_EVENT, $notification->source);
        $this->assertSame(InAppNotification::CATEGORY_NORMAL, $notification->category);
        $this->assertSame(InAppNotification::TTL_30D, $notification->ttl_preset);
        $this->assertSame('Ученик добавил группу', $notification->title);
        $this->assertSame((int) $student->id, (int) $notification->created_by);
        $this->assertFalse((bool) $notification->is_global);
        $this->assertTrue($notification->partners()->where('partners.id', $this->partner->id)->exists());
        $this->assertFalse($notification->partners()->where('partners.id', $this->foreignPartner->id)->exists());

        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $trainer->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $foreignAdmin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $foreignTrainer->id,
        ]);

        $this->actingWith2fa($admin);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Ученик добавил группу', false)
            ->assertSee((string) $student->full_name, false)
            ->assertSee((string) $candidate->title, false)
            ->assertSee('Тестов Ученик добавил группу «НовГруппа» (объект «Тестовый объект»).', false)
            ->assertDontSee('Родитель:', false)
            ->assertDontSee(route('admin.user.edit', $student, false), false);

        $this->actingWith2fa($trainer);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Ученик добавил группу', false);

        $this->actingWith2fa($student);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Ученик добавил группу', false);

        $this->actingWith2fa($foreignAdmin, (int) $this->foreignPartner->id);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Ученик добавил группу', false);

        unset($currentTeam);
    }

    public function test_ttl_is_thirty_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16 12:00:00', 'Europe/Moscow'));

        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');
        $this->createUserWithRole('admin');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertNotNull($notification->expires_at);
        $this->assertTrue(
            $notification->expires_at->equalTo(now()->addDays(30))
        );

        unset($currentTeam);
        Carbon::setTestNow();
    }

    public function test_disabled_admin_and_custom_role_are_not_recipients(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $disabledAdmin = $this->createUserWithRole('admin', $this->partner, [
            'is_enabled' => 0,
        ]);
        $customRole = $this->createCustomRole($this->partner, 'Менеджер');
        $customStaff = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $customRole->id,
            'is_enabled' => 1,
        ]);

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk();

        $notification = $this->fanOutLatestEvent();

        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $disabledAdmin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $customStaff->id,
        ]);

        unset($currentTeam);
    }

    public function test_failed_attach_does_not_create_notification(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');
        $this->createUserWithRole('admin');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $currentTeam->id])
            ->assertStatus(422);

        $this->assertSame(0, InAppNotification::query()->where('source', InAppNotification::SOURCE_EVENT)->count());
        unset($candidate);
    }

    public function test_family_context_notification_is_about_active_sibling(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Сидоров',
            'firstname' => 'Пётр',
            'middlename' => null,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Иванов',
            'name' => 'Петя',
            'is_enabled' => 1,
        ]);
        $brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Иванов',
            'name' => 'Вася',
            'is_enabled' => 1,
        ]);

        $this->grantPermission($brother1, 'account.user.team.update');
        $this->grantPermission($brother1, 'dashboard.view');
        $this->createUserWithRole('admin');

        $location = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Семейный объект']);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'СемА',
            'is_enabled' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'СемБ',
            'is_enabled' => 1,
        ]);

        $this->sync->attachTeamForStudent($brother2, (int) $teamA->id);
        app(FamilyStudentContextService::class)->setActiveStudent($brother1, (int) $brother2->id);

        $this->actingAs($brother1)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed' => true,
                FamilyStudentContextService::SESSION_KEY => $brother2->id,
            ])
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $teamB->id])
            ->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertSame((int) $brother1->id, (int) $notification->created_by);
        $this->assertStringContainsString('Родитель: Сидоров Пётр', (string) $notification->body);
        $this->assertStringContainsString('Ребёнок: Иванов Вася.', (string) $notification->body);
        $this->assertStringContainsString('Добавлена группа «СемБ» (объект «Семейный объект»).', (string) $notification->body);
        $this->assertStringNotContainsString('Иванов Петя', (string) $notification->body);
        $this->assertStringNotContainsString('Иванов Вася добавил группу', (string) $notification->body);
        $this->assertStringNotContainsString('<a href', (string) $notification->body);
    }

    public function test_body_with_parent_id_but_empty_name_keeps_legacy_sentence(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => null,
            'firstname' => null,
            'middlename' => null,
        ]);
        $student->parent_id = $parent->id;
        $student->save();

        $this->grantPermission($student, 'account.user.team.update');
        $this->createUserWithRole('admin');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertStringContainsString(
            'Тестов Ученик добавил группу «НовГруппа» (объект «Тестовый объект»).',
            (string) $notification->body
        );
        $this->assertStringNotContainsString('Родитель:', (string) $notification->body);
        $this->assertStringNotContainsString('Ребёнок:', (string) $notification->body);
        $this->assertStringNotContainsString('Добавлена группа', (string) $notification->body);

        unset($currentTeam);
    }

    public function test_body_escapes_html_in_student_name_and_team_title(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $student->lastname = '<script>alert(1)</script>';
        $student->name = 'Иван';
        $student->save();
        $candidate->title = 'Группа <b>X</b>';
        $candidate->save();

        $this->grantPermission($student, 'account.user.team.update');
        $this->createUserWithRole('admin');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertStringNotContainsString('<script>', (string) $notification->body);
        $this->assertStringNotContainsString('<a href', (string) $notification->body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', (string) $notification->body);
        $this->assertStringContainsString('Группа &lt;b&gt;X&lt;/b&gt;', (string) $notification->body);
        $this->assertStringContainsString('Тестовый объект', (string) $notification->body);
        $this->assertStringContainsString('добавил группу', (string) $notification->body);
        $this->assertStringNotContainsString('Родитель:', (string) $notification->body);

        unset($currentTeam);
    }

    public function test_body_escapes_html_in_parent_name(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => '<img src=x onerror=alert(1)>',
            'firstname' => 'Иван',
            'middlename' => null,
        ]);
        $student->parent_id = $parent->id;
        $student->save();

        $this->grantPermission($student, 'account.user.team.update');
        $this->createUserWithRole('admin');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertStringNotContainsString('<img', (string) $notification->body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', (string) $notification->body);
        $this->assertStringContainsString('Родитель:', (string) $notification->body);
        $this->assertStringContainsString('Ребёнок: Тестов Ученик.', (string) $notification->body);
        $this->assertStringContainsString('Добавлена группа «НовГруппа»', (string) $notification->body);

        unset($currentTeam);
    }

    public function test_dispatcher_failure_does_not_block_attach(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        config(['in_app_notifications.events.cabinet_team_attached.ttl_preset' => 'bogus']);

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertContains(
            (int) $candidate->id,
            $this->sync->teamIdsForStudent($student->fresh())
        );
        $this->assertSame(0, InAppNotification::query()->where('source', InAppNotification::SOURCE_EVENT)->count());
        unset($currentTeam);
    }

    public function test_superadmin_sees_event_for_current_school_only(): void
    {
        [$student, $currentTeam, $candidate] = $this->makeStudentWithLocationTeams();
        $this->grantPermission($student, 'account.user.team.update');

        $this->actingAs($student)
            ->postJson(route('cabinet.teams.attach'), ['team_id' => $candidate->id])
            ->assertOk();

        $this->fanOutLatestEvent();

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Ученик добавил группу', false);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Ученик добавил группу', false);

        unset($currentTeam);
    }
}
