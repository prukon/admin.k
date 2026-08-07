<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Новый функционал: каунтер гибкого по consumes_lesson + empty-cell chooser при лимите.
 *
 * P1: доступ (гость / 403 / права), AJAX-контракт, non-AJAX safety-net (не пустой 200).
 * P2: smoke страница → context (blocked flexible) → place trial/single → повторный GET.
 *
 * @see ScheduleJournalEmptyCellContractsFeatureTest
 * @see ScheduleJournalFlexibleContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalFlexibleLimitEmptyCellContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        $this->grantLessonPackagesView();
        $this->flushHeaders();
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\Team, 2: UserLessonPackage, 3: \App\Models\TrainerProfile}
     */
    private function studentWithFlexibleAtLimit(int $lessons = 1): array
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: $lessons);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-05',
            'lesson_occurrence_status_id' => $attendedId,
            'trainer_profile_id' => $trainer->id,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('result.slots_remaining', 0);

        $ulp->refresh();
        $this->assertSame(0, (int) $ulp->lessons_remaining);

        // Не оставляем AJAX-заголовки на TestCase — иначе non-AJAX POST станет JSON 200.
        $this->flushHeaders();

        return [$student, $team, $ulp, $trainer];
    }

    // -------------------------------------------------------------------------
    // P1: доступ
    // -------------------------------------------------------------------------

    public function test_authorized_with_permissions_can_open_index_and_empty_cell_at_flexible_limit(): void
    {
        [$student, $team] = $this->studentWithFlexibleAtLimit();
        $this->makeSingleLessonTemplate('Доступ при лимите');

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('data-flexible="1"', false)
            ->assertSee('data-flexible-remaining="0"', false)
            ->assertSee('data-empty-lesson="1"', false)
            ->assertSee('emptyCellPlaceModal', false);

        $ctx = $this->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-12',
                'context_team_id' => $team->id,
            ]), $this->ajaxHeaders());
        $ctx->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flexible_options.0.allowed', false)
            ->assertJsonStructure([
                'trial' => ['allowed', 'reason', 'label'],
                'single_options',
                'flexible_options',
            ]);
    }

    public function test_guest_denied_index_empty_cell_and_flexible_at_limit_flow(): void
    {
        [$student, $team, $ulp] = $this->studentWithFlexibleAtLimit();
        $template = $this->makeSingleLessonTemplate();
        Auth::logout();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertRedirect();

        $this->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-12')
            ->assertUnauthorized();
        $this->getJson(route('schedule.abonement.flexible-context', $student).'?occurrence_date=2026-09-12')
            ->assertUnauthorized();

        $this->postJson(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-12')
        )->assertUnauthorized();
        $this->postJson(
            route('schedule.empty-cell.place-single', $student),
            $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-12', 900)
        )->assertUnauthorized();
        $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-12',
            'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ])->assertUnauthorized();

        $this->post(
            route('schedule.empty-cell.place-trial', $student),
            $this->placeTrialPayload((int) $team->id, '2026-09-12')
        )->assertRedirect();

        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count(),
            'Гость не должен создать второе занятие'
        );
    }

    public function test_without_schedule_view_index_and_mutations_return_403(): void
    {
        [$student, $team, $ulp] = $this->studentWithFlexibleAtLimit();
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
            ->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-12')
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-09-12')
            )
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-12',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(
                route('schedule.empty-cell.place-single', $student),
                array_merge(
                    $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-12', 900),
                    ['_token' => csrf_token()]
                )
            )
            ->assertForbidden();
    }

    public function test_without_lesson_packages_view_empty_cell_403_but_flexible_place_ok(): void
    {
        [$student, $team, $ulp] = $this->studentWithFlexibleAtLimit();
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk()
            ->assertSee('data-flexible="1"', false)
            ->assertSee('data-flexible-remaining="0"', false)
            ->assertDontSee('data-empty-lesson="1"', false)
            ->assertSee('Гибкий абонемент: поставить занятие', false);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-12')
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-12',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.slots_remaining', 0);

        $this->assertSame(
            2,
            UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count()
        );
    }

    // -------------------------------------------------------------------------
    // P1: домен + AJAX
    // -------------------------------------------------------------------------

    public function test_index_with_flexible_remaining_keeps_direct_flexible_not_empty_lesson(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 3);
        $this->makeSingleLessonTemplate();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('data-flexible="1"', false)
            ->assertSee('data-flexible-remaining="3"', false)
            ->assertDontSee('data-empty-lesson="1"', false)
            ->assertSee('Гибкий абонемент: поставить занятие', false);
    }

    public function test_place_scheduled_does_not_reduce_remaining_ajax(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-10',
                'lesson_occurrence_status_id' => $scheduledId,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.slots_remaining', 2)
            ->assertJsonPath('result.is_flexible', true)
            ->assertJsonStructure([
                'message',
                'result' => [
                    'utss_id',
                    'user_lesson_package_id',
                    'slots_remaining',
                    'lessons_total',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);

        $ulp->refresh();
        $this->assertSame(2, (int) $ulp->lessons_remaining);
        $this->assertSame(1, $ulp->userTeamScheduleSlots()->count());
    }

    public function test_empty_cell_context_at_limit_blocks_flexible_and_lists_trial_single(): void
    {
        [$student, $team, $ulp] = $this->studentWithFlexibleAtLimit();
        $template = $this->makeSingleLessonTemplate('Разовое в chooser', 125000);

        $response = $this->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-15',
                'context_team_id' => $team->id,
            ]), $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flexible_options.0.user_lesson_package_id', (int) $ulp->id)
            ->assertJsonPath('flexible_options.0.allowed', false)
            ->assertJsonPath('flexible_options.0.slots_remaining', 0)
            ->assertJsonPath(
                'flexible_options.0.reason',
                'Достигнут лимит занятий по гибкому абонементу.'
            )
            ->assertJsonPath('trial.allowed', true);

        $create = collect($response->json('single_options') ?? [])
            ->firstWhere('lesson_package_id', (int) $template->id);
        $this->assertNotNull($create);
        $this->assertSame('create_new', $create['mode']);
        $this->assertNotSame('', (string) ($response->json('flexible_options.0.label') ?? ''));
    }

    public function test_place_trial_ajax_at_flexible_limit_succeeds(): void
    {
        [$student, $team] = $this->studentWithFlexibleAtLimit();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-09-16', [
                    'comment' => 'Пробное при лимите гибкого',
                ])
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Пробное занятие записано в журнал.')
            ->assertJsonPath('result.is_trial_lesson', true)
            ->assertJsonPath('result.comment', 'Пробное при лимите гибкого')
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);

        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()
                ->where('user_id', $student->id)
                ->where('is_trial_lesson', true)
                ->count()
        );
    }

    public function test_place_single_ajax_at_flexible_limit_succeeds(): void
    {
        [$student, $team] = $this->studentWithFlexibleAtLimit();
        $template = $this->makeSingleLessonTemplate('Разовое при лимите', 99000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-17', 990, [
                    'comment' => 'Разовое при лимите',
                ])
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.comment', 'Разовое при лимите')
            ->assertJsonStructure([
                'message',
                'result' => [
                    'utss_id',
                    'user_lesson_package_id',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);

        $ulpId = (int) $response->json('result.user_lesson_package_id');
        $this->assertGreaterThan(0, $ulpId);
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulpId)->count());
    }

    public function test_place_flexible_attended_at_limit_ajax_returns_422(): void
    {
        [$student, $team, $ulp, $trainer] = $this->studentWithFlexibleAtLimit();
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-18',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['user_lesson_package_id']]);

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_flexible_scheduled_at_limit_ajax_still_allowed(): void
    {
        [$student, $team, $ulp] = $this->studentWithFlexibleAtLimit();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-19',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.slots_remaining', 0);

        $ulp->refresh();
        $this->assertSame(0, (int) $ulp->lessons_remaining);
        $this->assertSame(2, $ulp->userTeamScheduleSlots()->count());
    }

    public function test_update_ajax_returns_flexible_remaining_payload_and_restores(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-20',
                'lesson_occurrence_status_id' => $scheduledId,
            ]);
        $place->assertOk()->assertJsonPath('result.slots_remaining', 2);
        $utssId = (int) $place->json('result.utss_id');

        $toAttended = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssId,
                'occurrence_date' => '2026-09-20',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ]);
        $toAttended->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.')
            ->assertJsonPath('result.is_flexible', true)
            ->assertJsonPath('result.user_lesson_package_id', (int) $ulp->id)
            ->assertJsonPath('result.slots_remaining', 1)
            ->assertJsonPath('result.lessons_total', 2)
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'status' => ['id', 'title', 'icon', 'color'],
                    'slots_remaining',
                    'lessons_total',
                    'package_name',
                ],
            ]);

        $toScheduled = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssId,
                'occurrence_date' => '2026-09-20',
                'lesson_occurrence_status_id' => $scheduledId,
            ]);
        $toScheduled->assertOk()
            ->assertJsonPath('result.is_flexible', true)
            ->assertJsonPath('result.slots_remaining', 2);

        $ulp->refresh();
        $this->assertSame(2, (int) $ulp->lessons_remaining);
    }

    // -------------------------------------------------------------------------
    // P1: non-AJAX safety-net
    // -------------------------------------------------------------------------

    public function test_place_trial_non_ajax_at_flexible_limit_redirects_and_creates_not_empty_200(): void
    {
        $this->flushHeaders();
        [$student, $team] = $this->studentWithFlexibleAtLimit();

        $response = $this->from(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->post(
                route('schedule.empty-cell.place-trial', $student),
                array_merge(
                    $this->placeTrialPayload((int) $team->id, '2026-09-21'),
                    ['_token' => csrf_token()]
                )
            );

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHas('status');

        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()
                ->where('user_id', $student->id)
                ->where('is_trial_lesson', true)
                ->count()
        );
    }

    public function test_place_single_non_ajax_at_flexible_limit_redirects_and_creates_not_empty_200(): void
    {
        $this->flushHeaders();
        [$student, $team] = $this->studentWithFlexibleAtLimit();
        $template = $this->makeSingleLessonTemplate('Non-AJAX разовое', 150000);

        $response = $this->from(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->post(
                route('schedule.empty-cell.place-single', $student),
                array_merge(
                    $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-22', 1500),
                    ['_token' => csrf_token()]
                )
            );

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHas('status');

        $this->assertSame(
            1,
            UserLessonPackage::query()
                ->where('user_id', $student->id)
                ->where('lesson_package_id', $template->id)
                ->count()
        );
    }

    public function test_place_flexible_scheduled_non_ajax_at_limit_redirects_and_creates_not_empty_200(): void
    {
        $this->flushHeaders();
        [$student, $team, $ulp] = $this->studentWithFlexibleAtLimit();

        $response = $this->from(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-23',
                'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHas('status');

        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $ulp->refresh();
        $this->assertSame(0, (int) $ulp->lessons_remaining);
    }

    public function test_place_flexible_attended_non_ajax_at_limit_redirects_with_errors_not_empty_200(): void
    {
        $this->flushHeaders();
        [$student, $team, $ulp, $trainer] = $this->studentWithFlexibleAtLimit();
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $response = $this->from(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->post(route('schedule.abonement.place-flexible', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-09-24',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHasErrors(['user_lesson_package_id']);
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_update_non_ajax_flexible_status_redirects_and_consumes_remaining(): void
    {
        $this->flushHeaders();
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $place = $this->postJson(route('schedule.abonement.place-flexible', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-09-25',
            'lesson_occurrence_status_id' => $scheduledId,
        ], $this->ajaxHeaders());
        $place->assertOk();
        $utssId = (int) $place->json('result.utss_id');

        $response = $this->from(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'utss_id' => $utssId,
                'occurrence_date' => '2026-09-25',
                'lesson_occurrence_status_id' => $attendedId,
                'trainer_profile_id' => $trainer->id,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHas('status');

        $ulp->refresh();
        $this->assertSame(1, (int) $ulp->lessons_remaining);
        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'occurrence_date' => '2026-09-25',
            'lesson_occurrence_status_id' => $attendedId,
        ]);
    }

    // -------------------------------------------------------------------------
    // P2: smoke workflow без F5
    // -------------------------------------------------------------------------

    public function test_flexible_limit_empty_cell_workflow_page_context_place_visible_without_reload(): void
    {
        [$student, $team, $ulp] = $this->studentWithFlexibleAtLimit();
        $student->update(['name' => 'Лимит', 'lastname' => 'Гибкий']);
        $template = $this->makeSingleLessonTemplate('Smoke разовое', 130000);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee($student->full_name, false)
            ->assertSee('emptyCellPlaceModal', false)
            ->assertSee('emptyCellPlaceForm', false)
            ->assertSee('novalidate', false)
            ->assertSee('data-flexible="1"', false)
            ->assertSee('data-flexible-remaining="0"', false)
            ->assertSee('data-empty-lesson="1"', false)
            ->assertSee('Пробное, разовое или занятие из гибкого абонемента', false)
            ->assertSee(">0/1\nГибкий<", false)
            ->assertSee('flexiblePlaceModal', false);

        $ctx = $this->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-26',
                'context_team_id' => $team->id,
            ]), $this->ajaxHeaders());
        $ctx->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flexible_options.0.allowed', false)
            ->assertJsonPath('flexible_options.0.user_lesson_package_id', (int) $ulp->id)
            ->assertJsonPath('trial.allowed', true)
            ->assertJsonStructure(['single_options', 'trainers', 'scheduled_status_id']);

        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-09-26', 1300, [
                    'comment' => 'Smoke при лимите гибкого',
                ])
            );
        $place->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.comment', 'Smoke при лимите гибкого')
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
            ->assertSee('data-occurrence-count="1"', false)
            ->assertSee(">0/1\nГибкий<", false);

        $singleUlpId = (int) $place->json('result.user_lesson_package_id');
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $singleUlpId)->count());
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }
}
