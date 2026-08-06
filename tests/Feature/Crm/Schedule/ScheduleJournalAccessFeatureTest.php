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
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFixedAssignment($student, lessons: 1, durationDays: 7);
        $utss = $this->createTrialUtss($student, $team, '2026-05-01');

        Auth::logout();

        $this->get(route('schedule.index'))->assertStatus(302);
        $this->getJson(route('schedule.cell-context', ['user_id' => $student->id, 'date' => '2026-05-01']))->assertStatus(401);
        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-05-01',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertStatus(401);
        $this->getJson(route('schedule.abonement.context', $student))->assertStatus(401);
        $this->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-10')
            ->assertStatus(401);
        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertStatus(401);
        $flexUlp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $flexUlp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ])->assertStatus(401);
        $this->postJson(route('user.sync.teams', $student), ['team_ids' => []])->assertStatus(401);
        $this->getJson(route('logs.data.schedule', ['draw' => 1]))->assertStatus(401);
        $this->get(route('schedule.occurrence-statuses'))->assertStatus(302);
        $this->post(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertRedirect();
        $this->post(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $flexUlp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ])->assertRedirect();
    }

    public function test_schedule_journal_forbidden_without_schedule_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student, $team] = $this->makeStudentWithTeam();
        $date = '2026-05-01';
        $utss = $this->createTrialUtss($student, $team, $date);
        $ulp = $this->makeFixedAssignment($student, lessons: 1, durationDays: 7);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('schedule.index'))
            ->assertStatus(403);

        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.cell-context', ['user_id' => $student->id, 'date' => $date]))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('logs.data.schedule', ['draw' => 1]))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.abonement.context', $student))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-10')
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])
            ->assertStatus(403);

        $flexUlp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $flexUlp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $flexUlp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('user.sync.teams', $student), ['team_ids' => []])
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

    public function test_authorized_with_schedule_view_can_open_journal_and_flexible_endpoints(): void
    {
        $this->withoutVite();
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $index = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $index->assertOk();
        $this->assertNotSame('', trim((string) $index->getContent()));
        $index->assertSee('flexiblePlaceModal', false);

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.flexible-context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]));
        $ctx->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_place', true);
        $this->assertNotSame('', (string) $ctx->getContent());

        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-10')
            );
        $place->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'result' => ['utss_id', 'status']]);
        $this->assertNotSame('', (string) $place->getContent());
    }
}
