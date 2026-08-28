<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\UserPrice;
use App\Services\Schedule\JournalMonthlyPaymentStatusService;
use App\Services\TeamUserSyncService;
use App\Support\Money;

/**
 * Колонка оплаты: сумма к оплате неоплаченной постоплаты, шапки tooltip-hint, P2 workflow.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalMonthlyPaymentStatusFeatureTest
 */
final class ScheduleJournalPostpayPaymentDueFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_journal_headers_use_tooltip_hint_partial(): void
    {
        $this->makeStudentWithTeam();

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('journal-col-header-hint', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('title="Статус оплаты"', $html);
        $this->assertStringContainsString('title="Кол-во посещений"', $html);
        $this->assertStringContainsString('title="Название абонемента"', $html);
        $this->assertStringContainsString('fa-ruble-sign', $html);
        $this->assertStringContainsString('fa-person-circle-check', $html);
        $this->assertStringContainsString('fa-ticket', $html);
        $this->assertStringNotContainsString('Whoops', $html);
    }

    public function test_unpaid_postpay_zero_price_shows_empty_payment_cell(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $this->assertSame('', $this->journalPaymentCellHtml($html, (int) $student->id));
        $this->assertNull($this->journalPaymentStatusInHtml($html, (int) $student->id));
        $this->assertStringNotContainsString('data-journal-payment-status="due"', $this->journalStudentRowHtml($html, (int) $student->id) ?? '');
    }

    public function test_two_unpaid_postpay_groups_sum_due_amount(): void
    {
        [$student, $teamA, , $package] = $this->makeStudentWithPostpayMonth(180000, false, 0, 120000);
        $student->update(['lastname' => 'СуммаПост', 'name' => 'Студент'.uniqid()]);
        $student = $student->fresh();
        $teamA->update(['title' => 'ПостА-'.uniqid(), 'order_by' => 1]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ПостБ-'.uniqid(),
            'order_by' => 2,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        UserPrice::query()->create([
            'user_id' => $student->id,
            'team_id' => $teamB->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $package->id,
            'price_cents' => 120000,
            'is_paid' => 0,
        ]);

        $allHtml = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $allCell = $this->journalPaymentCellHtml($allHtml, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="due"', $allCell);
        $this->assertStringContainsString('3000₽', $allCell);
        $this->assertStringContainsString('Не оплачено:', $allCell);

        $teamHtml = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $teamA->id,
        ]))->assertOk()->getContent();
        $teamCell = $this->journalPaymentCellHtml($teamHtml, (int) $student->id);
        $this->assertSame('due', $this->journalPaymentStatusInHtml($teamHtml, (int) $student->id));
        $this->assertStringContainsString('1800₽', $teamCell);
        $this->assertStringNotContainsString('3000₽', $teamCell);
    }

    public function test_custom_consumes_lesson_status_shows_due_on_page(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);
        $custom = $this->createCustomOccurrenceStatus('Списывает пост');
        $custom->consumes_lesson = true;
        $custom->save();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-08',
                'lesson_occurrence_status_id' => $custom->id,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.payment_status.state', 'due')
            ->assertJsonPath('result.payment_status.amount_cents', 50000);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();
        $cell = $this->journalPaymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="due"', $cell);
        $this->assertStringContainsString('500₽', $cell);
        $this->assertStringNotContainsString('fa-circle-check', $cell);
    }

    public function test_non_consuming_status_does_not_show_due(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);
        $scheduledId = $this->occurrenceStatusIdByCode(LessonOccurrenceStatus::CODE_SCHEDULED);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-09',
                'lesson_occurrence_status_id' => $scheduledId,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.payment_status.state', 'none')
            ->assertJsonPath('result.payment_status.amount_cents', 0);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();
        $this->assertSame('', $this->journalPaymentCellHtml($html, (int) $student->id));
    }

    public function test_due_amount_label_includes_discount(): void
    {
        $payable = Money::payableAfterDiscountCents(150000, 10);
        [$student, $team] = $this->makeStudentWithPostpayMonth($payable, false, 10, 50000);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $expected = JournalMonthlyPaymentStatusService::dueAmountLabel($payable);
        $cell = $this->journalPaymentCellHtml($html, (int) $student->id);
        $this->assertSame('due', $this->journalPaymentStatusInHtml($html, (int) $student->id));
        $this->assertStringContainsString($expected, $cell);
        $this->assertSame(135000, $payable);
        $this->assertSame('1350₽', $expected);
    }

    /**
     * P2: страница → AJAX create_postpay «Посетил» → JSON payment_status без reload,
     * повторный GET показывает сумму.
     */
    public function test_postpay_due_workflow_page_ajax_create_visible_without_reload(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);
        $student->update(['name' => 'Постоплата', 'lastname' => 'Workflow']);

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('id="cellEditModal"', false)
            ->assertSee('id="cellEditForm"', false)
            ->assertSee('js/schedule-journal.js', false)
            ->assertSee('schedule-payment-status', false)
            ->assertSee($student->full_name, false);
        $this->assertSame('', $this->journalPaymentCellHtml($html, (int) $student->id));

        $save = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ]);
        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.created', true)
            ->assertJsonPath('result.payment_status.state', 'due')
            ->assertJsonPath('result.payment_status.amount_cents', 50000)
            ->assertJsonPath('result.payment_status.amount_label', '500₽');
        $this->assertNotSame('', (string) $save->json('message'));

        $pageAfter = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]));
        $pageAfter->assertOk();
        $afterHtml = (string) $pageAfter->getContent();
        $this->assertNotSame('', trim($afterHtml));
        $this->assertSame('due', $this->journalPaymentStatusInHtml($afterHtml, (int) $student->id));
        $this->assertStringContainsString('500₽', $this->journalPaymentCellHtml($afterHtml, (int) $student->id));
        $pageAfter->assertSee($student->full_name, false);
    }
}
