<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\Schedule\JournalMonthlyPaymentStatusService;
use App\Services\TeamUserSyncService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;

/**
 * Колонка оплаты месяца в журнале /schedule.
 *
 * P1: HTTP (200/403/401/302), UX-баг «зелёная галочка на неоплаченной группе»,
 * разметка строки ученика, фильтр группы, tooltip-hint, граничные кейсы.
 * Неоплаченная постоплата — сумма к оплате (due); оплаченная — галочка.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalMonthlyPaymentStatusFeatureTest extends ScheduleJournalTestCase
{
    private JournalMonthlyPaymentStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        $this->service = app(JournalMonthlyPaymentStatusService::class);
    }

    public function test_guest_is_redirected_from_journal_with_payment_filters(): void
    {
        Auth::logout();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertStatus(302);
        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 1]))
            ->assertStatus(302);
    }

    public function test_guest_json_request_to_journal_is_unauthorized(): void
    {
        Auth::logout();

        $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertStatus(401);
        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->assertStatus(401);
    }

    public function test_manager_without_schedule_view_gets_403_on_journal_payment_column(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertStatus(403);
    }

    public function test_viewer_with_schedule_view_gets_non_empty_journal_page(): void
    {
        [$student] = $this->makeStudentWithTeam();

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('id="filter-team"', false);
        $page->assertSee('schedule-payment-status', false);
        $page->assertSee($student->full_name, false);
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringNotContainsString('Undefined variable', $html);
    }

    public function test_ajax_get_journal_does_not_return_empty_or_server_error(): void
    {
        $this->makeStudentWithTeam();

        $page = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringNotContainsString('"success":true', $html);
    }

    public function test_partially_paid_student_does_not_look_fully_paid_on_all_groups_view(): void
    {
        [$student, $paidTeam, $unpaidTeam] = $this->makeStudentWithTwoPricedTeams(true, false);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $page->assertOk();
        $html = (string) $page->getContent();

        $page->assertSee($student->full_name, false);
        $this->assertFilterOptionSelected($html, 'all');
        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertNotSame('', $cell);
        $this->assertStringContainsString('data-journal-payment-status="partial"', $cell);
        $this->assertStringNotContainsString('data-journal-payment-status="paid"', $cell);
        $this->assertStringContainsString('text-warning', $cell);
        $this->assertStringNotContainsString('text-success', $cell);
        $this->assertStringContainsString('journal-monthly-payment-hint', $cell);
        $this->assertStringContainsString('kids-tooltip-hint', $cell);
        $this->assertStringContainsString('data-kids-tooltip-hint', $cell);
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $cell);
        $this->assertStringContainsString('Оплачено: '.$paidTeam->title, $cell);
        $this->assertStringContainsString('Не оплачено: '.$unpaidTeam->title, $cell);
        $this->assertStringNotContainsString(JournalMonthlyPaymentStatusService::HOVER_ALL_GROUPS_PAID, $cell);

        $row = $this->service->statusesByUser(
            (int) $this->partner->id,
            [(int) $student->id],
            '2026-08-01',
            'all',
        )[(int) $student->id];
        $this->assertSame(JournalMonthlyPaymentStatusService::STATE_PARTIAL, $row['state']);
    }

    public function test_unpaid_group_filter_shows_student_without_green_check(): void
    {
        [$student, $paidTeam, $unpaidTeam] = $this->makeStudentWithTwoPricedTeams(true, false);

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $unpaidTeam->id,
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();

        $page->assertSee($student->full_name, false);
        $this->assertFilterOptionSelected($html, (string) $unpaidTeam->id);
        $this->assertFilterOptionNotSelected($html, 'all');
        $this->assertNotNull($this->studentRowHtml($html, (int) $student->id));
        $this->assertSame('', $this->paymentCellHtml($html, (int) $student->id));
        $this->assertNull($this->paymentStatusInHtml($html, (int) $student->id));
        $this->assertStringNotContainsString('data-journal-payment-status="paid"', $html);
        $this->assertStringNotContainsString('data-journal-payment-status="partial"', $html);
        $this->assertStringNotContainsString('fa-circle-check', $this->studentRowHtml($html, (int) $student->id) ?? '');

        $paidPage = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $paidTeam->id,
        ]));
        $paidPage->assertOk()->assertSee($student->full_name, false);
        $this->assertSame('paid', $this->paymentStatusInHtml((string) $paidPage->getContent(), (int) $student->id));
    }

    public function test_paid_group_filter_shows_green_check_without_partial_hover(): void
    {
        [$student, $paidTeam] = $this->makeStudentWithTwoPricedTeams(true, false);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $paidTeam->id,
        ]))->assertOk()->getContent();

        $this->assertFilterOptionSelected($html, (string) $paidTeam->id);
        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="paid"', $cell);
        $this->assertStringContainsString('text-success', $cell);
        $this->assertStringNotContainsString('text-warning', $cell);
        $this->assertStringNotContainsString('journal-monthly-payment-hint', $cell);
        $this->assertStringNotContainsString('Оплачено:', $cell);
        $this->assertStringNotContainsString(JournalMonthlyPaymentStatusService::HOVER_ALL_GROUPS_PAID, $cell);
        $this->assertStringNotContainsString('kids-tooltip-hint', $cell);
    }

    public function test_all_groups_paid_shows_green_check_and_all_paid_hover(): void
    {
        [$student] = $this->makeStudentWithTwoPricedTeams(true, true);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();

        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="paid"', $cell);
        $this->assertStringContainsString('text-success', $cell);
        $this->assertStringContainsString(JournalMonthlyPaymentStatusService::HOVER_ALL_GROUPS_PAID, $cell);
        $this->assertStringContainsString('journal-monthly-payment-hint', $cell);
        $this->assertStringContainsString('kids-tooltip-hint', $cell);
        $this->assertStringNotContainsString('Оплачено:', $cell);
        $this->assertStringNotContainsString('Не оплачено:', $cell);
    }

    public function test_single_paid_group_shows_green_check_without_all_paid_hover(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->createMonthPrice((int) $student->id, (int) $team->id, true, 500000);

        $allHtml = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();
        $allCell = $this->paymentCellHtml($allHtml, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="paid"', $allCell);
        $this->assertStringNotContainsString(JournalMonthlyPaymentStatusService::HOVER_ALL_GROUPS_PAID, $allCell);
        $this->assertStringNotContainsString('journal-monthly-payment-hint', $allCell);

        $teamHtml = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();
        $teamCell = $this->paymentCellHtml($teamHtml, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="paid"', $teamCell);
        $this->assertStringNotContainsString(JournalMonthlyPaymentStatusService::HOVER_ALL_GROUPS_PAID, $teamCell);
    }

    public function test_both_groups_unpaid_shows_no_payment_check(): void
    {
        [$student] = $this->makeStudentWithTwoPricedTeams(false, false);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->getContent();

        $this->assertSame('', $this->paymentCellHtml($html, (int) $student->id));
        $this->assertNull($this->paymentStatusInHtml($html, (int) $student->id));
        $this->assertStringNotContainsString('fa-circle-check', $this->studentRowHtml($html, (int) $student->id) ?? '');
    }

    public function test_none_filter_hides_payment_check_even_if_month_is_paid(): void
    {
        $student = $this->makeStudent(null);
        $student->update(['lastname' => 'БезГрОпл', 'name' => 'Студент'.uniqid()]);
        $student = $student->fresh();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->createMonthPrice((int) $student->id, (int) $team->id, true, 500000);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'none',
        ]))->assertOk()->getContent();

        $this->assertFilterOptionSelected($html, 'none');
        $this->assertStringContainsString($student->full_name, $html);
        $this->assertSame('', $this->paymentCellHtml($html, (int) $student->id));
        $this->assertNull($this->paymentStatusInHtml($html, (int) $student->id));
    }

    public function test_zero_price_charge_does_not_count_as_paid_or_unpaid_group(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Ноль-'.uniqid(),
            'order_by' => 2,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $this->createMonthPrice((int) $student->id, (int) $teamA->id, true, 0);
        $this->createMonthPrice((int) $student->id, (int) $teamB->id, false, 400000);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->getContent();

        $this->assertSame('', $this->paymentCellHtml($html, (int) $student->id));
        $this->assertNull($this->paymentStatusInHtml($html, (int) $student->id));

        $row = $this->service->statusesByUser(
            (int) $this->partner->id,
            [(int) $student->id],
            '2026-08-01',
            'all',
        )[(int) $student->id];
        $this->assertSame(JournalMonthlyPaymentStatusService::STATE_NONE, $row['state']);
    }

    public function test_zero_price_row_does_not_force_all_groups_paid_hover_when_one_billable_is_paid(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'НольХовер-'.uniqid(),
            'order_by' => 2,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $this->createMonthPrice((int) $student->id, (int) $teamA->id, true, 500000);
        $this->createMonthPrice((int) $student->id, (int) $teamB->id, true, 0);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();

        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="paid"', $cell);
        $this->assertStringNotContainsString(JournalMonthlyPaymentStatusService::HOVER_ALL_GROUPS_PAID, $cell);
        $this->assertStringNotContainsString('journal-monthly-payment-hint', $cell);
    }

    public function test_manual_unpaid_mark_makes_auto_paid_group_look_unpaid(): void
    {
        [$student, $paidTeam, $unpaidTeam] = $this->makeStudentWithTwoPricedTeams(true, true);
        UserPrice::query()
            ->where('user_id', $student->id)
            ->where('team_id', $unpaidTeam->id)
            ->whereDate('new_month', '2026-08-01')
            ->update(['is_paid' => 1, 'is_manual_paid' => 0]);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();

        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="partial"', $cell);
        $this->assertStringContainsString('Оплачено: '.$paidTeam->title, $cell);
        $this->assertStringContainsString('Не оплачено: '.$unpaidTeam->title, $cell);

        $unpaidFilter = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $unpaidTeam->id,
        ]))->assertOk()->getContent();
        $this->assertSame('', $this->paymentCellHtml($unpaidFilter, (int) $student->id));
    }

    public function test_manual_paid_mark_makes_auto_unpaid_group_look_paid(): void
    {
        [$student, $teamA, $teamB] = $this->makeStudentWithTwoPricedTeams(false, false);
        UserPrice::query()
            ->where('user_id', $student->id)
            ->where('team_id', $teamA->id)
            ->whereDate('new_month', '2026-08-01')
            ->update(['is_paid' => 0, 'is_manual_paid' => 1]);

        $allHtml = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();
        $cell = $this->paymentCellHtml($allHtml, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="partial"', $cell);
        $this->assertStringContainsString('Оплачено: '.$teamA->title, $cell);
        $this->assertStringContainsString('Не оплачено: '.$teamB->title, $cell);

        $paidFilter = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $teamA->id,
        ]))->assertOk()->getContent();
        $this->assertSame('paid', $this->paymentStatusInHtml($paidFilter, (int) $student->id));
    }

    public function test_unpaid_postpay_shows_due_amount_not_check(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayPrice(360000, false, 0);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="due"', $cell);
        $this->assertStringContainsString('3600₽', $cell);
        $this->assertStringContainsString('journal-monthly-payment-due', $cell);
        $this->assertStringNotContainsString('fa-circle-check', $cell);
        $this->assertStringNotContainsString('data-journal-payment-status="paid"', $cell);
        $this->assertStringNotContainsString('в день', $cell);

        $row = $this->service->statusesByUser(
            (int) $this->partner->id,
            [(int) $student->id],
            '2026-08-01',
            (string) $team->id,
        )[(int) $student->id];
        $this->assertSame(JournalMonthlyPaymentStatusService::STATE_DUE, $row['state']);
        $this->assertSame(360000, $row['amount_cents']);
        $this->assertSame('3600₽', $row['amount_label']);
    }

    public function test_paid_postpay_shows_green_check_not_due_amount(): void
    {
        [$student, $team] = $this->makeStudentWithPostpayPrice(360000, true, 0);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="paid"', $cell);
        $this->assertStringContainsString('text-success', $cell);
        $this->assertStringNotContainsString('data-journal-payment-status="due"', $cell);
        $this->assertStringNotContainsString('journal-monthly-payment-due', $cell);
        $this->assertStringNotContainsString('3600₽', $cell);
    }

    public function test_unpaid_postpay_due_amount_includes_student_discount(): void
    {
        $gross = 360000;
        $payable = Money::payableAfterDiscountCents($gross, 10);
        [$student, $team] = $this->makeStudentWithPostpayPrice($payable, false, 10);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $expected = JournalMonthlyPaymentStatusService::dueAmountLabel($payable);
        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="due"', $cell);
        $this->assertStringContainsString($expected, $cell);
        $this->assertSame(324000, $payable);
        $this->assertSame('3240₽', $expected);
    }

    public function test_regular_unpaid_month_does_not_show_due_amount(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->createMonthPrice((int) $student->id, (int) $team->id, false, 500000);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $this->assertSame('', $this->paymentCellHtml($html, (int) $student->id));
        $this->assertNull($this->paymentStatusInHtml($html, (int) $student->id));
    }

    public function test_unpaid_postpay_plus_paid_regular_shows_due_amount_with_partial_hover(): void
    {
        [$student, $paidTeam] = $this->makeStudentWithTeam();
        $student->update(['lastname' => 'ЖурПост', 'name' => 'Студент'.uniqid()]);
        $student = $student->fresh();
        $paidTeam->update(['title' => 'ОплРег-'.uniqid(), 'order_by' => 1]);
        $postpayTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ПостНеопл-'.uniqid(),
            'order_by' => 2,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $paidTeam->id, (int) $postpayTeam->id]);
        $this->createMonthPrice((int) $student->id, (int) $paidTeam->id, true, 500000);
        $this->createPostpayMonthPrice((int) $student->id, (int) $postpayTeam->id, 180000, false, 0);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();

        $cell = $this->paymentCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="due"', $cell);
        $this->assertStringContainsString('1800₽', $cell);
        $this->assertStringNotContainsString('data-journal-payment-status="partial"', $cell);
        $this->assertStringContainsString('Оплачено: '.$paidTeam->title, $cell);
        $this->assertStringContainsString('Не оплачено: '.$postpayTeam->title, $cell);
    }

    public function test_other_month_payment_does_not_show_check_in_current_month(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->createMonthPrice((int) $student->id, (int) $team->id, true, 500000, '2026-07-01');

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->assertSee($student->full_name, false)
            ->getContent();

        $this->assertSame('', $this->paymentCellHtml($html, (int) $student->id));
    }

    public function test_other_partner_paid_student_does_not_appear_in_journal(): void
    {
        $foreign = $this->makeForeignStudentWithPaidAugust();
        $foreign->update(['lastname' => 'ЧужойОпл', 'name' => 'Студент'.uniqid()]);
        $foreign = $foreign->fresh();

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString((string) $foreign->full_name, $html);
        $this->assertNull($this->studentRowHtml($html, (int) $foreign->id));
    }

    public function test_disabled_student_with_paid_month_is_not_listed(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['lastname' => 'ВыклОпл', 'name' => 'Студент'.uniqid(), 'is_enabled' => 0]);
        $student = $student->fresh();
        $this->createMonthPrice((int) $student->id, (int) $team->id, true, 500000);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString((string) $student->full_name, $html);
        $this->assertNull($this->studentRowHtml($html, (int) $student->id));
    }

    /**
     * @return array{0: User, 1: Team, 2: Team}
     */
    private function makeStudentWithTwoPricedTeams(bool $firstPaid, bool $secondPaid): array
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $student->update(['lastname' => 'ЖурОпл', 'name' => 'Студент'.uniqid()]);
        $student = $student->fresh();
        $teamA->update(['title' => 'ОплА-'.uniqid(), 'order_by' => 1]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ОплБ-'.uniqid(),
            'order_by' => 2,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $teamA->refresh();
        $teamB->refresh();

        $this->createMonthPrice((int) $student->id, (int) $teamA->id, $firstPaid, 500000);
        $this->createMonthPrice((int) $student->id, (int) $teamB->id, $secondPaid, 400000);

        return [$student, $teamA, $teamB];
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function makeStudentWithPostpayPrice(int $priceCents, bool $paid, int $discountPercent): array
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->createPostpayMonthPrice((int) $student->id, (int) $team->id, $priceCents, $paid, $discountPercent);

        return [$student, $team];
    }

    private function createPostpayMonthPrice(
        int $userId,
        int $teamId,
        int $priceCents,
        bool $paid,
        int $discountPercent,
        string $month = '2026-08-01',
    ): UserPrice {
        $package = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->postpay()
            ->create([
                'name' => 'Постоплата журнал',
                'price_cents' => 120000,
            ]);

        return UserPrice::query()->create([
            'user_id' => $userId,
            'team_id' => $teamId,
            'new_month' => $month,
            'lesson_package_id' => $package->id,
            'price_cents' => $priceCents,
            'discount_percent' => $discountPercent,
            'is_paid' => $paid ? 1 : 0,
        ]);
    }

    private function createMonthPrice(
        int $userId,
        int $teamId,
        bool $paid,
        int $priceCents,
        string $month = '2026-08-01',
    ): UserPrice {
        return UserPrice::query()->create([
            'user_id' => $userId,
            'team_id' => $teamId,
            'new_month' => $month,
            'price_cents' => $priceCents,
            'is_paid' => $paid ? 1 : 0,
        ]);
    }

    private function makeForeignStudentWithPaidAugust(): User
    {
        $team = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $this->createMonthPrice((int) $this->foreignUser->id, (int) $team->id, true, 500000);

        return $this->foreignUser;
    }

    private function studentRowHtml(string $html, int $userId): ?string
    {
        if (! preg_match(
            '/<tr[^>]*data-user-id="'.$userId.'"[^>]*>[\s\S]*?<\/tr>/',
            $html,
            $rowMatch
        )) {
            return null;
        }

        return $rowMatch[0];
    }

    private function paymentCellHtml(string $html, int $userId): string
    {
        $row = $this->studentRowHtml($html, $userId);
        if ($row === null) {
            return '';
        }

        if (! preg_match_all('/<td\b[^>]*>[\s\S]*?<\/td>/', $row, $cells) || count($cells[0]) < 3) {
            return '';
        }

        $cell = $cells[0][2];
        if (! str_contains($cell, 'data-journal-payment-status') && ! str_contains($cell, 'fa-circle-check')) {
            return '';
        }

        return $cell;
    }

    private function paymentStatusInHtml(string $html, int $userId): ?string
    {
        $cell = $this->paymentCellHtml($html, $userId);
        if ($cell === '' || ! preg_match('/data-journal-payment-status="([^"]+)"/', $cell, $statusMatch)) {
            return null;
        }

        return $statusMatch[1];
    }

    private function assertFilterOptionSelected(string $html, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote($value, '/').'"[^>]*\bselected\b/',
            $html,
            "Ожидали selected у option value={$value}"
        );
    }

    private function assertFilterOptionNotSelected(string $html, string $value): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.preg_quote($value, '/').'"[^>]*\bselected\b/',
            $html,
            "Не ожидали selected у option value={$value}"
        );
    }
}
