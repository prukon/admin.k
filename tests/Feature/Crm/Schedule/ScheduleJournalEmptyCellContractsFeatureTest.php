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
 * P1: AJAX-контракт + non-AJAX safety-net + доступ для пробного/разового на пустой ячейке.
 * P2: smoke страница → context → place → повторный GET.
 *
 * @see ScheduleJournalEmptyCellPlacementFeatureTest
 * @see ScheduleJournalFlexibleContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalEmptyCellContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        $this->grantLessonPackagesView();
    }

    public function test_empty_cell_context_ajax_success_returns_json_structure(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate('Контекст разовое', 100000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('occurrence_date', '2026-09-10')
            ->assertJsonPath('team_id', (int) $team->id)
            ->assertJsonPath('team_locked', true)
            ->assertJsonStructure([
                'success',
                'user' => ['id', 'name'],
                'occurrence_date',
                'teams',
                'team_id',
                'team_locked',
                'trial' => ['allowed', 'reason', 'label'],
                'single_options',
                'single_blocked_reason',
                'flexible_options',
                'trainers',
                'scheduled_status_id',
                'visited_status_id',
                'team_default_trainer_profile_id',
                'team_default_trainer_profile_ids',
                'team_default_trainer_profile_ids_by_team',
            ]);

        $byTeam = $response->json('team_default_trainer_profile_ids_by_team') ?? [];
        $this->assertArrayHasKey((string) $team->id, $byTeam);
        $this->assertIsArray($byTeam[(string) $team->id]);
        $this->assertSame(
            LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            (int) $response->json('scheduled_status_id')
        );
        $this->assertTrue((bool) $response->json('trial.allowed'));
        $create = collect($response->json('single_options') ?? [])->firstWhere('lesson_package_id', (int) $template->id);
        $this->assertNotNull($create);
        $this->assertSame('create_new', $create['mode']);
    }

    public function test_empty_cell_context_returns_default_trainers_by_team(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа B']);
        $trainerA1 = $this->makeTrainerProfile('A1');
        $trainerA2 = $this->makeTrainerProfile('A2');
        $trainerB = $this->makeTrainerProfile('B1');

        foreach ([$trainerA1, $trainerA2] as $profile) {
            DB::table('team_trainer')->insert([
                'partner_id' => $this->partner->id,
                'team_id' => $teamA->id,
                'trainer_profile_id' => $profile->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'trainer_profile_id' => $trainerB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = $this->makeStudent(null);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [
            (int) $teamA->id,
            (int) $teamB->id,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-11',
            ]))
            ->assertOk();

        $byTeam = collect($response->json('team_default_trainer_profile_ids_by_team') ?? [])
            ->mapWithKeys(fn ($ids, $teamId) => [(int) $teamId => array_map('intval', $ids)])
            ->all();

        $this->assertSame([$trainerA1->id, $trainerA2->id], $byTeam[(int) $teamA->id] ?? null);
        $this->assertSame([$trainerB->id], $byTeam[(int) $teamB->id] ?? null);
        // Без filter team_id и при 2+ группах — дефолт текущей группы ещё не выбран.
        $this->assertSame([], $response->json('team_default_trainer_profile_ids'));
        $this->assertNull($response->json('team_default_trainer_profile_id'));
    }

    public function test_empty_cell_create_new_option_uses_current_student_discount(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update([
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
        $template = $this->makeSingleLessonTemplate('Разовое со скидкой', 100000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]))
            ->assertOk();

        $create = collect($response->json('single_options') ?? [])->firstWhere('lesson_package_id', (int) $template->id);
        $this->assertNotNull($create);
        $this->assertSame('create_new', $create['mode']);
        $this->assertEqualsWithDelta(900.0, (float) ($create['fee_amount'] ?? 0), 0.001);
        $this->assertSame(10, (int) ($create['discount_percent'] ?? 0));
        $this->assertSame('Льгота', (string) ($create['discount_comment'] ?? ''));
    }

    public function test_empty_cell_create_new_option_does_not_force_discount_when_student_has_none(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate('Разовое без скидки', 100000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]))
            ->assertOk();

        $create = collect($response->json('single_options') ?? [])->firstWhere('lesson_package_id', (int) $template->id);
        $this->assertNotNull($create);
        $this->assertEqualsWithDelta(1000.0, (float) ($create['fee_amount'] ?? 0), 0.001);
        $this->assertTrue(
            ($create['discount_percent'] ?? null) === null
            || (int) $create['discount_percent'] === 0
        );
    }

    public function test_empty_cell_context_ajax_validation_returns_422_not_empty_200(): void
    {
        [$student] = $this->makeStudentWithTeam();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_date']);
    }

    public function test_place_trial_ajax_success_returns_json_message_and_creates_row(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-09-10')
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Пробное занятие записано в журнал.')
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'user_lesson_package_id',
                    'is_trial_lesson',
                    'occurrence_date',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ])
            ->assertJsonPath('result.is_trial_lesson', true)
            ->assertJsonPath('result.occurrence_date', '2026-09-10')
            ->assertJsonPath('result.status.id', $scheduledId);

        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()
                ->where('user_id', $student->id)
                ->where('is_trial_lesson', true)
                ->count()
        );
    }

    public function test_place_single_ajax_success_returns_json_message_and_creates_assignment(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate('AJAX разовое', 150000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-11', 1500)
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Разовое занятие записано в журнал.')
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'user_lesson_package_id',
                    'is_trial_lesson',
                    'occurrence_date',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ])
            ->assertJsonPath('result.is_trial_lesson', false);

        $ulpId = (int) $response->json('result.user_lesson_package_id');
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulpId)->count());
        $this->assertSame((int) $team->id, (int) UserLessonPackage::query()->findOrFail($ulpId)->team_id);
    }

    public function test_place_trial_ajax_validation_returns_422_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.empty-cell.place-trial', $student), [
                'team_id' => $team->id,
                // нет occurrence_date / status
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['occurrence_date', 'lesson_occurrence_status_id']);

        $this->assertSame(
            0,
            UserTeamScheduleSlot::query()->where('user_id', $student->id)->where('is_trial_lesson', true)->count()
        );
    }

    public function test_place_single_ajax_validation_returns_422_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.empty-cell.place-single', $student), [
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['lesson_package_id']);

        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $student->id)->count());
    }

    public function test_place_single_ajax_missing_fee_when_create_returns_422(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.empty-cell.place-single', $student), [
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_package_id' => $template->id,
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fee_amount']);
    }

    public function test_place_single_ajax_with_attended_status_trainer_and_comment(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $template = $this->makeSingleLessonTemplate();
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-12', 1000, [
                    'lesson_occurrence_status_id' => $attendedId,
                    'trainer_profile_id' => $trainer->id,
                    'comment' => 'Разовое посетил',
                ])
            );

        $response->assertOk()
            ->assertJsonPath('result.status.id', $attendedId)
            ->assertJsonPath('result.comment', 'Разовое посетил');

        $ulpId = (int) $response->json('result.user_lesson_package_id');
        $event = UserLessonOccurrenceStatusEvent::query()
            ->where('user_lesson_package_id', $ulpId)
            ->whereDate('occurrence_date', '2026-09-12')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $trainer->id, (int) $event->trainer_profile_id);

        $ulp = UserLessonPackage::query()->findOrFail($ulpId);
        $this->assertSame(0, (int) $ulp->lessons_remaining);
    }

    public function test_place_trial_non_ajax_redirects_and_creates_row_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();

        $response = $this->post(
            route('schedule.empty-cell.place-trial', $student),
            array_merge($this->placeTrialPayload((int) $team->id, '2026-09-10'), ['_token' => csrf_token()])
        );

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Пробное занятие записано в журнал.');
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()
                ->where('user_id', $student->id)
                ->where('is_trial_lesson', true)
                ->count()
        );
        $student->refresh();
        $this->assertTrue((bool) $student->has_used_school_schedule_trial);
    }

    public function test_place_single_non_ajax_redirects_and_creates_assignment_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate('Non-AJAX разовое', 90000);

        $response = $this->post(
            route('schedule.empty-cell.place-single', $student),
            array_merge(
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-11', 900),
                ['_token' => csrf_token()]
            )
        );

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Разовое занятие записано в журнал.');
        $this->assertNotSame(200, $response->getStatusCode());

        $ulp = UserLessonPackage::query()
            ->where('user_id', $student->id)
            ->where('lesson_package_id', $template->id)
            ->first();
        $this->assertNotNull($ulp);
        $this->assertSame((int) $team->id, (int) $ulp->team_id);
        $this->assertSame(1, $ulp->userTeamScheduleSlots()->count());
        $this->assertSame(90000, (int) $ulp->fee_amount_cents);
    }

    public function test_place_single_non_ajax_bind_writes_team_id_and_redirects_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFreeSingleLessonAssignment($student);
        $this->assertNull($ulp->team_id);

        $response = $this->post(
            route('schedule.empty-cell.place-single', $student),
            array_merge(
                $this->placeSingleBindPayload($ulp, (int) $team->id, '2026-09-19'),
                ['_token' => csrf_token()]
            )
        );

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());

        $ulp->refresh();
        $this->assertSame((int) $team->id, (int) $ulp->team_id);
        $this->assertSame(1, $ulp->userTeamScheduleSlots()->count());
    }

    public function test_place_single_non_ajax_wrong_assignment_team_redirects_with_team_id_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Non-AJAX другая группа',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [
            (int) $team->id,
            (int) $otherTeam->id,
        ]);
        $ulp = $this->makeFreeSingleLessonAssignment($student);
        $ulp->team_id = (int) $team->id;
        $ulp->save();

        $response = $this->from(route('schedule.index'))
            ->post(
                route('schedule.empty-cell.place-single', $student),
                array_merge(
                    $this->placeSingleBindPayload($ulp, (int) $otherTeam->id, '2026-09-20'),
                    ['_token' => csrf_token()]
                )
            );

        $response->assertStatus(302)
            ->assertSessionHasErrors(['team_id']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(0, $ulp->userTeamScheduleSlots()->count());
        $this->assertSame((int) $team->id, (int) $ulp->fresh()->team_id);
    }

    public function test_place_trial_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.empty-cell.place-trial', $student), [
                '_token' => csrf_token(),
                'team_id' => $team->id,
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['occurrence_date', 'lesson_occurrence_status_id']);
        $this->assertSame(
            0,
            UserTeamScheduleSlot::query()->where('user_id', $student->id)->where('is_trial_lesson', true)->count()
        );
    }

    public function test_place_single_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.empty-cell.place-single', $student), [
                '_token' => csrf_token(),
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['lesson_package_id']);
        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $student->id)->count());
    }

    public function test_place_trial_non_ajax_business_error_redirects_with_session_errors(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->forceFill(['has_used_school_schedule_trial' => true])->save();

        $response = $this->from(route('schedule.index'))
            ->post(
                route('schedule.empty-cell.place-trial', $student),
                array_merge($this->placeTrialPayload((int) $team->id, '2026-09-10'), ['_token' => csrf_token()])
            );

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHasErrors();
        $this->assertSame(
            0,
            UserTeamScheduleSlot::query()->where('user_id', $student->id)->where('is_trial_lesson', true)->count()
        );
    }

    public function test_guest_empty_cell_endpoints_denied_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();
        Auth::logout();

        $this->get(route('schedule.index'))->assertRedirect();
        $this->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10')
            ->assertUnauthorized();
        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10')
        )->assertUnauthorized();
        $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-10', 1000)
        )->assertUnauthorized();
        $this->post(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-10')
        )->assertRedirect();
        $this->post(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-10', 1000)
        )->assertRedirect();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $student->id)->count());
    }

    public function test_without_schedule_view_empty_cell_endpoints_return_403(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10')
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-09-10')
            )
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-10', 1000)
            )
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(
                route('schedule.empty-cell.place-trial', $student),
                array_merge($this->placeTrialPayload((int) $team->id, '2026-09-10'), ['_token' => csrf_token()])
            )
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_without_lesson_packages_view_empty_cell_endpoints_return_403(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index'))
            ->assertOk();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10')
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-09-10')
            )
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-10', 1000)
            )
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(
                route('schedule.empty-cell.place-single', $student),
                array_merge(
                    $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-10', 1000),
                    ['_token' => csrf_token()]
                )
            )
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
        $this->assertSame(0, UserLessonPackage::query()->where('user_id', $student->id)->count());
    }

    public function test_foreign_partner_student_returns_404_for_empty_cell_endpoints(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->foreignUser->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->foreignUser->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->foreignPartner->id);

        $this->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10')
            ->assertNotFound();
        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            [
                'team_id' => $foreignTeam->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->foreignPartner->id),
            ]
        )->assertNotFound();

        $foreignTemplate = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой разовое',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            [
                'team_id' => $foreignTeam->id,
                'occurrence_date' => '2026-09-10',
                'lesson_package_id' => $foreignTemplate->id,
                'fee_amount' => 1000,
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->foreignPartner->id),
            ]
        )->assertNotFound();
    }

    /**
     * P2: страница → empty-cell-context → place → повторный GET с маркерами / без пустого ответа.
     */
    public function test_empty_cell_place_workflow_page_modal_submit_visible_without_reload(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['name' => 'Пустая', 'lastname' => 'Ячейка']);
        $template = $this->makeSingleLessonTemplate('Workflow разовое', 110000);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('emptyCellPlaceModal', false)
            ->assertSee('emptyCellPlaceForm', false)
            ->assertSee('novalidate', false)
            ->assertSee('empty_cell_lesson_occurrence_status_id', false)
            ->assertSee('empty-cell-status-error', false)
            ->assertSee('empty-cell-trainer-wrap', false)
            ->assertSee('empty-cell-fee-amount', false)
            ->assertSee('data-empty-lesson="1"', false)
            ->assertSee($student->full_name, false);

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-08',
                'context_team_id' => $team->id,
            ]));
        $ctx->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('trial.allowed', true)
            ->assertJsonStructure(['trainers', 'scheduled_status_id', 'visited_status_id', 'single_options']);

        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-08', 1100, [
                    'comment' => 'Workflow без F5',
                ])
            );
        $place->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.status.id', $scheduledId)
            ->assertJsonPath('result.comment', 'Workflow без F5')
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'user_lesson_package_id',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);

        $pageAfter = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('emptyCellPlaceModal', false)
            ->assertSee('empty_cell_lesson_occurrence_status_id', false)
            ->assertSee('data-occurrence-count="1"', false);

        $ulpId = (int) $place->json('result.user_lesson_package_id');
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulpId)->count());
        $this->assertSame((int) $team->id, (int) UserLessonPackage::query()->findOrFail($ulpId)->team_id);
    }
}
