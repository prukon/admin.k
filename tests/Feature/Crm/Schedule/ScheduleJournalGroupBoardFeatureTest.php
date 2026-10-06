<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Http\Controllers\Admin\ScheduleController;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * Журнал /schedule группами: вложенные ученики, ячейки только этой группы,
 * GET /schedule/group-rows.
 *
 * P1: HTTP 200/403/401/302/404/405/422 и errors[field], разметка (группы раскрыты,
 * пустая группа, «Без группы»), ссылка пейджера на полный журнал.
 * UX-баг «после смены страницы снова видны ученики стр. 1» — контракт JS
 * в BladeInlineJsSyntaxTest::test_schedule_journal_group_page_swap_keeps_new_rows_and_closed_groups.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalGroupBoardFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_guest_is_redirected_from_group_rows(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        Auth::logout();

        $this->get($this->groupRowsUrl($team, 1))
            ->assertStatus(302);
        $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'group_pages' => [(string) $team->id => 2],
        ]))->assertStatus(302);
        $this->assertNotSame('', $student->full_name);
    }

    public function test_guest_json_request_for_group_rows_is_unauthorized(): void
    {
        [, $team] = $this->makeStudentWithTeam();
        Auth::logout();

        $this->getJson($this->groupRowsUrl($team, 1))->assertStatus(401);
        $this->withHeaders($this->ajaxHeaders())
            ->getJson($this->groupRowsUrl($team, 2))
            ->assertStatus(401);
    }

    public function test_manager_without_schedule_view_gets_403_on_group_rows(): void
    {
        [, $team] = $this->makeStudentWithTeam();
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get($this->groupRowsUrl($team, 1))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson($this->groupRowsUrl($team, 1))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']))
            ->assertStatus(403);
    }

    public function test_user_without_partner_is_logged_out_from_group_rows(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $this->get(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => 'none',
        ]))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
        $this->assertGuest();
    }

    public function test_unsupported_methods_on_group_rows_are_not_server_errors(): void
    {
        [, $team] = $this->makeStudentWithTeam();

        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('schedule.group-rows'), [
                'year' => 2026,
                'month' => '08',
                'group_key' => (string) $team->id,
            ]);
            $this->assertSame(
                405,
                $response->status(),
                "{$method} /schedule/group-rows должен быть 405, а не 500/пустой 200"
            );
        }

        $this->postJson(route('schedule.group-rows'), ['group_key' => (string) $team->id])->assertStatus(405);
        $this->patchJson(route('schedule.group-rows'), ['group_key' => (string) $team->id])->assertStatus(405);
        $this->deleteJson(route('schedule.group-rows'))->assertStatus(405);
    }

    public function test_viewer_gets_only_that_groups_students_on_group_rows(): void
    {
        [$inTeam, $team] = $this->makeStudentWithTeam();
        $inTeam->update(['lastname' => 'ВЭтойГруппе', 'name' => 'Ученик']);
        [$other, $otherTeam] = $this->makeStudentWithTeam();
        $other->update(['lastname' => 'ВДругойГруппе', 'name' => 'Ученик']);

        $page = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get($this->groupRowsUrl($team, 1));
        $page->assertOk();
        $html = (string) $page->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringNotContainsString('id="schedule-table"', $html);
        $this->assertStringNotContainsString('schedule-group-row', $html);
        $this->assertStringContainsString($inTeam->full_name, $html);
        $this->assertStringNotContainsString($other->full_name, $html);
        $this->assertStringContainsString('data-group-key="'.$team->id.'"', $html);
        $this->assertStringContainsString('data-team-id="'.$team->id.'"', $html);
        $this->assertStringNotContainsString('data-group-key="'.$otherTeam->id.'"', $html);
        $this->assertStringNotContainsString('schedule-journal-per-page__select', $html);
        $this->assertStringNotContainsString('schedule-group-page-link', $html);
    }

    public function test_second_page_of_a_group_lists_the_remaining_students(): void
    {
        $perPage = ScheduleController::JOURNAL_GROUP_STUDENTS_PER_PAGE;
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаСоСтраницей',
        ]);
        $students = $this->seedStudentsInTeam($team, $perPage + 1, 'СтрГруппы');
        $first = $students[0];
        $overflow = $students[$perPage];

        $fragment = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get($this->groupRowsUrl($team, 2, ['q' => 'СтрГруппы']));
        $fragment->assertOk();
        $html = (string) $fragment->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringContainsString($overflow->full_name, $html);
        $this->assertStringNotContainsString($first->full_name, $html);
        $this->assertStringContainsString('number-line">'.($perPage + 1).'</td', $html);
        $this->assertStringContainsString(
            ($perPage + 1).'–'.($perPage + 1).'</span> <span class="schedule-journal-pagination__of">из '.($perPage + 1),
            $html
        );
        $this->assertJournalPageLinkPointsAtFullJournal($html, (string) $team->id, 1);

        $beyond = $this->get($this->groupRowsUrl($team, 99))->assertOk();
        $beyondHtml = (string) $beyond->getContent();
        $this->assertStringNotContainsString('Whoops', $beyondHtml);
        $this->assertStringNotContainsString($first->full_name, $beyondHtml);
        $this->assertStringNotContainsString($overflow->full_name, $beyondHtml);
    }

    public function test_invalid_group_rows_filters_return_422_with_errors_under_fields(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $this->getJson(route('schedule.group-rows', ['year' => 2026, 'month' => '08']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_key'])
            ->assertJsonPath('errors.group_key.0', 'Выберите группу из списка.');

        $this->getJson(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => 'all',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_key'])
            ->assertJsonPath('errors.group_key.0', 'Выберите группу из списка.');

        $this->getJson(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => (string) $foreignTeam->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_key'])
            ->assertJsonPath('errors.group_key.0', 'Выберите группу из списка.');

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.group-rows', [
                'year' => 2026,
                'month' => '08',
                'group_key' => (string) $team->id,
                'group_page' => 0,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_page'])
            ->assertJsonPath('errors.group_page.0', 'Номер страницы группы должен быть не меньше 1.');

        $this->getJson(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => (string) $team->id,
            'group_page' => 'abc',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_page'])
            ->assertJsonPath('errors.group_page.0', 'Номер страницы группы должен быть числом.');

        $this->assertFieldError(
            $this->getJson(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'group_pages' => ['foo' => 1],
            ])),
            'group_pages.foo',
            'Выберите группу из списка.',
        );

        $this->assertFieldError(
            $this->getJson(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'group_pages' => ['none' => 0],
            ])),
            'group_pages.none',
            'Номер страницы группы должен быть не меньше 1.',
        );

        $this->assertFieldError(
            $this->getJson(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'group_pages' => [(string) $foreignTeam->id => 2],
            ])),
            'group_pages.'.$foreignTeam->id,
            'Выберите группу из списка.',
        );

        $this->assertNotSame('', $student->full_name);
    }

    public function test_invalid_group_key_on_html_get_redirects_with_error_under_field(): void
    {
        $this->from(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->get(route('schedule.group-rows', [
                'year' => 2026,
                'month' => '08',
                'group_key' => 'foo',
            ]))
            ->assertRedirect(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->assertSessionHasErrors(['group_key' => 'Выберите группу из списка.']);

        $this->from(route('schedule.index'))
            ->get(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'group_pages' => ['foo' => 1],
            ]))
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHasErrors(['group_pages.foo' => 'Выберите группу из списка.']);
    }

    public function test_group_outside_the_selected_filter_is_not_found(): void
    {
        [, $team] = $this->makeStudentWithTeam();
        [, $other] = $this->makeStudentWithTeam();

        $this->get(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => (string) $team->id,
            'team_ids' => [(string) $other->id],
        ]))->assertNotFound();

        $this->getJson(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => (string) $team->id,
            'team_ids' => [(string) $other->id],
        ]))->assertNotFound();
    }

    public function test_disabled_group_is_hidden_on_all_groups_and_missing_from_group_rows(): void
    {
        $disabled = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ВыключеннаяГруппаЖурнала',
            'is_enabled' => 0,
        ]);
        $student = $this->makeStudent();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $disabled->id]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('ВыключеннаяГруппаЖурнала', $html);
        $this->assertStringNotContainsString('data-group-key="'.$disabled->id.'"', $html);

        $this->get(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => (string) $disabled->id,
        ]))->assertNotFound();
    }

    public function test_journal_opens_groups_collapsed_with_a_group_column(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $team->update(['title' => 'РаскрытаяГруппаЖурнала']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertSame(
            1,
            preg_match('/<table id="schedule-table"[\s\S]*?<\/table>/', $html, $tableMatch)
        );
        $table = $tableMatch[0];
        $this->assertSame(1, preg_match('/<thead>([\s\S]*?)<\/thead>/', $table, $headMatch));
        $head = $headMatch[1];
        $numberPos = strpos($head, 'col-number');
        $namePos = strpos($head, 'col-name');
        $this->assertNotFalse($numberPos);
        $this->assertNotFalse($namePos);
        $this->assertLessThan($namePos, $numberPos);
        $this->assertStringNotContainsString('col-group', $head);
        $this->assertStringNotContainsString('>Группа<', $head);
        $this->assertStringContainsString('data-group-rows-url="'.route('schedule.group-rows').'"', $table);
        $this->assertStringContainsString('class="schedule-group-row"', $table);
        $this->assertStringNotContainsString('schedule-group-row is-open', $table);
        $this->assertStringNotContainsString('class="schedule-group-user"', $table);
        $this->assertStringContainsString('data-users-loaded="0"', $table);
        $this->assertStringContainsString('schedule-group-day-check', $table);
        $this->assertStringContainsString('schedule-group-head-cell', $table);
        $this->assertStringContainsString('aria-expanded="false"', $table);
        $this->assertStringContainsString('aria-label="Развернуть"', $table);
        $this->assertStringContainsString('fa-chevron-right', $table);
        $this->assertStringNotContainsString('aria-expanded="true"', $table);
        $this->assertStringNotContainsString('fa-chevron-down', $table);
        $this->assertStringNotContainsString('fa-chevron-up', $table);
        $this->assertStringContainsString('schedule-group-title" title="РаскрытаяГруппаЖурнала"', $table);
        $this->assertStringContainsString('schedule-attendance-total-label">Итого', $table);
        $this->assertStringNotContainsString($student->full_name, $table);

        $fragment = (string) $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get($this->groupRowsUrl($team, 1))->assertOk()->getContent();
        $row = $this->groupUserRow($fragment, (int) $student->id, (string) $team->id);
        $this->assertStringNotContainsString('РаскрытаяГруппаЖурнала', $row);
    }

    public function test_student_in_two_groups_has_each_groups_lessons_only_on_that_row(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаАльфаЖурнал',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаБетаЖурнал',
        ]);
        $student = $this->makeStudent();
        $student->update(['lastname' => 'ДвухГрупп', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $utss = $this->createTrialUtss($student, $teamA, '2026-08-04');

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('schedule-group-title" title="ГруппаАльфаЖурнал"', $html);
        $this->assertStringContainsString('schedule-group-title" title="ГруппаБетаЖурнал"', $html);
        $this->assertStringNotContainsString('class="schedule-group-user"', $html);

        $fragmentA = (string) $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get($this->groupRowsUrl($teamA, 1))->assertOk()->getContent();
        $fragmentB = (string) $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get($this->groupRowsUrl($teamB, 1))->assertOk()->getContent();

        $rowA = $this->groupUserRow($fragmentA, (int) $student->id, (string) $teamA->id);
        $rowB = $this->groupUserRow($fragmentB, (int) $student->id, (string) $teamB->id);
        $cellA = $this->dayCellOpeningTag($rowA, '2026-08-04');
        $cellB = $this->dayCellOpeningTag($rowB, '2026-08-04');

        $this->assertStringContainsString('data-context-team-id="'.$teamA->id.'"', $cellA);
        $this->assertStringContainsString('data-occurrence-count="1"', $cellA);
        $this->assertStringContainsString('data-utss-id="'.$utss->id.'"', $cellA);
        $this->assertStringContainsString('data-team-id="'.$teamA->id.'"', $rowA);

        $this->assertStringContainsString('data-context-team-id="'.$teamB->id.'"', $cellB);
        $this->assertStringContainsString('data-occurrence-count="0"', $cellB);
        $this->assertStringNotContainsString('data-utss-id="'.$utss->id.'"', $rowB);
        $this->assertStringContainsString('data-team-id="'.$teamB->id.'"', $rowB);
        $this->assertStringNotContainsString('ГруппаАльфаЖурнал', $rowA);
        $this->assertStringNotContainsString('ГруппаБетаЖурнал', $rowB);
    }

    public function test_student_without_a_group_has_empty_days_and_cannot_place_a_lesson(): void
    {
        $this->grantLessonPackagesView();
        [$inTeam, $team] = $this->makeStudentWithTeam();
        $team->update(['title' => 'ГруппаПередБезГруппы']);
        $lonely = $this->makeStudent();
        $lonely->update(['lastname' => 'БезГруппыЖурнал', 'name' => 'Ученик']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $teamHeader = strpos($html, 'data-group-key="'.$team->id.'"');
        $noneHeader = strpos($html, 'data-group-key="none"');
        $this->assertNotFalse($teamHeader);
        $this->assertNotFalse($noneHeader);
        $this->assertLessThan($noneHeader, $teamHeader);
        $this->assertStringContainsString('schedule-group-title" title="Без группы"', $html);
        $this->assertStringNotContainsString($inTeam->full_name, $html);
        $this->assertStringNotContainsString($lonely->full_name, $html);

        $fragment = (string) $this->get(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => 'none',
        ]))->assertOk()->getContent();
        $this->assertStringContainsString($lonely->full_name, $fragment);
        $this->assertStringNotContainsString($inTeam->full_name, $fragment);
        $row = $this->groupUserRow($fragment, (int) $lonely->id, 'none');
        $this->assertStringNotContainsString('data-team-id=', $row);
        $fragmentCell = $this->dayCellOpeningTag($row, '2026-08-04');
        $this->assertStringContainsString('data-context-team-id=""', $fragmentCell);
        $this->assertStringContainsString('data-occurrence-count="0"', $fragmentCell);
        $this->assertStringContainsString('data-empty-lesson="0"', $fragmentCell);
        $this->assertStringNotContainsString('data-utss-id=', $fragmentCell);
    }

    public function test_single_group_filter_still_nests_students_under_the_group(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $team->update(['title' => 'ОднаГруппаВФильтре']);
        [$other] = $this->makeStudentWithTeam();
        $other->update(['lastname' => 'ЧужаяДляФильтра', 'name' => 'Ученик']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('class="schedule-group-row"', $html);
        $this->assertStringNotContainsString('schedule-group-row is-open', $html);
        $this->assertStringNotContainsString('class="schedule-group-user"', $html);
        $this->assertStringContainsString('data-users-loaded="0"', $html);
        $this->assertStringContainsString('schedule-group-count">1', $html);
        $this->assertStringContainsString('schedule-group-title" title="ОднаГруппаВФильтре"', $html);
        $this->assertStringNotContainsString($student->full_name, $html);
        $this->assertStringNotContainsString('ЧужаяДляФильтра', $html);
        $this->assertStringNotContainsString('data-group-key="none"', $html);

        $fragment = (string) $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get($this->groupRowsUrl($team, 1))->assertOk()->getContent();
        $this->assertStringContainsString('data-group-key="'.$team->id.'"', $this->groupUserRow($fragment, (int) $student->id, (string) $team->id));
    }

    public function test_empty_group_stays_hidden_until_it_is_explicitly_selected(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ПустаяГруппаЖурнала',
            'is_enabled' => 1,
        ]);
        $this->makeStudentWithTeam();

        $all = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertStringNotContainsString('schedule-group-title" title="ПустаяГруппаЖурнала"', $all);
        $this->assertStringContainsString('ПустаяГруппаЖурнала', $all);

        $empty = Team::query()->where('title', 'ПустаяГруппаЖурнала')->firstOrFail();
        $selected = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [(string) $empty->id],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('schedule-group-title" title="ПустаяГруппаЖурнала"', $selected);
        $this->assertStringContainsString('schedule-group-count">0', $selected);
        $this->assertStringContainsString('class="schedule-group-row"', $selected);
        $this->assertStringNotContainsString('schedule-group-row is-open', $selected);
        $this->assertStringNotContainsString('class="schedule-group-user"', $selected);
        $this->assertStringNotContainsString('class="schedule-journal-pagination', $selected);
    }

    public function test_opening_one_groups_second_page_keeps_the_other_group_on_page_one(): void
    {
        $perPage = ScheduleController::JOURNAL_GROUP_STUDENTS_PER_PAGE;
        $crowded = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'МногоУчениковЖурнал',
        ]);
        $small = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'МалоУчениковЖурнал',
        ]);
        $students = $this->seedStudentsInTeam($crowded, $perPage + 1, 'МногоСтр');
        $first = $students[0];
        $overflow = $students[$perPage];
        $alone = $this->makeStudent();
        $alone->update(['lastname' => 'ОдинВМалой', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($alone, [(int) $small->id]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
            'group_pages' => [(string) $crowded->id => 2],
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringContainsString($overflow->full_name, $html);
        $this->assertStringNotContainsString($first->full_name, $html);
        $this->assertStringNotContainsString($alone->full_name, $html);
        $this->assertStringContainsString('data-users-loaded="1"', $html);
        $this->assertStringContainsString('data-users-loaded="0"', $html);
        $this->assertStringContainsString('schedule-group-title" title="МалоУчениковЖурнал"', $html);
        $this->assertStringContainsString('schedule-group-count">1', $html);
        $this->assertJournalPageLinkPointsAtFullJournal($html, (string) $crowded->id, 1);
        $this->assertStringContainsString('data-group-rows-url="'.route('schedule.group-rows').'"', $html);
    }

    private function assertFieldError(\Illuminate\Testing\TestResponse $response, string $field, string $message): void
    {
        $response->assertStatus(422)->assertJsonValidationErrors([$field]);
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertSame($message, $errors[$field][0] ?? null);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function groupRowsUrl(Team $team, int $page, array $extra = []): string
    {
        return route('schedule.group-rows', array_merge([
            'year' => 2026,
            'month' => '08',
            'group_key' => (string) $team->id,
            'group_page' => $page,
        ], $extra));
    }

    /**
     * @return list<User>
     */
    private function seedStudentsInTeam(Team $team, int $count, string $lastnamePrefix): array
    {
        $students = User::factory()
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

        foreach ($students as $student) {
            app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        }

        return $students;
    }

    /**
     * @return list<string>
     */
    private function groupUserRows(string $html, int $userId): array
    {
        preg_match_all(
            '/<tr class="schedule-group-user[^"]*"[^>]*data-user-id="'.$userId.'"[^>]*>[\s\S]*?<\/tr>/',
            $html,
            $matches
        );

        return $matches[0] ?? [];
    }

    private function groupUserRow(string $html, int $userId, string $groupKey): string
    {
        foreach ($this->groupUserRows($html, $userId) as $row) {
            if (str_contains($row, 'data-group-key="'.$groupKey.'"')) {
                return $row;
            }
        }

        $this->fail("Нет строки ученика {$userId} в группе {$groupKey}");
    }

    private function dayCellOpeningTag(string $rowHtml, string $date): string
    {
        $this->assertSame(
            1,
            preg_match(
                '/<td[^>]*data-date="'.preg_quote($date, '/').'"[^>]*>/',
                $rowHtml,
                $match
            ),
            "Нет ячейки {$date}"
        );

        return $match[0];
    }

    private function assertJournalPageLinkPointsAtFullJournal(string $html, string $groupKey, int $page): void
    {
        $this->assertTrue(
            (bool) preg_match_all('/<a[^>]*class="schedule-group-page-link"[^>]*>/', $html, $links),
            'Нет ссылки страницы группы'
        );

        $found = false;
        foreach ($links[0] as $tag) {
            if (! str_contains($tag, 'data-group-key="'.$groupKey.'"') || ! str_contains($tag, 'data-page="'.$page.'"')) {
                continue;
            }
            $this->assertSame(1, preg_match('/href="([^"]+)"/', $tag, $hrefMatch));
            $href = urldecode(html_entity_decode($hrefMatch[1]));
            $this->assertStringNotContainsString(
                '/schedule/group-rows',
                $href,
                'Ссылка пейджера должна открывать полный журнал, а не фрагмент строк'
            );
            $this->assertStringContainsString('group_pages['.$groupKey.']='.$page, $href);
            $this->assertMatchesRegularExpression('#/schedule(\?|$)#', $href);
            $found = true;
        }

        $this->assertTrue($found, "Нет ссылки на страницу {$page} группы {$groupKey}");
    }
}
