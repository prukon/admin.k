<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\Auth;

/**
 * P1: non-AJAX safety-net суммы постоплаты в колонке оплаты:
 * POST/DELETE без X-Requested-With → 302 на /schedule, запись в БД, не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalPostpayPaymentDueNonAjaxSafetyNetFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_create_postpay_non_ajax_redirects_persists_and_column_shows_due(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $response = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'create_postpay' => 1,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'journal_team_filter' => (string) $team->id,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Статус занятия сохранён.');
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);
        $row = UserPrice::query()
            ->where('user_id', $student->id)
            ->where('team_id', $team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(50000, (int) $row->price_cents);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();
        $this->assertSame('due', $this->journalPaymentStatusInHtml($html, (int) $student->id));
        $this->assertStringContainsString('500₽', $this->journalPaymentCellHtml($html, (int) $student->id));
    }

    public function test_create_postpay_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['lesson_occurrence_status_id']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(
            0,
            UserLessonOccurrenceStatusEvent::query()->where('user_id', $student->id)->count()
        );
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_destroy_non_ajax_redirects_and_due_column_becomes_empty(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $create = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ]);
        $create->assertOk();
        $utssId = (int) $create->json('result.utss_id');

        $response = $this->flushHeaders()->delete(route('schedule.occurrence.destroy', $utssId), [
            '_token' => csrf_token(),
            'occurrence_date' => '2026-08-03',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Занятие удалено.');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utssId]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();
        $this->assertSame('', $this->journalPaymentCellHtml($html, (int) $student->id));
        $this->assertNull($this->journalPaymentStatusInHtml($html, (int) $student->id));
    }

    public function test_guest_non_ajax_mutations_redirect_not_empty_200(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $update = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'create_postpay' => 1,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);
        $this->assertNotSame(500, $update->getStatusCode());
        $this->assertNotSame(200, $update->getStatusCode());
        $update->assertRedirect();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_without_schedule_view_non_ajax_create_postpay_is_403_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $response = $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }
}
