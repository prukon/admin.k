<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\ScheduleJournalMonthService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * Ховер гибкого в колонке «Абонемент»: цена «…по абонементу "X" за Y руб» (в т.ч. «за 0 руб»).
 *
 * P1: UI-маркеры index + AJAX place (fee_amount_cents) + non-AJAX safety-net + доступ.
 * P2: страница → place → повторный GET с ценой в tooltip без «пустого 200».
 *
 * @see ScheduleJournalFlexibleContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalFlexibleHoverPriceContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_index_flexible_hint_hover_includes_price_and_data_attr(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 3, feeAmountCents: 123400);
        $hover = ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
            (string) $ulp->lessonPackage?->name,
            123400,
        );
        $hoverHtml = e($hover);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee('data-fee-amount-cents="123400"', false)
            ->assertSee($hoverHtml, false)
            ->assertSee('data-kids-tooltip-hint="1"', false)
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('за 1 234 руб', $html);
    }

    public function test_index_flexible_hint_hover_zero_fee_writes_za_0_rub(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2, feeAmountCents: 0);
        $hoverHtml = e(ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
            (string) $ulp->lessonPackage?->name,
            0,
        ));

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('data-fee-amount-cents="0"', false)
            ->assertSee($hoverHtml, false)
            ->assertSee('за 0 руб', false);
    }

    public function test_index_multi_flexible_hover_includes_price_per_line(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $ulpA = $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-09-01', lessons: 5, feeAmountCents: 100000);
        $ulpB = $this->makeMonthlyFlexibleAssignment($student, (int) $teamB->id, '2026-09-01', lessons: 4, feeAmountCents: 0);

        $lineA = e(ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
            (string) $ulpA->lessonPackage?->name,
            100000,
            true,
            5,
            5,
        ));
        $lineB = e(ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
            (string) $ulpB->lessonPackage?->name,
            0,
            true,
            4,
            4,
        ));

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => 'all']))
            ->assertOk()
            ->assertSee('journal-flexible-hint--multi', false)
            ->assertSee($lineA, false)
            ->assertSee($lineB, false)
            ->assertSee('fee_amount_cents', false);
    }

    public function test_place_flexible_ajax_returns_fee_amount_cents_in_result(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2, feeAmountCents: 77700);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-10')
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.fee_amount_cents', 77700)
            ->assertJsonPath('result.slots_remaining', 2)
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
            ]);

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $this->assertNotSame('', (string) $response->json('message'));
    }

    public function test_place_flexible_ajax_zero_fee_still_returns_fee_amount_cents_zero(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2, feeAmountCents: 0);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-11')
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.fee_amount_cents', 0);
    }

    public function test_place_flexible_non_ajax_redirects_and_creates_utss_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2, feeAmountCents: 250000);

        $response = $this->from(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->post(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-12')
            );

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->status());
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_guest_denied_index_context_and_place(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        Auth::logout();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertRedirect();

        $this->getJson(route('schedule.abonement.flexible-context', $student).'?'.http_build_query([
            'occurrence_date' => '2026-09-10',
        ]))->assertUnauthorized();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-10')
            )
            ->assertUnauthorized();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_without_schedule_view_index_context_and_place_return_403(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2);
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.flexible-context', $student).'?'.http_build_query([
                'occurrence_date' => '2026-09-10',
                'context_team_id' => $team->id,
            ]))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-10')
            )
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    /**
     * P2: index (hover с ценой) → place AJAX (fee в result) → повторный GET с обновлённым остатком и ценой.
     */
    public function test_flexible_hover_price_workflow_visible_without_reload_markers(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['name' => 'Цена', 'lastname' => 'Ховера']);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2, feeAmountCents: 90000);
        $hoverHtml = e(ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
            (string) $ulp->lessonPackage?->name,
            90000,
        ));

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('flexiblePlaceModal', false)
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee($hoverHtml, false)
            ->assertSee('data-fee-amount-cents="90000"', false)
            ->assertSee($student->full_name, false);

        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.abonement.place-flexible', $student),
                $this->placeFlexiblePayload($ulp, (int) $team->id, '2026-09-15', [
                    'comment' => 'Hover price workflow',
                ])
            );
        $place->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.fee_amount_cents', 90000)
            ->assertJsonPath('result.slots_remaining', 2)
            ->assertJsonPath('result.comment', 'Hover price workflow');
        $this->assertNotSame('', (string) $place->json('message'));

        $pageAfter = $this->get(route('schedule.index', ['year' => 2026, 'month' => '09', 'team' => $team->id]));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('journal-flexible-hint--ratio', false)
            ->assertSee(">2/2\nГибкий<", false)
            ->assertSee($hoverHtml, false)
            ->assertSee('data-fee-amount-cents="90000"', false)
            ->assertSee('data-occurrence-count="1"', false);

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
        ]);
    }

    public function test_service_payload_includes_fee_amount_cents_for_assignable_flexible(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-09-01', lessons: 2, feeAmountCents: 5500);

        $byUser = app(ScheduleJournalMonthService::class)->flexibleAssignableByUserForBillingMonth(
            (int) $this->partner->id,
            [(int) $student->id],
            '2026-09-01',
            (string) $team->id,
        );

        $this->assertArrayHasKey((int) $student->id, $byUser);
        $this->assertSame(5500, (int) ($byUser[(int) $student->id][0]['fee_amount_cents'] ?? -1));
    }
}
