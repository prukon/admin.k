<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Http\Controllers\Admin\ScheduleController;
use App\Models\Team;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;

/**
 * Журнал /schedule: фильтр групп — Select2 generic-multiselect, team_ids[] OR, legacy team.
 *
 * UX-баги: пустой select ≠ «никого»; несколько id — OR, не AND; нет кнопки «Применить»;
 * занятия/оплата/дни недели сужаются по выбранным; поиск и пейджер не теряют team_ids[].
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalTeamFilterSelect2FeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_index_renders_team_filter_as_generic_multiselect(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаSelect2Журнал',
            'is_enabled' => 1,
        ]);

        $html = $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('id="filter-team"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('generic-multiselect-field', false)
            ->assertSee('data-placeholder="Все группы"', false)
            ->getContent();

        $this->assertTrue((bool) preg_match('/<select[^>]*id="filter-team"[^>]*>(.*?)<\/select>/s', $html, $select));
        $this->assertStringContainsString('name="team_ids[]"', $select[0]);
        $this->assertStringContainsString('multiple', $select[0]);
        $this->assertStringNotContainsString('value="all"', $select[1]);
        $this->assertStringContainsString('value="none"', $select[1]);
        $this->assertStringContainsString('ГруппаSelect2Журнал', $select[1]);
        $this->assertStringContainsString('value="'.$team->id.'"', $select[1]);
        $this->assertStringNotContainsString('selected', $select[1]);
        $this->assertTeamFilterHasNoApplyButton($html);
        $this->assertDisabledTeamIsNotAnOption();
    }

    public function test_empty_team_ids_shows_all_students_not_an_empty_table(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $inA = $this->makeStudent($teamA->id);
        $inA->update(['lastname' => 'ПустойФильтр', 'name' => 'А']);
        $inB = $this->makeStudent($teamB->id);
        $inB->update(['lastname' => 'ПустойФильтр', 'name' => 'Б']);
        $ungrouped = $this->makeStudent(null);
        $ungrouped->update(['lastname' => 'ПустойФильтр', 'name' => 'БезГруппы']);

        foreach ([
            ['year' => 2026, 'month' => '08'],
            ['year' => 2026, 'month' => '08', 'team_ids' => []],
            ['year' => 2026, 'month' => '08', 'team' => 'all'],
            ['year' => 2026, 'month' => '08', 'team_ids' => ['all']],
        ] as $query) {
            $html = (string) $this->get(route('schedule.index', $query))->assertOk()->getContent();
            $this->assertNotSame('', trim($html));
            $this->assertStringContainsString($inA->full_name, $html);
            $this->assertStringContainsString($inB->full_name, $html);
            $this->assertStringContainsString($ungrouped->full_name, $html);
            $this->assertStringNotContainsString('Whoops', $html);
            $this->assertTrue((bool) preg_match('/<select[^>]*id="filter-team"[^>]*>(.*?)<\/select>/s', $html, $select));
            $this->assertStringNotContainsString('selected', $select[1]);
            $this->assertStringNotContainsString('name="team_ids[]"', $this->searchFormHtml($html));
        }
    }

    public function test_team_ids_or_shows_students_from_any_selected_group(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'ЖурналОрА']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'ЖурналОрБ']);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'ЖурналОрВ']);

        $studentA = $this->makeStudent($teamA->id);
        $studentA->update(['lastname' => 'ОрФильтр', 'name' => 'А']);
        $studentB = $this->makeStudent($teamB->id);
        $studentB->update(['lastname' => 'ОрФильтр', 'name' => 'Б']);
        $studentC = $this->makeStudent($teamC->id);
        $studentC->update(['lastname' => 'ОрФильтр', 'name' => 'В']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($studentA->full_name, $html);
        $this->assertStringContainsString($studentB->full_name, $html);
        $this->assertStringNotContainsString($studentC->full_name, $html);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$teamA->id.'"\s+selected/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<option value="'.$teamB->id.'"\s+selected/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$teamC->id.'"\s+selected/s',
            $html
        );
        $this->assertStringContainsString('name="team_ids[]" value="'.$teamA->id.'"', $this->searchFormHtml($html));
        $this->assertStringContainsString('name="team_ids[]" value="'.$teamB->id.'"', $this->searchFormHtml($html));
        $this->assertTeamFilterHasNoApplyButton($html);
    }

    public function test_student_in_only_one_of_two_selected_groups_still_appears(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $onlyA = $this->makeStudent($teamA->id);
        $onlyA->update(['lastname' => 'ТолькоОднаИзДвух', 'name' => 'Ученик']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($onlyA->full_name, $html);
        $this->assertSame(1, substr_count($html, '>'.$onlyA->full_name.'<'));
    }

    public function test_none_together_with_team_id_is_or(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $ungrouped = $this->makeStudent(null);
        $ungrouped->update(['lastname' => 'БезГруппыОр', 'name' => 'Ученик']);
        $inTeam = $this->makeStudent($team->id);
        $inTeam->update(['lastname' => 'ВГруппеОр', 'name' => 'Ученик']);
        $other = Team::factory()->create(['partner_id' => $this->partner->id]);
        $otherStudent = $this->makeStudent($other->id);
        $otherStudent->update(['lastname' => 'ЧужаяОр', 'name' => 'Ученик']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => ['none', $team->id],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($ungrouped->full_name, $html);
        $this->assertStringContainsString($inTeam->full_name, $html);
        $this->assertStringNotContainsString($otherStudent->full_name, $html);
        $this->assertMatchesRegularExpression('/<option value="none"\s+selected/s', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$team->id.'"\s+selected/s',
            $html
        );
    }

    public function test_legacy_team_query_still_filters_one_group(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $studentA = $this->makeStudent($teamA->id);
        $studentA->update(['lastname' => 'ЛегасиА', 'name' => 'Ученик']);
        $studentB = $this->makeStudent($teamB->id);
        $studentB->update(['lastname' => 'ЛегасиБ', 'name' => 'Ученик']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $teamA->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($studentA->full_name, $html);
        $this->assertStringNotContainsString($studentB->full_name, $html);
        $this->assertStringContainsString('name="team_ids[]" value="'.$teamA->id.'"', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$teamA->id.'"\s+selected/s',
            $html
        );
    }

    public function test_scalar_team_ids_query_filters_one_group(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $studentA = $this->makeStudent($teamA->id);
        $studentA->update(['lastname' => 'СкалярА', 'name' => 'Ученик']);
        $studentB = $this->makeStudent($teamB->id);
        $studentB->update(['lastname' => 'СкалярБ', 'name' => 'Ученик']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => (string) $teamA->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($studentA->full_name, $html);
        $this->assertStringNotContainsString($studentB->full_name, $html);
    }

    public function test_student_in_two_groups_appears_once_when_both_selected(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent($teamA->id);
        $student->update(['lastname' => 'ДвеГруппы', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id, $teamA->id],
        ]))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '>'.$student->full_name.'<'));
    }

    public function test_single_group_hides_team_titles_under_name_several_groups_show_them(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ПодписьГруппаА',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ПодписьГруппаБ',
        ]);
        $student = $this->makeStudent($teamA->id);
        $student->update(['lastname' => 'ПодписиГрупп', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $one = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id],
        ]))->assertOk()->getContent();
        $rowOne = $this->studentRowHtml($one, (int) $student->id);
        $this->assertNotNull($rowOne);
        $this->assertStringNotContainsString('ПодписьГруппаА', $rowOne);
        $this->assertStringNotContainsString('ПодписьГруппаБ', $rowOne);

        $many = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();
        $rowMany = $this->studentRowHtml($many, (int) $student->id);
        $this->assertNotNull($rowMany);
        $this->assertStringContainsString('ПодписьГруппаА', $rowMany);
        $this->assertStringContainsString('ПодписьГруппаБ', $rowMany);
    }

    public function test_one_group_hides_other_group_occurrence_several_keep_both(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent($teamA->id);
        $student->update(['lastname' => 'ЯчейкиФильтр', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $utssA = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $utssB = $this->createTrialUtss($student, $teamB, '2026-08-04');

        $one = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id],
        ]))->assertOk()->getContent();
        $cellA = $this->cellOpenTag($one, (int) $student->id, '2026-08-03');
        $cellB = $this->cellOpenTag($one, (int) $student->id, '2026-08-04');
        $this->assertStringContainsString('data-utss-id="'.$utssA->id.'"', $cellA);
        $this->assertStringContainsString('data-occurrence-count="1"', $cellA);
        $this->assertStringContainsString('data-occurrence-count="0"', $cellB);
        $this->assertStringNotContainsString('data-utss-id="'.$utssB->id.'"', $cellB);

        $both = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();
        $this->assertStringContainsString(
            'data-utss-id="'.$utssA->id.'"',
            $this->cellOpenTag($both, (int) $student->id, '2026-08-03')
        );
        $this->assertStringContainsString(
            'data-utss-id="'.$utssB->id.'"',
            $this->cellOpenTag($both, (int) $student->id, '2026-08-04')
        );
    }

    public function test_two_selected_groups_use_all_like_payment_one_group_does_not(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ОплатаФильтрА',
            'order_by' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ОплатаФильтрБ',
            'order_by' => 2,
        ]);
        $student = $this->makeStudent($teamA->id);
        $student->update(['lastname' => 'ОплатаМульти', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        UserPrice::query()->create([
            'user_id' => $student->id,
            'team_id' => $teamA->id,
            'new_month' => '2026-08-01',
            'price_cents' => 500000,
            'is_paid' => 1,
        ]);
        UserPrice::query()->create([
            'user_id' => $student->id,
            'team_id' => $teamB->id,
            'new_month' => '2026-08-01',
            'price_cents' => 400000,
            'is_paid' => 0,
        ]);

        $both = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();
        $bothCell = $this->paymentCellHtml($both, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="partial"', $bothCell);
        $this->assertStringContainsString('Оплачено: '.$teamA->title, $bothCell);
        $this->assertStringContainsString('Не оплачено: '.$teamB->title, $bothCell);

        $one = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id],
        ]))->assertOk()->getContent();
        $oneCell = $this->paymentCellHtml($one, (int) $student->id);
        $this->assertStringContainsString('data-journal-payment-status="paid"', $oneCell);
        $this->assertStringNotContainsString('data-journal-payment-status="partial"', $oneCell);
        $this->assertStringNotContainsString($teamB->title, $oneCell);
    }

    public function test_weekday_highlight_is_union_of_selected_groups_and_empty_does_not_highlight(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachWeekdays($teamA, [1]);
        $this->attachWeekdays($teamB, [3]);
        $this->makeStudent($teamA->id);

        $empty = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
        ]))->assertOk()->getContent();
        $this->assertSame(0, $this->theadHighlightCount($empty));

        $one = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id],
        ]))->assertOk()->getContent();
        $this->assertSame(5, $this->theadHighlightCount($one));

        $both = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();
        $this->assertSame(9, $this->theadHighlightCount($both));
    }

    public function test_cell_context_team_id_follows_single_group_otherwise_student_intersection(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent($teamA->id);
        $student->update(['lastname' => 'КонтекстГруппы', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $onlyB = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamB->id],
        ]))->assertOk()->getContent();
        $this->assertStringContainsString(
            'data-context-team-id="'.$teamB->id.'"',
            $this->cellOpenTag($onlyB, (int) $student->id, '2026-08-03')
        );

        $both = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();
        $open = $this->cellOpenTag($both, (int) $student->id, '2026-08-03');
        $this->assertMatchesRegularExpression(
            '/data-context-team-id="('.preg_quote((string) $teamA->id, '/').'|'.preg_quote((string) $teamB->id, '/').')"/',
            $open
        );

        $all = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
        ]))->assertOk()->getContent();
        $allOpen = $this->cellOpenTag($all, (int) $student->id, '2026-08-03');
        $this->assertMatchesRegularExpression(
            '/data-context-team-id="('.preg_quote((string) $teamA->id, '/').'|'.preg_quote((string) $teamB->id, '/').')"/',
            $allOpen
        );
    }

    public function test_journal_tab_keeps_team_ids_in_href(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]))->assertOk()->getContent();

        $this->assertTrue(
            (bool) preg_match('/id="scheduleSectionTabs"[\s\S]*?<\/ul>/', $html, $tabs),
            'Вкладки раздела расписания'
        );
        $this->assertStringContainsString('team_ids', $tabs[0]);
        $this->assertStringContainsString((string) $teamA->id, $tabs[0]);
        $this->assertStringContainsString((string) $teamB->id, $tabs[0]);
    }

    public function test_pager_keeps_team_ids_on_second_page(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $count = ScheduleController::JOURNAL_STUDENTS_PER_PAGE + 1;
        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $student = $this->makeStudent($team->id);
            $student->update([
                'lastname' => sprintf('ПейджГруппа%03d', $i),
                'name' => 'Ученик',
            ]);
            $students[] = $student;
        }

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$team->id],
            'page' => 2,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('class="schedule-journal-pagination', $html);
        $this->assertStringContainsString('team_ids', $html);
        $this->assertStringContainsString((string) $team->id, $html);
        $this->assertStringContainsString($students[$count - 1]->full_name, $html);
        $this->assertStringNotContainsString($students[0]->full_name, $html);
    }

    public function test_other_partner_team_ids_json_returns_422_under_field(): void
    {
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $response = $this->getJson(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$foreignTeam->id],
        ]));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0']);
        $this->assertSame(
            'Выберите группу из списка.',
            $response->json('errors')['team_ids.0'][0] ?? null
        );
        $this->assertSame(
            'Выберите группу из списка.',
            $response->json('errors')['team'][0] ?? null
        );
    }

    public function test_invalid_team_ids_json_returns_422_under_field(): void
    {
        $response = $this->getJson(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => ['foo'],
        ]));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0']);
        $this->assertSame(
            'Выберите группу из списка.',
            $response->json('errors')['team_ids.0'][0] ?? null
        );
    }

    public function test_unknown_team_ids_html_redirects_with_error_under_filter(): void
    {
        $html = (string) $this->from(route('schedule.index'))
            ->followingRedirects()
            ->get(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'team_ids' => [999999],
            ]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/wrap-filter-team[\s\S]*Выберите группу из списка\./',
            $html
        );
    }

    private function assertDisabledTeamIsNotAnOption(): void
    {
        $disabled = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ВыключеннаяГруппаЖурнал',
            'is_enabled' => 0,
        ]);

        $html = (string) $this->get(route('schedule.index'))->assertOk()->getContent();
        $this->assertTrue((bool) preg_match('/<select[^>]*id="filter-team"[^>]*>(.*?)<\/select>/s', $html, $select));
        $this->assertStringNotContainsString('ВыключеннаяГруппаЖурнал', $select[1]);
        $this->assertStringNotContainsString('value="'.$disabled->id.'"', $select[1]);
    }

    private function assertTeamFilterHasNoApplyButton(string $html): void
    {
        $this->assertTrue(
            (bool) preg_match(
                '/class="[^"]*wrap-filter-team[\s\S]*?class="[^"]*wrap-filter-fullscreen/',
                $html,
                $chunk
            ),
            'Блок фильтра групп'
        );
        $this->assertStringNotContainsString('Применить', $chunk[0]);
        $this->assertStringNotContainsString('filter-apply', $chunk[0]);
        $this->assertStringNotContainsString('btn-primary', $chunk[0]);
    }

    private function searchFormHtml(string $html): string
    {
        $this->assertTrue(
            (bool) preg_match(
                '/<form method="get"[^>]*>[\s\S]*?id="table-search"[\s\S]*?<\/form>/',
                $html,
                $match
            ),
            'Не нашли GET-форму поиска журнала'
        );

        return $match[0];
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

    private function cellOpenTag(string $html, int $userId, string $date): string
    {
        $this->assertTrue(
            (bool) preg_match(
                '/<td[^>]*data-user-id="'.$userId.'"[^>]*data-date="'.$date.'"[^>]*>/',
                $html,
                $match
            ),
            "Ячейка user={$userId} date={$date}"
        );

        return $match[0];
    }

    private function paymentCellHtml(string $html, int $userId): string
    {
        $row = $this->studentRowHtml($html, $userId);
        $this->assertNotNull($row);
        $this->assertTrue(
            (bool) preg_match_all('/<td\b[^>]*>[\s\S]*?<\/td>/', $row, $cells) && count($cells[0]) >= 3,
            'Ячейки строки ученика'
        );

        return $cells[0][2];
    }

    private function theadHighlightCount(string $html): int
    {
        $this->assertTrue(
            (bool) preg_match('/<thead>[\s\S]*?<\/thead>/', $html, $thead),
            'Шапка таблицы журнала'
        );

        return substr_count($thead[0], 'highlight-column');
    }
}
