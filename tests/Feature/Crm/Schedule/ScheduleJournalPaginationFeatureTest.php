<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Http\Controllers\Admin\ScheduleController;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Пагинация и серверный поиск учеников в журнале /schedule.
 *
 * P1: HTTP (200/403/401/302/405/422), UX-баг «все ученики на первой странице»,
 * safety-net GET-формы поиска, errors по полям, дефолты пейджера, JS сбрасывает page.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalPaginationFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_guest_is_redirected_from_paginated_journal(): void
    {
        Auth::logout();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertStatus(302);
        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'page' => 2, 'q' => 'Иванов']))
            ->assertStatus(302);
    }

    public function test_guest_json_request_to_paginated_journal_is_unauthorized(): void
    {
        Auth::logout();

        $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertStatus(401);
        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'page' => 2]))
            ->assertStatus(401);
    }

    public function test_manager_without_schedule_view_gets_403_on_paginated_journal(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all', 'page' => 2]))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'q' => 'Иванов']))
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
        $page->assertSee('id="table-search"', false);
        $page->assertSee($student->full_name, false);
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringNotContainsString('Undefined variable', $html);
    }

    public function test_user_without_partner_is_logged_out_from_journal(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
        $this->assertGuest();
    }

    public function test_unsupported_methods_on_journal_index_are_not_server_errors(): void
    {
        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('schedule.index'), [
                'year' => 2026,
                'month' => '08',
            ]);
            $this->assertSame(
                405,
                $response->status(),
                "{$method} /schedule должен быть 405, а не 500/пустой 200"
            );
        }

        $this->postJson(route('schedule.index'), ['year' => 2026])->assertStatus(405);
        $this->patchJson(route('schedule.index'), ['year' => 2026])->assertStatus(405);
        $this->deleteJson(route('schedule.index'))->assertStatus(405);
    }

    public function test_ajax_get_paginated_journal_does_not_return_empty_or_server_error(): void
    {
        $this->makeStudentWithTeam();

        $page = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringContainsString('id="table-search"', $html);
        $this->assertStringNotContainsString('"success":true', $html);
        $this->assertStringNotContainsString('Whoops', $html);
    }

    public function test_invalid_month_on_html_get_redirects_with_error_under_month_filter(): void
    {
        $this->from(route('schedule.index'))
            ->get(route('schedule.index', ['year' => 2026, 'month' => '13']))
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHasErrors(['month' => 'Выберите месяц из списка.']);

        $html = (string) $this->from(route('schedule.index'))
            ->followingRedirects()
            ->get(route('schedule.index', ['year' => 2026, 'month' => '13']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Выберите месяц из списка.', $html);
        $this->assertMatchesRegularExpression(
            '/wrap-filter-month[\s\S]*Выберите месяц из списка\./',
            $html
        );

        $this->from(route('schedule.index'))
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'foo']))
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHasErrors(['team' => 'Выберите группу из списка.']);

        $htmlTeam = (string) $this->from(route('schedule.index'))
            ->followingRedirects()
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'foo']))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/wrap-filter-team[\s\S]*Выберите группу из списка\./',
            $htmlTeam
        );
    }

    public function test_invalid_filters_on_json_get_return_422_with_errors_under_fields(): void
    {
        $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '13']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month'])
            ->assertJsonPath('errors.month.0', 'Выберите месяц из списка.');

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.index', ['year' => 'год', 'month' => '08']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year'])
            ->assertJsonPath('errors.year.0', 'Год должен быть числом.');

        $this->getJson(route('schedule.index', ['year' => 1999, 'month' => '08']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);

        $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'foo']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team'])
            ->assertJsonPath('errors.team.0', 'Выберите группу из списка.');

        $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'page' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page'])
            ->assertJsonPath('errors.page.0', 'Номер страницы должен быть не меньше 1.');

        $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'page' => 'abc']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page'])
            ->assertJsonPath('errors.page.0', 'Номер страницы должен быть числом.');

        $this->getJson(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'q' => str_repeat('я', 192),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q'])
            ->assertJsonPath('errors.q.0', 'Поисковый запрос слишком длинный.');
    }

    public function test_empty_team_is_treated_as_all_groups(): void
    {
        [$student] = $this->makeStudentWithTeam();

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => '',
        ]))->assertOk()->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString($student->full_name, $html);
        $this->assertFilterOptionSelected($html, 'all');
    }

    public function test_month_without_leading_zero_is_accepted(): void
    {
        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '8',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertFilterOptionSelected($html, '08');
        $this->assertStringContainsString('name="month" value="08"', $html);
    }

    public function test_first_page_does_not_render_students_beyond_page_size(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1);
        $first = $students[0];
        $overflow = $students[$perPage];

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($overflow, [(int) $team->id]);
        $this->createTrialUtss($overflow, $team, '2026-08-03');

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertSame($perPage, $this->journalRowUserIds($html)->count());
        $this->assertTrue($this->journalRowUserIds($html)->contains((int) $first->id));
        $this->assertFalse(
            $this->journalRowUserIds($html)->contains((int) $overflow->id),
            'Первая страница не должна рендерить учеников второй — иначе снова OOM на большом списке'
        );
        $this->assertStringNotContainsString($overflow->full_name, $html);
        $this->assertStringContainsString($first->full_name, $html);
        $this->assertJournalPagerRendered($html, true);
        $this->assertStringContainsString('из '.($perPage + 1).' учеников', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertMatchesRegularExpression(
            '#number-line">1</td#',
            $this->studentRowHtml($html, (int) $first->id) ?? ''
        );
    }

    public function test_second_page_shows_remaining_student_with_offset_row_number(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1);
        $first = $students[0];
        $overflow = $students[$perPage];

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'page' => 2,
        ]))->assertOk()->getContent();

        $ids = $this->journalRowUserIds($html);
        $this->assertSame(1, $ids->count());
        $this->assertTrue($ids->contains((int) $overflow->id));
        $this->assertFalse($ids->contains((int) $first->id));
        $this->assertStringContainsString($overflow->full_name, $html);
        $this->assertStringNotContainsString($first->full_name, $html);

        $row = $this->studentRowHtml($html, (int) $overflow->id);
        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression('#number-line">'.($perPage + 1).'</td#', $row);
        $this->assertJournalPagerRendered($html, true);
        $this->assertStringContainsString('Показаны '.($perPage + 1).'–'.($perPage + 1), $html);
    }

    public function test_pager_is_not_shown_when_students_fit_on_one_page(): void
    {
        $this->seedJournalStudents(3, 'ЖурналМало');

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame(3, $this->journalRowUserIds($html)->count());
        $this->assertJournalPagerRendered($html, false);
        $this->assertStringNotContainsString('page=2', $html);
        $this->assertStringNotContainsString('из 3 учеников', $html);
    }

    public function test_exact_page_size_does_not_show_pager(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $this->seedJournalStudents($perPage, 'ЖурналРовно');

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame($perPage, $this->journalRowUserIds($html)->count());
        $this->assertJournalPagerRendered($html, false);
        $this->assertStringNotContainsString('page=2', $html);
    }

    public function test_team_filter_with_few_students_does_not_force_pager(): void
    {
        $this->seedJournalStudents(3, 'ЖурналВнеГруппы');
        [$inTeam, $team] = $this->makeStudentWithTeam();
        $inTeam->update(['lastname' => 'ЖурналВГруппе', 'name' => 'Один']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $this->assertFilterOptionSelected($html, (string) $team->id);
        $this->assertTrue($this->journalRowUserIds($html)->contains((int) $inTeam->id));
        $this->assertSame(1, $this->journalRowUserIds($html)->count());
        $this->assertJournalPagerRendered($html, false);
        $this->assertStringContainsString($inTeam->full_name, $html);
        $this->assertStringNotContainsString('ЖурналВнеГруппы000', $html);
    }

    public function test_search_form_get_filters_by_name_and_does_not_keep_page(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1);
        $first = $students[0];
        $overflow = $students[$perPage];

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => $overflow->lastname,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($overflow->full_name, $html);
        $this->assertStringNotContainsString($first->full_name, $html);
        $this->assertTrue($this->journalRowUserIds($html)->contains((int) $overflow->id));
        $this->assertFalse($this->journalRowUserIds($html)->contains((int) $first->id));
        $this->assertJournalPagerRendered($html, false);

        $form = $this->searchFormHtml($html);
        $this->assertStringNotContainsString('name="page"', $form);
        $this->assertStringContainsString('name="q"', $form);
        $this->assertStringContainsString('value="'.$overflow->lastname.'"', $form);
        $this->assertStringContainsString('name="year" value="2026"', $form);
        $this->assertStringContainsString('name="month" value="08"', $form);
        $this->assertStringContainsString('name="team" value="all"', $form);
    }

    public function test_search_from_second_page_without_page_param_shows_match_without_white_screen(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1);
        $overflow = $students[$perPage];

        $page2 = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'page' => 2,
        ]))->assertOk();
        $this->assertStringNotContainsString('Whoops', (string) $page2->getContent());

        $search = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => $overflow->lastname,
        ]));
        $search->assertOk();
        $html = (string) $search->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringContainsString($overflow->full_name, $html);
        $this->assertSame(1, $this->journalRowUserIds($html)->count());
        $this->assertJournalPagerRendered($html, false);
    }

    public function test_changing_filters_without_page_opens_first_page_and_keeps_search(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $students = $this->seedJournalStudents($perPage + 1);
        $first = $students[0];
        $overflow = $students[$perPage];

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '07',
            'team' => 'all',
            'q' => $first->lastname,
        ]))->assertOk()->getContent();

        $this->assertFilterOptionSelected($html, '07');
        $this->assertFilterOptionSelected($html, 'all');
        $this->assertTrue($this->journalRowUserIds($html)->contains((int) $first->id));
        $this->assertFalse($this->journalRowUserIds($html)->contains((int) $overflow->id));
        $this->assertStringContainsString('value="'.$first->lastname.'"', $this->searchFormHtml($html));
        $this->assertStringNotContainsString('name="page"', $this->searchFormHtml($html));
    }

    public function test_search_form_on_first_open_has_empty_q_and_no_hidden_page(): void
    {
        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $form = $this->searchFormHtml($html);
        $this->assertStringContainsString('method="get"', $form);
        $this->assertStringContainsString('Найти', $form);
        $this->assertStringNotContainsString('name="page"', $form);
        $this->assertStringNotContainsString('name="fullscreen"', $form);
        $this->assertDoesNotMatchRegularExpression('/name="q"[^>]*value="[^"]+"/', $form);

        $yearPos = strpos($html, 'id="filter-year"');
        $monthPos = strpos($html, 'id="filter-month"');
        $teamPos = strpos($html, 'id="filter-team"');
        $searchPos = strpos($html, 'id="table-search"');
        $this->assertNotFalse($yearPos);
        $this->assertNotFalse($monthPos);
        $this->assertNotFalse($teamPos);
        $this->assertNotFalse($searchPos);
        $this->assertLessThan($monthPos, $yearPos);
        $this->assertLessThan($teamPos, $monthPos);
        $this->assertLessThan($searchPos, $teamPos);
    }

    public function test_fullscreen_hidden_field_appears_only_when_requested(): void
    {
        $with = $this->searchFormHtml((string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'fullscreen' => '1',
        ]))->assertOk()->getContent());
        $this->assertStringContainsString('name="fullscreen" value="1"', $with);

        $without = $this->searchFormHtml((string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent());
        $this->assertStringNotContainsString('name="fullscreen"', $without);
    }

    public function test_pager_links_keep_search_query_and_filters(): void
    {
        $perPage = ScheduleController::JOURNAL_STUDENTS_PER_PAGE;
        $this->seedJournalStudents($perPage + 1, 'ЖурналСсылка');

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'q' => 'ЖурналСсылка',
        ]))->assertOk()->getContent();

        $this->assertJournalPagerRendered($html, true);
        $this->assertTrue(
            (bool) preg_match_all('/href="([^"]+)"/', $html, $hrefs)
        );
        $foundPage2 = false;
        foreach ($hrefs[1] as $href) {
            $decoded = urldecode(html_entity_decode($href));
            if (
                str_contains($decoded, 'page=2')
                && str_contains($decoded, 'ЖурналСсылка')
                && str_contains($decoded, 'year=2026')
                && str_contains($decoded, 'month=08')
            ) {
                $foundPage2 = true;
                break;
            }
        }
        $this->assertTrue($foundPage2, 'Ссылка на страницу 2 должна сохранять q, year и month');
        $this->assertStringNotContainsString('name="page"', $this->searchFormHtml($html));
    }

    public function test_foreign_partner_student_is_not_shown_on_paginated_journal(): void
    {
        $this->seedJournalStudents(2, 'ЖурналСвои');
        $foreign = $this->foreignUser;
        $foreign->update([
            'lastname' => 'ЧужойПагин',
            'name' => 'Ученик',
            'is_enabled' => 1,
            'role_id' => $this->studentRoleId(),
        ]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertFalse($this->journalRowUserIds($html)->contains((int) $foreign->id));
        $this->assertStringNotContainsString('ЧужойПагин', $html);
        $this->assertStringContainsString('ЖурналСвои000', $html);
    }

    public function test_fixed_assignments_are_loaded_in_batch_not_per_student(): void
    {
        $students = $this->seedJournalStudents(8, 'ЖурналНплюс1');
        foreach ($students as $student) {
            $this->makeFixedAssignment($student, lessons: 1, durationDays: 7);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $perUserUlp = 0;
        foreach ($log as $item) {
            $sql = strtolower((string) ($item['query'] ?? ''));
            if (! str_contains($sql, 'user_lesson_packages')) {
                continue;
            }
            $equalsUser = (bool) preg_match('/[`"]?user_id[`"]?\s*=\s*\?/', $sql);
            $inUser = (bool) preg_match('/[`"]?user_id[`"]?\s+in\s*\(/', $sql);
            if ($equalsUser && ! $inUser) {
                $perUserUlp++;
            }
        }

        $this->assertSame(
            0,
            $perUserUlp,
            'Абонементы журнала должны грузиться пакетом (whereIn), а не по одному ученику'
        );
    }

    /**
     * @return list<User>
     */
    private function seedJournalStudents(int $count, string $lastnamePrefix = 'ЖурналПагин'): array
    {
        $studentRoleId = $this->studentRoleId();

        return User::factory()
            ->count($count)
            ->sequence(fn ($sequence) => [
                'lastname' => sprintf('%s%03d', $lastnamePrefix, $sequence->index),
                'name' => 'Тест',
                'partner_id' => $this->partner->id,
                'role_id' => $studentRoleId,
                'is_enabled' => 1,
                'team_id' => null,
            ])
            ->create()
            ->all();
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

    private function assertFilterOptionSelected(string $html, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote($value, '/').'"[^>]*\bselected\b/',
            $html,
            "Ожидали selected у option value={$value}"
        );
    }
}
