<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\ScheduleJournalGroupBoardService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * Массовая постановка занятия в пустые ячейки одной группы и одной даты.
 */
final class ScheduleJournalBulkPlaceFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_bulk_place_puts_prepaid_and_postpay_on_the_same_day(): void
    {
        [$prepaidStudent, $team] = $this->makeStudentWithTeam();
        $postpayStudent = $this->makeStudent();
        app(TeamUserSyncService::class)->syncTeamsForStudent($postpayStudent, [(int) $team->id]);

        $ulp = $this->makeMonthlyFlexibleAssignment($prepaidStudent, (int) $team->id, '2026-10-01', lessons: 4);
        $this->attachPostpay($postpayStudent->id, (int) $team->id, '2026-10-01');
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$prepaidStudent->id, $postpayStudent->id],
            'lesson_occurrence_status_id' => $statusId,
        ], $this->ajaxHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('result.failed', []);

        $placed = collect($response->json('result.placed'))->keyBy('user_id');
        $this->assertSame('prepaid', $placed[(int) $prepaidStudent->id]['billing']);
        $this->assertSame('postpay', $placed[(int) $postpayStudent->id]['billing']);
        $this->assertSame(4, (int) $ulp->fresh()->lessons_remaining);

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $prepaidStudent->id)->whereDate('starts_at', '2026-10-05')->count());
        $this->assertSame(1, UserTeamScheduleSlot::query()
            ->where('user_id', $postpayStudent->id)
            ->whereDate('starts_at', '2026-10-05')
            ->whereNull('user_lesson_package_id')
            ->count());
    }

    public function test_bulk_place_skips_empty_prepaid_and_newcomer_and_saves_postpay(): void
    {
        [$emptyPrepaid, $team] = $this->makeStudentWithTeam();
        $postpayStudent = $this->makeStudent();
        $newcomer = $this->makeStudent();
        app(TeamUserSyncService::class)->syncTeamsForStudent($postpayStudent, [(int) $team->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($newcomer, [(int) $team->id]);

        $ulp = $this->makeMonthlyFlexibleAssignment($emptyPrepaid, (int) $team->id, '2026-10-01', lessons: 2);
        $ulp->update(['lessons_remaining' => 0]);
        $this->attachPostpay($postpayStudent->id, (int) $team->id, '2026-10-01');
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$emptyPrepaid->id, $postpayStudent->id, $newcomer->id],
            'lesson_occurrence_status_id' => $statusId,
        ], $this->ajaxHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertSame('Занятие поставлено не всем ученикам.', $response->json('message'));

        $placed = collect($response->json('result.placed'))->keyBy('user_id');
        $this->assertCount(1, $placed);
        $this->assertSame('postpay', $placed[(int) $postpayStudent->id]['billing']);

        $failed = collect($response->json('result.failed'))->keyBy('user_id');
        $this->assertSame('В абонементе не осталось занятий.', $failed[(int) $emptyPrepaid->id]['message']);
        $this->assertSame('На этот месяц не установлен абонемент.', $failed[(int) $newcomer->id]['message']);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $emptyPrepaid->id)->count());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $newcomer->id)->count());
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $postpayStudent->id)->count());
    }

    public function test_bulk_place_does_not_overwrite_an_existing_lesson(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 3);
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $payload = [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$student->id],
            'lesson_occurrence_status_id' => $statusId,
        ];

        $this->postJson(route('schedule.bulk-place'), $payload, $this->ajaxHeaders())->assertOk();

        $second = $this->postJson(route('schedule.bulk-place'), $payload, $this->ajaxHeaders());
        $second->assertOk();
        $second->assertJsonPath('success', false);
        $second->assertJsonPath('result.failed.0.message', 'На эту дату занятие уже стоит.');
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $student->id)->whereDate('starts_at', '2026-10-05')->count());
    }

    public function test_bulk_place_validation_error_is_under_status_field(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$student->id],
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.lesson_occurrence_status_id.0', 'Выберите статус.');

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_bulk_place_non_ajax_redirects_and_saves(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->post(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-06',
            'user_ids' => [$student->id],
            'lesson_occurrence_status_id' => $statusId,
        ])->assertRedirect(route('schedule.index'));

        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $student->id)->whereDate('starts_at', '2026-10-06')->count());
    }

    public function test_guest_bulk_place_is_denied(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);
        Auth::logout();

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$student->id],
            'lesson_occurrence_status_id' => (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ])->assertUnauthorized();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_group_bulk_candidates_include_later_pages_and_skip_ineligible(): void
    {
        [$first, $team] = $this->makeStudentWithTeam();
        $first->update(['lastname' => 'АааПервый', 'name' => 'Ученик']);
        $package = $this->makeMonthlyFlexibleAssignment($first, (int) $team->id, '2026-10-01', lessons: 2, feeAmountCents: 500000);

        $last = null;
        for ($i = 0; $i < 20; $i++) {
            $student = $this->makeStudent();
            $student->update(['lastname' => sprintf('Яяя%02d', $i), 'name' => 'Хвост']);
            app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
            $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 2, feeAmountCents: 250000);
            $last = $student;
        }

        $occupied = $this->makeStudent();
        $occupied->update(['lastname' => 'Занят', 'name' => 'День']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($occupied, [(int) $team->id]);
        $this->makeMonthlyFlexibleAssignment($occupied, (int) $team->id, '2026-10-01', lessons: 2);
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'occurrence_date' => '2026-10-05',
            'user_ids' => [$occupied->id],
            'lesson_occurrence_status_id' => $statusId,
        ], $this->ajaxHeaders())->assertOk();

        $emptyPrepaid = $this->makeStudent();
        $emptyPrepaid->update(['lastname' => 'Пустой', 'name' => 'Абонемент']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($emptyPrepaid, [(int) $team->id]);
        $empty = $this->makeMonthlyFlexibleAssignment($emptyPrepaid, (int) $team->id, '2026-10-01', lessons: 1);
        $empty->update(['lessons_remaining' => 0]);

        $newcomer = $this->makeStudent();
        $newcomer->update(['lastname' => 'Новичок', 'name' => 'Без']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($newcomer, [(int) $team->id]);

        $postpay = $this->makeStudent();
        $postpay->update(['lastname' => 'Постоплата', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($postpay, [(int) $team->id]);
        $this->attachPostpay((int) $postpay->id, (int) $team->id, '2026-10-01');

        $hidden = $this->makeStudent();
        $hidden->update(['lastname' => 'СкрытыйПоиск', 'name' => 'Нет']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($hidden, [(int) $team->id]);
        $this->makeMonthlyFlexibleAssignment($hidden, (int) $team->id, '2026-10-01', lessons: 2);

        $response = $this->getJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
            'occurrence_date' => '2026-10-05',
        ]));

        $response->assertOk();
        $ids = collect($response->json('users'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame(23, $response->json('total'));
        $this->assertContains((int) $first->id, $ids);
        $this->assertContains((int) $last->id, $ids);
        $this->assertContains((int) $postpay->id, $ids);
        $this->assertContains((int) $hidden->id, $ids);
        $this->assertNotContains((int) $occupied->id, $ids);
        $this->assertNotContains((int) $emptyPrepaid->id, $ids);
        $this->assertNotContains((int) $newcomer->id, $ids);

        $search = $this->getJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
            'occurrence_date' => '2026-10-05',
            'q' => 'СкрытыйПоиск',
        ]));
        $search->assertOk();
        $search->assertJsonPath('total', 1);
        $search->assertJsonPath('users.0.id', (int) $hidden->id);

        $prepaidRow = collect($response->json('users'))->firstWhere('id', (int) $first->id);
        $this->assertSame('prepaid', $prepaidRow['billing']);
        $this->assertSame($package->lessonPackage->name, $prepaidRow['abonement_name']);
        $this->assertSame('5 000 руб', $prepaidRow['price_label']);

        $postpayRow = collect($response->json('users'))->firstWhere('id', (int) $postpay->id);
        $this->assertSame('postpay', $postpayRow['billing']);
        $this->assertSame('Постоплата', $postpayRow['abonement_name']);
        $this->assertSame('500 ₽/занятие', $postpayRow['price_label']);

        $html = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '10',
            'team' => $team->id,
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('schedule-group-pager-cell', $html);
        $this->assertStringContainsString('colspan="36"', $html);
        $this->assertStringNotContainsString('schedule-group-pager-host', $html);
    }

    public function test_group_bulk_candidates_cap_at_one_hundred(): void
    {
        [, $team] = $this->makeStudentWithTeam();
        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(2, 60)->create([
            'name' => 'Лимит массовой',
            'is_active' => true,
        ]);
        $monthStart = '2026-10-01';
        $monthEnd = '2026-10-31';
        $limit = ScheduleJournalGroupBoardService::BULK_CANDIDATE_LIMIT;

        for ($i = 1; $i <= $limit + 1; $i++) {
            $student = User::factory()->create([
                'partner_id' => $this->partner->id,
                'role_id' => $this->studentRoleId(),
                'lastname' => sprintf('Лимит%03d', $i),
                'name' => 'Ученик',
                'is_enabled' => 1,
            ]);
            app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
            UserLessonPackage::query()->create([
                'user_id' => $student->id,
                'lesson_package_id' => $package->id,
                'team_id' => $team->id,
                'billing_month' => $monthStart,
                'starts_at' => $monthStart,
                'ends_at' => $monthEnd,
                'lessons_total' => 2,
                'lessons_remaining' => 2,
                'fee_amount_cents' => 100000,
                'is_paid' => false,
                'created_by' => $this->user->id,
            ]);
        }

        $response = $this->getJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
            'occurrence_date' => '2026-10-05',
        ]));

        $response->assertOk();
        $response->assertJsonPath('total', $limit + 1);
        $response->assertJsonPath('limit', $limit);
        $response->assertJsonCount($limit, 'users');
        $this->assertSame('Лимит001 Ученик', $response->json('users.0.name'));
        $this->assertSame('Лимит100 Ученик', $response->json('users.'.($limit - 1).'.name'));
    }

    public function test_group_bulk_candidates_validation_and_access(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);

        $this->getJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
        ]))->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'Укажите дату занятия.');

        $this->getJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
            'occurrence_date' => '2026-11-01',
        ]))->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'Дата должна быть в выбранном месяце.');

        $this->postJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
            'occurrence_date' => '2026-10-05',
        ]))->assertStatus(405);

        Auth::logout();
        $this->getJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
            'occurrence_date' => '2026-10-05',
        ]))->assertUnauthorized();

        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ])->getJson(route('schedule.group-bulk-candidates', [
            'year' => 2026,
            'month' => '10',
            'group_key' => $team->id,
            'occurrence_date' => '2026-10-05',
        ]))->assertStatus(403);
    }

    public function test_journal_marks_selectable_empty_cells(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 2);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '10', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('id="bulkPlaceModal"', false)
            ->assertSee('id="schedule-bulk-add"', false)
            ->assertSee('Добавить занятие', false)
            ->assertSee('data-bulk-block="eligible"', false)
            ->assertSee('data-bulk-billing="prepaid"', false)
            ->assertSee('schedule-group-day', false);
    }

    private function attachPostpay(int $userId, int $teamId, string $month): void
    {
        $package = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->postpay()
            ->create([
                'name' => 'Постоплата журнал',
                'price_cents' => 50000,
            ]);

        UserPrice::query()->create([
            'user_id' => $userId,
            'team_id' => $teamId,
            'new_month' => $month,
            'lesson_package_id' => $package->id,
            'price_cents' => 50000,
            'discount_percent' => 0,
            'is_paid' => 0,
        ]);
    }
}
