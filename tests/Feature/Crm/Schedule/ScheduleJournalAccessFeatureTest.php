<?php

namespace Tests\Feature\Crm\Schedule;

use Illuminate\Support\Facades\Auth;

final class ScheduleJournalAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
    }

    public function test_guest_cannot_access_schedule_journal(): void
    {
        Auth::logout();

        $this->get(route('schedule.index'))->assertStatus(302);
        $this->getJson(route('schedule.cell-context', ['user_id' => 1, 'date' => '2026-05-01']))->assertStatus(401);
        $this->postJson(route('schedule.update'), [])->assertStatus(401);
        $this->get(route('schedule.occurrence-statuses'))->assertStatus(302);
    }

    public function test_schedule_journal_forbidden_without_schedule_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student] = $this->makeStudentTeamAndTrainer();

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('schedule.index'))
            ->assertStatus(403);

        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.cell-context', ['user_id' => $student->id, 'date' => '2026-05-01']))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'date' => '2026-05-01',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('logs.data.schedule', ['draw' => 1]))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('user.schedule.info', $student))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('user.set.group', $student), ['team_id' => null])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('user.sync.teams', $student), ['team_ids' => []])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('user.update.schedule', $student), [
                'weekdays' => [1],
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-01',
            ])
            ->assertStatus(403);

        // Без schedule.view и без lessonPackages.view вкладка/CRUD статусов недоступны
        $noStatuses = $this->makeCustomRoleUser();
        $this->actingAs($noStatuses)->withSession($session)
            ->get(route('schedule.occurrence-statuses'))
            ->assertStatus(403);

        $this->actingAs($noStatuses)->withSession($session)
            ->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
                'title' => 'X',
                'color' => '#abcdef',
                'consumes_lesson' => 0,
                'is_active' => 1,
            ])
            ->assertStatus(403);
    }
}
