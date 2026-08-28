<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserPrice;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;

/**
 * P1: AJAX-контракт суммы постоплаты в колонке оплаты:
 * postJson/deleteJson с X-Requested-With → JSON success/message/result.payment_status.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalPostpayPaymentDueAjaxContractFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_create_postpay_ajax_success_returns_payment_status_due(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.')
            ->assertJsonPath('result.created', true)
            ->assertJsonPath('result.payment_status.state', 'due')
            ->assertJsonPath('result.payment_status.amount_cents', 50000)
            ->assertJsonPath('result.payment_status.amount_label', '500₽')
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'created',
                    'payment_status' => ['state', 'icon_class', 'hover', 'amount_cents', 'amount_label'],
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ]);
        $this->assertIsInt($response->json('result.payment_status.amount_cents'));

        $row = UserPrice::query()
            ->where('user_id', $student->id)
            ->where('team_id', $team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(50000, (int) $row->price_cents);
    }

    public function test_second_visit_ajax_recalculates_due_amount(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.payment_status.amount_cents', 50000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-04',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.payment_status.state', 'due')
            ->assertJsonPath('result.payment_status.amount_cents', 100000)
            ->assertJsonPath('result.payment_status.amount_label', '1000₽');
    }

    public function test_create_postpay_ajax_due_amount_includes_discount(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 10, 50000);
        $expectedCents = Money::payableAfterDiscountCents(50000, 10);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-05',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.payment_status.state', 'due')
            ->assertJsonPath('result.payment_status.amount_cents', $expectedCents)
            ->assertJsonPath('result.payment_status.amount_label', '450₽');
        $this->assertSame(45000, $expectedCents);
    }

    public function test_update_to_scheduled_ajax_clears_due_amount(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);
        $scheduledId = $this->occurrenceStatusIdByCode(LessonOccurrenceStatus::CODE_SCHEDULED);

        $create = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-06',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ]);
        $create->assertOk()->assertJsonPath('result.payment_status.state', 'due');
        $utssId = (int) $create->json('result.utss_id');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssId,
                'occurrence_date' => '2026-08-06',
                'lesson_occurrence_status_id' => $scheduledId,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.payment_status.state', 'none')
            ->assertJsonPath('result.payment_status.amount_cents', 0)
            ->assertJsonPath('result.payment_status.amount_label', '');
    }

    public function test_destroy_ajax_returns_none_payment_status_after_last_visit(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $create = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-07',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ]);
        $create->assertOk();
        $utssId = (int) $create->json('result.utss_id');

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utssId), [
                'occurrence_date' => '2026-08-07',
                'journal_team_filter' => (string) $team->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие удалено.')
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.payment_status.state', 'none')
            ->assertJsonPath('result.payment_status.amount_cents', 0)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'deleted',
                    'payment_status' => ['state', 'icon_class', 'hover', 'amount_cents', 'amount_label'],
                ],
            ]);
    }

    public function test_create_postpay_ajax_validation_returns_422_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-08',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);
    }

    public function test_journal_team_filter_other_team_hides_due_in_json(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) ((int) $team->id + 99999),
            ])
            ->assertOk()
            ->assertJsonPath('result.payment_status.state', 'none');
    }

    public function test_place_flexible_ajax_includes_payment_status_none_for_non_postpay(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.payment_status.state', 'none')
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'payment_status' => ['state', 'icon_class', 'hover', 'amount_cents', 'amount_label'],
                ],
            ]);
    }

    public function test_place_trial_and_single_ajax_include_payment_status(): void
    {
        $this->grantLessonPackagesView();
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();

        $trial = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-08-10', [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            );
        $trial->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.payment_status.state', 'none');

        $single = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-08-11', 1000, [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            );
        $single->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.payment_status.state', 'none');
    }

    public function test_guest_ajax_mutations_are_unauthorized_not_empty_200(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertUnauthorized();
    }
}
