<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * P1: AJAX-контракт + non-AJAX safety-net + доступ для гибкого абонемента в журнале.
 * P2: smoke страница → context → place → повторный GET.
 *
 * @see ScheduleJournalMutationContractsFeatureTest
 * @see ScheduleJournalMonthlyUlpContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalFlexibleContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_flexible_context_ajax_success_returns_json_structure(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 3);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.flexible-context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_place', true)
            ->assertJsonPath('team_id', (int) $team->id)
            ->assertJsonPath('assignment.id', (int) $ulp->id)
            ->assertJsonPath('occurrence_date', '2026-09-10')
            ->assertJsonStructure([
                'success',
                'user' => ['id', 'name'],
                'occurrence_date',
                'assignments',
                'teams',
                'team_id',
                'assignment',
                'can_place',
                'trainers',
                'scheduled_status_id',
                'visited_status_id',
                'team_default_trainer_profile_id',
            ]);

        $this->assertCount(1, $response->json('assignments'));
        $this->assertSame(3, (int) $response->json('assignment.slots_remaining'));
        $this->assertSame(
            LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            (int) $response->json('scheduled_status_id')
        );
        $this->assertSame(
            LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id),
            (int) $response->json('visited_status_id')
        );
    }

    public function test_flexible_context_ajax_validation_returns_422_not_empty_200(): void
    {
        [$student] = $this->makeStudentWithTeam();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.flexible-context', $student))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_date']);
    }

    public function test_place_flexible_ajax_success_returns_json_message_and_creates_utss(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие из гибкого абонемента поставлено в журнал.')
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'user_lesson_package_id',
                    'occurrence_date',
                    'slots_remaining',
                    'lessons_total',
                    'fee_amount_cents',
                    'package_name',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ])
            ->assertJsonPath('result.slots_remaining', 2)
            ->assertJsonPath('result.occurrence_date', '2026-09-10')
            ->assertJsonPath('result.fee_amount_cents', (int) $ulp->fee_amount_cents)
            ->assertJsonPath('result.status.id', LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id));

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $ulp->refresh();
        $this->assertTrue($ulp->isLaidOutInSchedule());
        $this->assertSame(2, (int) $ulp->lessons_remaining);

        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->assertSame(
            1,
            UserLessonOccurrenceStatusEvent::query()
                ->where('user_lesson_package_id', $ulp->id)
                ->where('lesson_occurrence_status_id', $scheduledId)
                ->count()
        );
    }

    public function test_place_flexible_ajax_validation_returns_422_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                // нет occurrence_date
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['occurrence_date']);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_flexible_ajax_missing_status_returns_422_under_status_field(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_flexible_ajax_with_attended_status_comment_and_trainer(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 3);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-12', [
                    'lesson_occurrence_status_id' => $attendedId,
                    'trainer_profile_id' => $trainer->id,
                    'comment' => 'Поставили с посещением',
                ])
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.slots_remaining', 2)
            ->assertJsonPath('result.lessons_total', 3)
            ->assertJsonPath('result.status.id', $attendedId)
            ->assertJsonPath('result.comment', 'Поставили с посещением')
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->whereDate('occurrence_date', '2026-09-12')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $attendedId, (int) $event->lesson_occurrence_status_id);
        $this->assertSame((int) $trainer->id, (int) $event->trainer_profile_id);
        $this->assertEqualsCanonicalizing(
            [(int) $trainer->id],
            $event->trainerProfiles()->pluck('trainer_profiles.id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertSame('Поставили с посещением', (string) $event->comment);

        $ulp->refresh();
        $this->assertSame(2, (int) $ulp->lessons_remaining);
    }

    public function test_place_flexible_ajax_with_multiple_trainers(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer('Гибкий А');
        $trainerB = $this->makeTrainerProfile('Гибкий Б');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-13', [
                    'lesson_occurrence_status_id' => $attendedId,
                    'trainer_profile_ids' => [$trainerA->id, $trainerB->id],
                ])
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->whereDate('occurrence_date', '2026-09-13')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertEqualsCanonicalizing(
            [(int) $trainerA->id, (int) $trainerB->id],
            $event->trainerProfiles()->pluck('trainer_profiles.id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_place_flexible_ajax_inactive_status_returns_422(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $inactive = $this->createCustomOccurrenceStatus('Неактивный гибкий');
        $inactive->update(['is_active' => false]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-11', [
                    'lesson_occurrence_status_id' => $inactive->id,
                ])
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_flexible_ajax_outside_month_returns_422_under_occurrence_date(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-20',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_date']);
        $this->assertStringContainsString(
            'месяца начисления',
            (string) ($response->json('errors.occurrence_date.0') ?? '')
        );
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_flexible_ajax_business_limit_returns_422_with_field_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);
        $trainer = $this->makeTrainerProfile('Flex Limit Trainer');

        // Статус без списания не исчерпывает лимит.
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-05',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertOk()
            ->assertJsonPath('result.slots_remaining', 1);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-06',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.slots_remaining', 0);

        $ulp->refresh();
        $this->assertSame(0, (int) $ulp->lessons_remaining);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-07',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['user_lesson_package_id']]);

        // Без consumes_lesson — можно ставить и при нулевом остатке.
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-08',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertOk()
            ->assertJsonPath('result.slots_remaining', 0);
    }

    public function test_place_flexible_non_ajax_redirects_and_creates_utss_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $response = $this->post(route('schedule.abonement.place-flexible', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Занятие из гибкого абонемента поставлено в журнал.');
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $ulp->refresh();
        $this->assertSame('2026-09-01', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2026-09-30', $ulp->ends_at?->format('Y-m-d'));
    }

    public function test_place_flexible_non_ajax_with_custom_status_redirects_and_persists_event(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $custom = $this->createCustomOccurrenceStatus('Гибкий статус');

        $response = $this->post(
            route('schedule.abonement.place-flexible', $student),
            array_merge($this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-14', [
                'lesson_occurrence_status_id' => $custom->id,
                'comment' => 'Non-AJAX комментарий',
            ]), ['_token' => csrf_token()])
        );

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHas('status');
        $this->assertNotSame(200, $response->getStatusCode());

        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->whereDate('occurrence_date', '2026-09-14')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $custom->id, (int) $event->lesson_occurrence_status_id);
        $this->assertSame('Non-AJAX комментарий', (string) $event->comment);
    }

    public function test_place_flexible_non_ajax_missing_status_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['lesson_occurrence_status_id']);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_flexible_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                // нет occurrence_date
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['occurrence_date']);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_flexible_non_ajax_business_error_redirects_with_session_errors(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->post(route('schedule.abonement.place-flexible', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-05',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_id' => $trainer->id,
        ])->assertRedirect(route('schedule.index'));

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-06',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHasErrors(['user_lesson_package_id']);
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_guest_flexible_endpoints_denied_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        Auth::logout();

        $this->get(route('schedule.index'))->assertRedirect();
        $this->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-10')
            ->assertUnauthorized();
        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ])->assertUnauthorized();
        $this->post(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ])->assertRedirect();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_without_schedule_view_flexible_endpoints_return_403(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-10')
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_foreign_partner_student_returns_404_for_flexible_endpoints(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 1);
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->foreignUser->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->foreignPartner->id);

        // Чужой ученик в контексте другого партнёра — 404 (assertScheduleStudent).
        $this->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-10')
            ->assertNotFound();
        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $foreignTeam->id,
            'occurrence_date' => '2026-09-10',
            'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->foreignPartner->id),
        ])->assertNotFound();
    }

    public function test_place_flexible_rejects_classic_flexible_without_billing_month(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(4, 60)->create([
            'name' => 'Классика гибкий контракт',
            'is_active' => true,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'billing_month' => null,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 100000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['user_lesson_package_id']]);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_second_lesson_same_day_ajax_ok_and_uses_distinct_slots(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 3);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-15',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-15',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertOk()
            ->assertJsonPath('result.slots_remaining', 3);

        $slotIds = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->whereDate('starts_at', '2026-09-15')
            ->pluck('team_schedule_slot_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertCount(2, $slotIds);
        $this->assertCount(2, array_unique($slotIds));
    }

    public function test_paid_month_does_not_block_ajax_place_flexible(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $ulp->update(['is_paid' => true, 'is_manual_paid' => true]);

        DB::table('users_prices')->insert([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 500000,
            'is_paid' => 1,
            'is_manual_paid' => 1,
            'lesson_package_id' => $ulp->lesson_package_id,
            'user_lesson_package_id' => $ulp->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-12',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_wrong_team_returns_422_under_team_id(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $otherTeam = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id, (int) $otherTeam->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01');

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $otherTeam->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => \App\Models\LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['team_id']]);
        $this->assertStringContainsString('групп', mb_strtolower((string) ($response->json('errors.team_id.0') ?? '')));
    }

    /**
     * P2: страница → flexible-context → place → повторный GET с маркерами / без пустого ответа.
     */
    public function test_flexible_place_workflow_page_modal_submit_visible_without_reload(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['name' => 'Гибкий', 'lastname' => 'Workflow']);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('flexiblePlaceModal', false)
            ->assertSee('flexiblePlaceForm', false)
            ->assertSee('novalidate', false)
            ->assertSee('flexible_lesson_occurrence_status_id', false)
            ->assertSee('flexible-status-error', false)
            ->assertSee('flexible-trainer-wrap', false)
            ->assertSee('flexible-comment', false)
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee(">2/2\nГибкий<", false)
            ->assertSee('data-flexible="1"', false)
            ->assertSee('btn-add-flexible-lesson', false)
            ->assertSee($student->full_name, false);

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.flexible-context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-08',
                'context_team_id' => $team->id,
            ]));
        $ctx->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_place', true)
            ->assertJsonPath('assignment.id', (int) $ulp->id)
            ->assertJsonStructure(['trainers', 'scheduled_status_id', 'visited_status_id']);

        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-08', [
                    'comment' => 'Workflow без F5',
                ])
            );
        $place->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.status.id', $scheduledId)
            ->assertJsonPath('result.slots_remaining', 2)
            ->assertJsonPath('result.comment', 'Workflow без F5')
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'lessons_total',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);

        $pageAfter = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('flexiblePlaceModal', false)
            ->assertSee('flexible_lesson_occurrence_status_id', false)
            ->assertSee('data-occurrence-count="1"', false)
            ->assertSee(">2/2\nГибкий<", false);
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_index_with_remaining_flexible_shows_hint_after_partial_place_still_flexible(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        // «Запись» не списывает — каунтер остаётся 2/2.
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-03',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])->assertOk();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee(">2/2\nГибкий<", false)
            ->assertSee('data-flexible="1"', false);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-04',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ])->assertOk();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee(">1/2\nГибкий<", false)
            ->assertSee('data-flexible="1"', false);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-05',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ])->assertOk();

        // При 0/2 hint и affordance остаются (статусы без списания без лимита).
        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee(">0/2\nГибкий<", false)
            ->assertSee('data-flexible="1"', false);
    }
}
