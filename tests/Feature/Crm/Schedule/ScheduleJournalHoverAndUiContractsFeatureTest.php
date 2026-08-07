<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\TrainerProfile;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\Postpay\PostpayJournalService;
use App\Services\Schedule\ScheduleJournalMonthService;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Новый UX журнала: ховеры ячеек (абонемент / постоплата / тренер), колонка постоплаты,
 * краткая причина пробного, UI-маркеры модалок.
 *
 * P1: feature + AJAX-контракт + non-AJAX safety-net + доступ.
 * P2: page smoke с маркерами blade.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalHoverAndUiContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        $this->grantLessonPackagesView();
    }

    public function test_cell_context_package_hover_for_trial_without_attended_has_no_trainer_line(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-10');

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => '2026-08-10',
                'utss_id' => $utss->id,
            ]))
            ->assertOk()
            ->assertJsonPath('selected.utss_id', (int) $utss->id)
            ->json();

        $this->assertSame('Пробное', $ctx['selected']['package_hover'] ?? null);
        $this->assertStringNotContainsString('Тренер', (string) ($ctx['selected']['package_hover'] ?? ''));
    }

    public function test_cell_context_package_hover_for_attended_includes_trainer_name_line(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $trainerName = $this->renameTrainer($trainer, 'Иванов', 'Иван');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $date = '2026-08-11';
        $utss = $this->createTrialUtss($student, $team, $date);

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
            'created_by' => $this->user->id,
        ]);

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]))
            ->assertOk()
            ->json();

        $hover = (string) ($ctx['selected']['package_hover'] ?? '');
        $this->assertStringContainsString('Пробное', $hover);
        $this->assertStringContainsString('Тренер: '.$trainerName, $hover);
        $this->assertSame($trainerName, $ctx['selected']['trainer_name'] ?? null);
    }

    public function test_cell_context_package_hover_for_attended_without_trainer_says_not_selected(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $date = '2026-08-12';
        $utss = $this->createTrialUtss($student, $team, $date);

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => null,
            'created_by' => $this->user->id,
        ]);

        $hover = (string) $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]))
            ->assertOk()
            ->json('selected.package_hover');

        $this->assertStringContainsString('Пробное', $hover);
        $this->assertStringContainsString('Тренер не выбран', $hover);
    }

    public function test_update_ajax_attended_returns_trainer_name_in_result(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $trainerName = $this->renameTrainer($trainer, 'Петров', 'Пётр');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $date = '2026-08-13';
        $utss = $this->createTrialUtss($student, $team, $date);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $trainer->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.')
            ->assertJsonPath('result.utss_id', (int) $utss->id)
            ->assertJsonPath('result.status.id', $this->visitedStatusId)
            ->assertJsonPath('result.trainer_name', $trainerName)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'comment',
                    'created',
                    'trainer_name',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);
        $this->assertNotSame('', (string) $response->getContent());
    }

    public function test_update_ajax_attended_without_trainer_returns_null_trainer_name(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $date = '2026-08-14';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.trainer_name', null);
    }

    public function test_update_ajax_non_attended_returns_null_trainer_name(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $date = '2026-08-15';
        $utss = $this->createTrialUtss($student, $team, $date);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $scheduledId,
            ])
            ->assertOk()
            ->assertJsonPath('result.trainer_name', null)
            ->assertJsonPath('result.status.id', $scheduledId);
    }

    public function test_update_non_ajax_attended_redirects_and_persists_trainer_not_empty_200(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $this->renameTrainer($trainer, 'Сидоров', 'Сидор');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $date = '2026-08-16';
        $utss = $this->createTrialUtss($student, $team, $date);

        $response = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Статус занятия сохранён.');
        $this->assertNotSame(200, $response->status());

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);
    }

    public function test_empty_cell_context_trial_reason_short_with_date(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->createTrialUtss($student, $team, '2026-08-01');

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.empty-cell.context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath('trial.reason', 'Уже есть пробное занятие 01.08.2026.');
    }

    public function test_month_service_single_lesson_hover_includes_fee(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFreeSingleLessonAssignment($student, feeAmountCents: 150000);
        $slot = $this->resolveOrCreateTeamScheduleSlot((int) $this->partner->id, (int) $team->id, '2026-09-10');
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-09-10',
            'ends_at' => '2026-09-10',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);

        $grouped = app(ScheduleJournalMonthService::class)->occurrencesByUserDate(
            (int) $this->partner->id,
            [(int) $student->id],
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30'),
            (int) $team->id,
        );
        $items = $grouped[$student->id.'_2026-09-10'] ?? [];
        $this->assertNotEmpty($items);
        $this->assertSame(
            ScheduleJournalMonthService::packageHoverLabel((string) $items[0]['package_name'], 150000),
            $items[0]['package_hover']
        );
    }

    public function test_schedule_index_shows_postpay_column_hint_and_modal_ui_markers(): void
    {
        $this->grantRolePermission('setPrices.view');
        $this->grantRolePermission('lessonPackages.type.postpay');

        [$student, $team] = $this->makeStudentWithTeam();
        $package = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->postpay()
            ->create([
                'name' => 'Постоплата UI',
                'price_cents' => 120000,
            ]);

        UserPrice::query()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $package->id,
            'price_cents' => 0,
            'is_paid' => false,
        ]);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('journal-postpay-hint', false)
            ->assertSee('1200₽', false)
            ->assertSee('в день', false)
            ->assertSee('empty-cell-team-display', false)
            ->assertSee('abonement-start-date-quick', false)
            ->assertSee('abonement-start-quick-link', false)
            ->assertSee('edit-user-teams-display', false)
            ->assertDontSee('empty-cell-choice-summary', false)
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString(PostpayJournalService::postpayAbonementColumnLabel(120000), $html);
    }

    public function test_guest_cannot_access_hover_related_endpoints(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-17');
        Auth::logout();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']))->assertStatus(302);
        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-08-17',
            'utss_id' => $utss->id,
        ]))->assertStatus(401);
        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-17',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertStatus(401);
        $this->getJson(route('schedule.empty-cell.context', $student).'?occurrence_date=2026-09-10')
            ->assertStatus(401);
    }

    public function test_without_schedule_view_hover_related_endpoints_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-18');
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index'))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => '2026-08-18',
                'utss_id' => $utss->id,
            ]))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-18',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-18',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertStatus(403);
    }

    /**
     * P2: страница журнала с маркерами UI → cell-context → update → повторный GET.
     */
    public function test_hover_ui_workflow_page_update_visible_without_reload_markers(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $trainerName = $this->renameTrainer($trainer, 'Козлов', 'Козел');
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $student->update(['name' => 'Ховер', 'lastname' => 'Ученик']);
        $date = '2026-08-19';
        $utss = $this->createTrialUtss($student, $team, $date);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('cellEditModal', false)
            ->assertSee('emptyCellPlaceModal', false)
            ->assertSee('abonementPlaceModal', false)
            ->assertSee('empty-cell-team-display', false)
            ->assertSee('abonement-start-date-quick', false)
            ->assertSee($student->full_name, false);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $trainer->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.trainer_name', $trainerName);

        $hover = (string) $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]))
            ->assertOk()
            ->json('selected.package_hover');

        $this->assertStringContainsString('Тренер: '.$trainerName, $hover);
    }

    private function grantRolePermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function renameTrainer(TrainerProfile $trainer, string $lastname, string $name): string
    {
        $trainer->loadMissing('user');
        $trainer->user->update([
            'lastname' => $lastname,
            'name' => $name,
        ]);

        return (string) $trainer->user->fresh()->full_name;
    }
}
