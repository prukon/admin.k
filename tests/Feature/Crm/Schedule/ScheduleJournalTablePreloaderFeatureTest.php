<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

/**
 * Локальный прелоадер вкладки «Журнал» /schedule: первый кадр HTML, фильтры снаружи stage,
 * CSS в head, hotfix JS, негатив на других вкладках и на /admin/users.
 *
 * UX-баги, которые ловим:
 * — вечный спиннер (is-ready не ставится до DataTable / общий bind на Vite-модуле);
 * — размазанный AdminLTE (общий stage + visibility/contain на всю страницу);
 * — общий <x-ui.table-preloader> на журнале.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalTablePreloaderFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_journal_first_paint_shows_local_preloader_and_keeps_filters_outside_stage(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
            'team' => 'all',
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('Whoops', $html);
        $page->assertSee($student->full_name, false);

        $this->assertJournalFirstPaintPreloader($html);
    }

    public function test_each_journal_reload_trigger_keeps_first_paint_preloader_without_is_ready(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();

        $queries = [
            'first open' => ['year' => 2026, 'month' => '05', 'team' => 'all'],
            'year and month filters' => ['year' => 2026, 'month' => '08', 'team' => 'all'],
            'team filter' => ['year' => 2026, 'month' => '05', 'team' => $team->id],
            'none team filter' => ['year' => 2026, 'month' => '05', 'team' => 'none'],
            'search submit' => ['year' => 2026, 'month' => '05', 'team' => 'all', 'q' => $student->lastname],
            'fullscreen query' => ['year' => 2026, 'month' => '05', 'team' => 'all', 'fullscreen' => '1'],
            'page query' => ['year' => 2026, 'month' => '05', 'team' => 'all', 'page' => 1],
        ];

        foreach ($queries as $label => $query) {
            $page = $this->get(route('schedule.index', $query));
            $this->assertSame(200, $page->status(), $label);
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html), $label);
            $this->assertJournalFirstPaintPreloader($html, $label);
        }
    }

    public function test_ajax_get_journal_still_returns_html_preloader_not_empty_json(): void
    {
        $this->makeStudentTeamAndTrainer();

        $page = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', [
                'year' => 2026,
                'month' => '05',
                'team' => 'all',
            ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('{"message"', $html);
        $this->assertJournalFirstPaintPreloader($html);
    }

    public function test_trainer_workload_tab_does_not_render_journal_preloader(): void
    {
        $this->makeStudentTeamAndTrainer();

        $page = $this->get(route('schedule.trainer-workload'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('trainer-workload-app', false);
        $this->assertStringNotContainsString('id="schedule-journal-stage"', $html);
        $this->assertStringNotContainsString('schedule-journal-preloader', $html);
        $this->assertStringNotContainsString('#schedule-journal-stage:not(.is-ready)', $html);
        $this->assertStringNotContainsString('js/schedule-journal.js', $html);
        $this->assertStringNotContainsString('kids-table-preloader', $html);
    }

    public function test_users_list_does_not_get_journal_or_shared_table_preloader(): void
    {
        $this->grantUsersView();

        $page = $this->get(route('admin.user1'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="users-table"', $html);
        $this->assertStringContainsString('class="table-responsive"', $html);
        $this->assertStringContainsString('payments-report-toolbar', $html);
        $this->assertStringNotContainsString('id="schedule-journal-stage"', $html);
        $this->assertStringNotContainsString('schedule-journal-preloader', $html);
        $this->assertStringNotContainsString('kids-table-preloader', $html);
        $this->assertStringNotContainsString('users-table-stage', $html);
        $this->assertStringNotContainsString('contain: inline-size', $html);
        $this->assertStringNotContainsString('#users-table-stage:not(.is-ready)', $html);

        $toolbarPos = strpos($html, 'payments-report-toolbar');
        $tablePos = strpos($html, 'id="users-table"');
        $this->assertNotFalse($toolbarPos);
        $this->assertNotFalse($tablePos);
        $this->assertLessThan($tablePos, $toolbarPos);
    }

    private function grantUsersView(): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertJournalFirstPaintPreloader(string $html, string $label = ''): void
    {
        $suffix = $label === '' ? '' : ' ['.$label.']';

        $this->assertStringContainsString('id="schedule-journal-stage"', $html, $suffix);
        $this->assertStringContainsString('aria-busy="true"', $html, $suffix);
        $this->assertStringContainsString('class="schedule-journal-preloader"', $html, $suffix);
        $this->assertStringContainsString('spinner-border text-secondary', $html, $suffix);
        $this->assertStringContainsString('id="schedule-table"', $html, $suffix);
        $this->assertStringContainsString('js/schedule-journal.js', $html, $suffix);
        $this->assertStringNotContainsString('kids-table-preloader', $html, $suffix);
        $this->assertStringNotContainsString('x-ui.table-preloader', $html, $suffix);
        $this->assertStringNotContainsString('KidsCrmTablePreloader', $html, $suffix);
        $this->assertStringNotContainsString('contain: inline-size', $html, $suffix);
        $this->assertDoesNotMatchRegularExpression(
            '/id="schedule-journal-stage"[^>]*\bis-ready\b/',
            $html,
            'первый кадр не должен содержать is-ready, иначе оверлей не виден'.$suffix
        );

        $filterYear = strpos($html, 'id="filter-year"');
        $filterMonth = strpos($html, 'id="filter-month"');
        $filterTeam = strpos($html, 'id="filter-team"');
        $stage = strpos($html, 'id="schedule-journal-stage"');
        $preloader = strpos($html, 'class="schedule-journal-preloader"');
        $table = strpos($html, 'id="schedule-table"');
        $this->assertNotFalse($filterYear, $suffix);
        $this->assertNotFalse($filterMonth, $suffix);
        $this->assertNotFalse($filterTeam, $suffix);
        $this->assertNotFalse($stage, $suffix);
        $this->assertNotFalse($preloader, $suffix);
        $this->assertNotFalse($table, $suffix);
        $this->assertLessThan($stage, $filterYear, 'фильтр года должен быть снаружи stage'.$suffix);
        $this->assertLessThan($stage, $filterMonth, 'фильтр месяца должен быть снаружи stage'.$suffix);
        $this->assertLessThan($stage, $filterTeam, 'фильтр группы должен быть снаружи stage'.$suffix);
        $this->assertGreaterThan($stage, $preloader, $suffix);
        $this->assertGreaterThan($preloader, $table, $suffix);

        $css = strpos($html, '#schedule-journal-stage:not(.is-ready)');
        $wrapper = strpos($html, 'class="wrapper"');
        $this->assertNotFalse($css, 'критический CSS прелоадера должен быть в HTML'.$suffix);
        $this->assertNotFalse($wrapper, $suffix);
        $this->assertLessThan($wrapper, $css, 'CSS прелоадера должен быть в head, иначе вспышка без оверлея'.$suffix);
        $this->assertStringContainsString('visibility: hidden', $html, $suffix);
        $this->assertStringContainsString('display: none !important', $html, $suffix);
        $this->assertStringContainsString('height: 12rem', $html, $suffix);
        $this->assertStringContainsString('background: #f4f6f9', $html, $suffix);
        $this->assertStringContainsString('<noscript>', $html, $suffix);
        $this->assertStringContainsString('class="wrapper"', $html, $suffix);
        $this->assertStringContainsString('content-wrapper', $html, $suffix);
    }
}
