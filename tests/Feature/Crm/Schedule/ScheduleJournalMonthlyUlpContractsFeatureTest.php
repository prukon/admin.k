<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * P1: AJAX/non-AJAX контракты месячного ULP (billing_month) + доступ.
 * P2: smoke страница → context → place → данные на GET без «белого экрана».
 *
 * @see ScheduleJournalMutationContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalMonthlyUlpContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_place_monthly_ajax_preview_returns_json_without_writing_utss(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1, 3]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01', lessons: 2);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-10-06',
                'weekdays' => [1],
                'preview' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview', true)
            ->assertJsonStructure([
                'success',
                'message',
                'preview',
                'result' => ['linked_count', 'starts_at', 'ends_at', 'preview_dates'],
            ])
            ->assertJsonPath('result.starts_at', '2025-10-06')
            ->assertJsonPath('result.ends_at', '2025-10-31');

        $ulp->refresh();
        $this->assertNull($ulp->starts_at);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_monthly_ajax_start_outside_billing_month_returns_422_under_start_date(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01');

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-09-29',
                'weekdays' => [1],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
        $this->assertStringContainsString(
            'месяца начисления',
            (string) ($response->json('errors.start_date.0') ?? '')
        );

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_monthly_ajax_success_keeps_month_end_and_sets_starts_at(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01', lessons: 2);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-10-06',
                'weekdays' => [1],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview', false)
            ->assertJsonPath('result.starts_at', '2025-10-06')
            ->assertJsonPath('result.ends_at', '2025-10-31')
            ->assertJsonPath('result.linked_count', 2);

        $ulp->refresh();
        $this->assertSame('2025-10-06', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2025-10-31', $ulp->ends_at?->format('Y-m-d'));
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_monthly_non_ajax_redirects_and_creates_utss_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01', lessons: 2);

        $response = $this->post(route('schedule.abonement.place-fixed', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2025-10-06',
            'weekdays' => [1],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Абонемент разложен в расписание.');
        $this->assertNotSame(200, $response->getStatusCode());

        $ulp->refresh();
        $this->assertSame('2025-10-06', $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2025-10-31', $ulp->ends_at?->format('Y-m-d'));
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_monthly_non_ajax_validation_redirects_with_start_date_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01');

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-11-03',
                'weekdays' => [1],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['start_date']);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_guest_monthly_place_denied_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01', lessons: 1);
        Auth::logout();

        $this->post(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2025-10-06',
            'weekdays' => [1],
        ])->assertRedirect();

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2025-10-06',
            'weekdays' => [1],
        ])->assertUnauthorized();

        $this->getJson(route('schedule.abonement.context', $student))->assertUnauthorized();
    }

    public function test_without_schedule_view_monthly_place_returns_403(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01', lessons: 1);
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-10-06',
                'weekdays' => [1],
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-10-06',
                'weekdays' => [1],
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.abonement.context', $student))
            ->assertForbidden();
    }

    /**
     * P2: страница → модалка (context) → place monthly → повторный GET не пустой.
     */
    public function test_monthly_place_workflow_page_modal_submit_visible_without_reload(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['name' => 'Месячный', 'lastname' => 'Workflow']);
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2025-10-01', lessons: 2);

        $page = $this->get(route('schedule.index', ['year' => 2025, 'month' => '10']));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('abonementPlaceForm', false)
            ->assertSee('novalidate', false)
            ->assertSee('abonement-start-date-error', false)
            ->assertSee($student->full_name, false);

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student));
        $ctx->assertOk()->assertJsonPath('success', true);
        $row = collect($ctx->json('assignments') ?? [])->firstWhere('id', (int) $ulp->id);
        $this->assertIsArray($row);
        $this->assertTrue((bool) ($row['placeable'] ?? false));
        $this->assertTrue((bool) ($row['from_setting_prices'] ?? false));
        $this->assertSame('2025-10-01', $row['billing_month'] ?? null);
        $this->assertSame('2025-10-31', $row['ends_at'] ?? null);
        $this->assertArrayHasKey('starts_at', $row);
        $this->assertNull($row['starts_at']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-10-06',
                'weekdays' => [1],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $pageAfter = $this->get(route('schedule.index', ['year' => 2025, 'month' => '10']));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('abonementPlaceModal', false);
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    private function makeMonthlyFixedAssignment(
        \App\Models\User $student,
        int $teamId,
        string $billingMonth,
        int $lessons = 4
    ): UserLessonPackage {
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [$teamId]);

        $package = \App\Models\LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Месячный контракт '.uniqid(),
            'schedule_type' => 'fixed',
            'duration_days' => 45,
            'lessons_count' => $lessons,
            'price_cents' => 800000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $monthEnd = \Carbon\Carbon::parse($billingMonth)->endOfMonth()->format('Y-m-d');

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $teamId,
            'billing_month' => $billingMonth,
            'starts_at' => null,
            'ends_at' => $monthEnd,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount' => '8000.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }
}
