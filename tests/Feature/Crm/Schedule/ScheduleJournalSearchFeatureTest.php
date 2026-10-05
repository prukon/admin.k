<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Http\Controllers\Admin\ScheduleController;
use App\Models\Team;
use App\Models\User;

/**
 * Серверный поиск q в журнале /schedule: кнопка «Найти» / Enter в поле (GET-форма).
 * Ищет по всей выборке, не только по текущей странице. Автопоиска при вводе нет.
 *
 * P1: UX «ученик на стр. 2 не виден без q, виден с q»; пагинация результатов поиска;
 * комбинация с фильтром группы; пустой q; AJAX GET — HTML, не пустой JSON.
 *
 * @see ScheduleJournalPaginationFeatureTest
 * @see ScheduleJournalSearchAccessFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalSearchFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_search_form_submit_via_get_finds_student_without_page_param(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1, 'ЖурналФорма');
        $target = $students[$perPage];

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => $target->lastname,
        ]))->assertOk()->getContent();

        $form = $this->searchFormHtml($html);
        $this->assertStringContainsString('method="get"', $form);
        $this->assertStringContainsString('<button type="submit"', $form);
        $this->assertStringContainsString('Найти', $form);
        $this->assertStringNotContainsString('name="page"', $form);
        $this->assertSame(0, $this->journalRowUserIds($html)->count());
        $this->assertStringContainsString('schedule-group-count">1', $html);

        $rows = $this->groupRowsHtml('none', 1, ['q' => $target->lastname]);
        $this->assertTrue($this->journalRowUserIds($rows)->contains((int) $target->id));
        $this->assertStringContainsString($target->full_name, $rows);
    }

    public function test_search_finds_student_on_second_page_without_opening_page_two(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1, 'ЖурналПоискСтр');
        $first = $students[0];
        $onSecondPage = $students[$perPage];
        $onSecondPage->update(['name' => 'ВторСтр', 'lastname' => 'ЖурналПоискСтр'.str_pad((string) $perPage, 3, '0', STR_PAD_LEFT)]);

        $page1Html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame(0, $this->journalRowUserIds($page1Html)->count());
        $this->assertStringNotContainsString($onSecondPage->full_name, $page1Html);
        $page1Rows = $this->groupRowsHtml('none');
        $this->assertFalse($this->journalRowUserIds($page1Rows)->contains((int) $onSecondPage->id));
        $this->assertTrue($this->journalRowUserIds($page1Rows)->contains((int) $first->id));

        $searchHtml = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => 'ВторСтр',
        ]))->assertOk()->getContent();

        $this->assertNotSame('', trim($searchHtml));
        $this->assertStringNotContainsString('Whoops', $searchHtml);
        $this->assertStringContainsString('id="schedule-table"', $searchHtml);
        $this->assertSame(0, $this->journalRowUserIds($searchHtml)->count());
        $this->assertStringContainsString('schedule-group-count">1', $searchHtml);
        $this->assertJournalPagerRendered($searchHtml, false);
        $searchRows = $this->groupRowsHtml('none', 1, ['q' => 'ВторСтр']);
        $this->assertSame(1, $this->journalRowUserIds($searchRows)->count());
        $this->assertTrue($this->journalRowUserIds($searchRows)->contains((int) $onSecondPage->id));
        $this->assertFalse($this->journalRowUserIds($searchRows)->contains((int) $first->id));
        $this->assertStringContainsString($onSecondPage->full_name, $searchRows);
        $this->assertStringContainsString('value="ВторСтр"', $this->searchFormHtml($searchHtml));
    }

    public function test_search_by_first_name_finds_student_on_any_page(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1, 'ЖурналПоискИмя');
        $target = $students[$perPage];
        $target->update(['name' => 'УникальноеИмяПоиск', 'lastname' => 'ЖурналПоискИмя'.str_pad((string) $perPage, 3, '0', STR_PAD_LEFT)]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => 'УникальноеИмяПоиск',
        ]))->assertOk()->getContent();

        $this->assertSame(0, $this->journalRowUserIds($html)->count());
        $this->assertStringContainsString('schedule-group-count">1', $html);
        $this->assertStringContainsString('УникальноеИмяПоиск', $html);

        $rows = $this->groupRowsHtml('none', 1, ['q' => 'УникальноеИмяПоиск']);
        $this->assertSame(1, $this->journalRowUserIds($rows)->count());
        $this->assertTrue($this->journalRowUserIds($rows)->contains((int) $target->id));
    }

    public function test_search_by_full_name_concatenation_finds_student(): void
    {
        $student = $this->makeStudent();
        $student->update(['lastname' => 'СклейкаФам', 'name' => 'ПоискИмя']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => 'СклейкаФам ПоискИмя',
        ]))->assertOk()->getContent();

        $this->assertSame(0, $this->journalRowUserIds($html)->count());
        $rows = $this->groupRowsHtml('none', 1, ['q' => 'СклейкаФам ПоискИмя']);
        $this->assertTrue($this->journalRowUserIds($rows)->contains((int) $student->id));
        $this->assertStringContainsString($student->full_name, $rows);
    }

    public function test_search_with_many_matches_paginates_search_results(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $this->seedJournalStudents($perPage + 5, 'ЖурналМногоПоиск');

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => 'ЖурналМногоПоиск',
        ]))->assertOk()->getContent();

        $this->assertSame(0, $this->journalRowUserIds($html)->count());
        $this->assertStringContainsString('schedule-group-count">'.($perPage + 5), $html);
        $this->assertJournalPagerRendered($html, false);

        $rows = $this->groupRowsHtml('none', 1, ['q' => 'ЖурналМногоПоиск']);
        $this->assertSame($perPage, $this->journalRowUserIds($rows)->count());
        $this->assertJournalPagerRendered($rows, true);
        $this->assertStringContainsString(
            '1–'.$perPage.'</span> <span class="schedule-journal-pagination__of">из '.($perPage + 5),
            $rows
        );
        $this->assertStringContainsString('group_pages', $rows);
        $this->assertStringContainsString('schedule-group-page-link', $rows);
        $this->assertStringContainsString('ЖурналМногоПоиск', $this->searchFormHtml($html));
    }

    public function test_second_page_of_search_results_shows_remaining_matches(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 3, 'ЖурналПоискДве');
        $overflow = $students[$perPage];

        $html = $this->groupRowsHtml('none', 2, ['q' => 'ЖурналПоискДве']);

        $ids = $this->journalRowUserIds($html);
        $this->assertSame(3, $ids->count());
        $this->assertTrue($ids->contains((int) $overflow->id));
        $this->assertStringContainsString($overflow->full_name, $html);
        $this->assertStringContainsString(($perPage + 1).'–'.($perPage + 3), $html);
    }

    public function test_empty_search_query_restores_full_paginated_list(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $this->seedJournalStudents($perPage + 1, 'ЖурналСбросQ');

        $filtered = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => 'ЖурналСбросQ999',
        ]))->assertOk()->getContent();
        $this->assertSame(0, $this->journalRowUserIds($filtered)->count());
        $this->assertStringNotContainsString('schedule-group-count">', $filtered);

        $restored = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => '',
        ]))->assertOk()->getContent();

        $this->assertSame(0, $this->journalRowUserIds($restored)->count());
        $this->assertStringContainsString('schedule-group-count">'.($perPage + 1), $restored);
        $restoredRows = $this->groupRowsHtml('none');
        $this->assertSame($perPage, $this->journalRowUserIds($restoredRows)->count());
        $this->assertJournalPagerRendered($restoredRows, true);
        $this->assertStringNotContainsString('name="q" value="', $this->searchFormHtml($restored));
    }

    public function test_search_combined_with_team_filter_narrows_results(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'ГруппаПоискА']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'ГруппаПоискБ']);

        $studentA = $this->makeStudent($teamA->id);
        $studentA->update(['lastname' => 'ОбщийПоиск', 'name' => 'ВГруппеА']);

        $studentB = $this->makeStudent($teamB->id);
        $studentB->update(['lastname' => 'ОбщийПоиск', 'name' => 'ВГруппеБ']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $teamA->id,
            'q' => 'ОбщийПоиск',
        ]))->assertOk()->getContent();

        $this->assertSame(0, $this->journalRowUserIds($html)->count());
        $this->assertStringContainsString('schedule-group-count">1', $html);
        $rows = $this->groupRowsHtml((string) $teamA->id, 1, [
            'team' => $teamA->id,
            'q' => 'ОбщийПоиск',
        ]);
        $ids = $this->journalRowUserIds($rows);
        $this->assertSame(1, $ids->count());
        $this->assertTrue($ids->contains((int) $studentA->id));
        $this->assertFalse($ids->contains((int) $studentB->id));
        $this->assertStringContainsString('name="team_ids[]" value="'.$teamA->id.'"', $this->searchFormHtml($html));
    }

    public function test_search_with_no_matches_returns_empty_student_rows(): void
    {
        $this->makeStudentTeamAndTrainer();

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => 'НетТакогоУченикаXYZ123',
        ]))->assertOk()->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertSame(0, $this->journalRowUserIds($html)->count());
        $this->assertJournalPagerRendered($html, false);
        $this->assertStringContainsString('value="НетТакогоУченикаXYZ123"', $this->searchFormHtml($html));
    }

    public function test_ajax_get_with_search_returns_html_not_empty_json(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();
        $student->update(['lastname' => 'AjaxПоиск', 'name' => 'Журнал']);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'team' => 'all',
                'q' => 'AjaxПоиск',
            ]));
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringContainsString('id="table-search"', $html);
        $this->assertStringContainsString('schedule-group-count">1', $html);
        $this->assertStringNotContainsString($student->full_name, $html);
        $this->assertStringNotContainsString('"success":true', $html);
        $this->assertStringNotContainsString('Whoops', $html);
    }

    public function test_search_from_page_two_with_q_resets_to_first_page_of_matches(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1, 'ЖурналСбросСтр');
        $first = $students[0];
        $overflow = $students[$perPage];

        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'page' => 2,
        ]))->assertOk();

        $searchHtml = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => $first->lastname,
        ]))->assertOk()->getContent();

        $this->assertSame(0, $this->journalRowUserIds($searchHtml)->count());
        $this->assertJournalPagerRendered($searchHtml, false);
        $rows = $this->groupRowsHtml('none', 1, ['q' => $first->lastname]);
        $ids = $this->journalRowUserIds($rows);
        $this->assertTrue($ids->contains((int) $first->id));
        $this->assertFalse($ids->contains((int) $overflow->id));
        $this->assertMatchesRegularExpression(
            '#number-line">1</td#',
            $this->studentRowHtml($rows, (int) $first->id) ?? ''
        );
    }

    /**
     * @return list<User>
     */
    private function seedJournalStudents(int $count, string $lastnamePrefix = 'ЖурналПоиск'): array
    {
        return User::factory()
            ->count($count)
            ->sequence(fn ($sequence) => [
                'lastname' => sprintf('%s%03d', $lastnamePrefix, $sequence->index),
                'name' => 'Тест',
                'partner_id' => $this->partner->id,
                'role_id' => $this->studentRoleId(),
                'is_enabled' => 1,
                'team_id' => null,
            ])
            ->create()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function groupRowsHtml(string $groupKey, int $page = 1, array $extra = []): string
    {
        return (string) $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get(route('schedule.group-rows', array_merge([
            'year' => 2026,
            'month' => '08',
            'group_key' => $groupKey,
            'group_page' => $page,
        ], $extra)))->assertOk()->getContent();
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

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function journalRowUserIds(string $html): \Illuminate\Support\Collection
    {
        preg_match_all('/<tr[^>]*\bdata-user-id="(\d+)"/', $html, $matches);

        return collect($matches[1] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
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

    private function assertJournalPagerRendered(string $html, bool $visible): void
    {
        if ($visible) {
            $this->assertStringContainsString('class="schedule-journal-pagination', $html);

            return;
        }

        $this->assertStringNotContainsString('class="schedule-journal-pagination', $html);
    }
}
