<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Http\Controllers\Admin\ScheduleController;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\Schedule\ScheduleJournalPageLength;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * «Показывать по» журнала: user_table_settings.page_length, table_key schedule_journal.
 *
 * @see \Tests\Feature\Crm\Reports\ReportsAndPayoutsPageLengthFeatureTest
 */
final class ScheduleJournalPageLengthFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_guest_is_redirected_and_json_is_unauthorized(): void
    {
        Auth::logout();

        $this->post(route('schedule.journal-page-length'), ['page_length' => 50])
            ->assertStatus(302);
        $this->postJson(route('schedule.journal-page-length'), ['page_length' => 50])
            ->assertStatus(401);
    }

    public function test_manager_without_schedule_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.journal-page-length'), ['page_length' => 50])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.journal-page-length'), ['page_length' => 50])
            ->assertStatus(403);

        $this->assertDatabaseMissing('user_table_settings', [
            'user_id' => $actor->id,
            'table_key' => ScheduleJournalPageLength::TABLE_KEY,
        ]);
    }

    public function test_unsupported_methods_are_not_server_errors(): void
    {
        $get = $this->get(route('schedule.journal-page-length'));
        $this->assertSame(404, $get->status());
        $this->assertStringNotContainsString('Whoops', (string) $get->getContent());
        $this->getJson(route('schedule.journal-page-length'))->assertStatus(404);

        foreach (['patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('schedule.journal-page-length'), [
                'page_length' => 50,
            ]);
            $this->assertSame(405, $response->status(), "{$method} journal-page-length");
        }

        $this->patchJson(route('schedule.journal-page-length'), ['page_length' => 50])->assertStatus(405);
        $this->deleteJson(route('schedule.journal-page-length'))->assertStatus(405);
    }

    public function test_ajax_rejects_unknown_length_under_page_length(): void
    {
        $this->postJson(route('schedule.journal-page-length'), ['page_length' => 10])
            ->assertStatus(422)
            ->assertJsonPath('errors.page_length.0', 'Можно показать 20, 50 или 100 учеников.');

        $this->postJson(route('schedule.journal-page-length'), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.page_length.0', 'Укажите, сколько учеников показывать.');

        $this->postJson(route('schedule.journal-page-length'), ['page_length' => 'много'])
            ->assertStatus(422)
            ->assertJsonPath('errors.page_length.0', 'Количество учеников должно быть целым числом.');

        $this->assertDatabaseMissing('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => ScheduleJournalPageLength::TABLE_KEY,
        ]);
    }

    public function test_non_ajax_invalid_length_redirects_with_session_error(): void
    {
        $this->from(route('schedule.index'))
            ->post(route('schedule.journal-page-length'), ['page_length' => 10])
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHasErrors([
                'page_length' => 'Можно показать 20, 50 или 100 учеников.',
            ]);
    }

    public function test_save_writes_page_length_and_keeps_columns_and_filters(): void
    {
        UserTableSetting::query()->create([
            'user_id' => $this->user->id,
            'table_key' => ScheduleJournalPageLength::TABLE_KEY,
            'columns' => ['name' => true],
            'filters' => ['status' => 'active'],
            'page_length' => 20,
        ]);
        $other = $this->createUserWithoutPermission('schedule.view', $this->partner);
        UserTableSetting::query()->create([
            'user_id' => $other->id,
            'table_key' => ScheduleJournalPageLength::TABLE_KEY,
            'page_length' => 20,
        ]);

        $this->postJson(route('schedule.journal-page-length'), ['page_length' => 50])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('page_length', 50);

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', ScheduleJournalPageLength::TABLE_KEY)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(50, (int) $row->page_length);
        $this->assertSame(['name' => true], $row->columns);
        $this->assertSame(['status' => 'active'], $row->filters);

        $this->assertSame(20, (int) UserTableSetting::query()
            ->where('user_id', $other->id)
            ->where('table_key', ScheduleJournalPageLength::TABLE_KEY)
            ->value('page_length'));
    }

    public function test_default_page_is_fifty_and_saved_length_applies_to_every_group_request(): void
    {
        $perPage = ScheduleController::JOURNAL_GROUP_STUDENTS_PER_PAGE;
        $this->assertSame(50, $perPage);
        $this->seedStudents($perPage + 1, 'РазмерЖурнал');

        $defaultRows = $this->groupRows('none', 1);
        $this->assertSame($perPage, $this->rowCount($defaultRows));
        $this->assertMatchesRegularExpression('/<option value="50"[^>]*\bselected\b/', $defaultRows);
        $this->assertStringContainsString('schedule-group-page-link', $defaultRows);
        $this->assertStringContainsString('data-group-per-page="50"', (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent());

        $this->postJson(route('schedule.journal-page-length'), ['page_length' => 100])->assertOk();

        $wideRows = $this->groupRows('none', 1);
        $this->assertSame($perPage + 1, $this->rowCount($wideRows));
        $this->assertMatchesRegularExpression('/<option value="100"[^>]*\bselected\b/', $wideRows);
        $this->assertStringNotContainsString('schedule-group-page-link', $wideRows);
        $this->assertStringContainsString(
            '1–'.($perPage + 1).'</span> <span class="schedule-journal-pagination__of">из '.($perPage + 1),
            $wideRows
        );

        $page = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('data-group-per-page="100"', $page);
        $this->assertStringContainsString('data-page-length-url="', $page);
    }

    public function test_non_ajax_success_returns_json_and_saves(): void
    {
        $this->post(route('schedule.journal-page-length'), ['page_length' => '20'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('page_length', 20);

        $this->assertSame(20, (int) UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', ScheduleJournalPageLength::TABLE_KEY)
            ->value('page_length'));
    }

    public function test_saved_twenty_applies_to_every_group_and_is_not_replaced_by_the_default(): void
    {
        UserTableSetting::query()->create([
            'user_id' => $this->user->id,
            'table_key' => ScheduleJournalPageLength::TABLE_KEY,
            'page_length' => 20,
        ]);
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'ГруппаА50']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'ГруппаБ50']);
        $this->seedStudentsInTeam($teamA, 25, 'ГруппаАстр');
        $this->seedStudentsInTeam($teamB, 25, 'ГруппаБстр');

        $rowsA = $this->groupRows((string) $teamA->id, 1);
        $rowsB = $this->groupRows((string) $teamB->id, 2);
        $this->assertSame(20, $this->rowCount($rowsA));
        $this->assertSame(5, $this->rowCount($rowsB));
        $this->assertMatchesRegularExpression('/<option value="20"[^>]*\bselected\b/', $rowsA);
        $this->assertMatchesRegularExpression('/<option value="20"[^>]*\bselected\b/', $rowsB);
        $this->assertStringContainsString('data-group-per-page="20"', (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
        ]))->assertOk()->getContent());
    }

    public function test_stored_length_outside_the_set_falls_back_to_fifty(): void
    {
        UserTableSetting::query()->create([
            'user_id' => $this->user->id,
            'table_key' => ScheduleJournalPageLength::TABLE_KEY,
            'page_length' => 10,
        ]);
        $this->seedStudents(51, 'ЧужойРазмер');

        $rows = $this->groupRows('none', 1);
        $this->assertSame(50, $this->rowCount($rows));
        $this->assertMatchesRegularExpression('/<option value="50"[^>]*\bselected\b/', $rows);
        $this->assertStringContainsString('schedule-group-page-link', $rows);
    }

    /**
     * @return list<User>
     */
    private function seedStudentsInTeam(Team $team, int $count, string $lastnamePrefix): array
    {
        $students = $this->seedStudents($count, $lastnamePrefix);
        foreach ($students as $student) {
            app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        }

        return $students;
    }

    /**
     * @return list<User>
     */
    private function seedStudents(int $count, string $lastnamePrefix): array
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

    private function groupRows(string $groupKey, int $page): string
    {
        return (string) $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get(route('schedule.group-rows', [
            'year' => 2026,
            'month' => '08',
            'group_key' => $groupKey,
            'group_page' => $page,
        ]))->assertOk()->getContent();
    }

    private function rowCount(string $html): int
    {
        preg_match_all('/<tr[^>]*\bdata-user-id="(\d+)"/', $html, $matches);

        return count(array_unique($matches[1] ?? []));
    }
}
