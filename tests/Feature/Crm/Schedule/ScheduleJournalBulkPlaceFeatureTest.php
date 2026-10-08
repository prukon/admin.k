<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\ScheduleJournalGroupBoardService;
use App\Services\Schedule\ScheduleJournalPageLength;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * Массовая постановка занятий в пустые ячейки нескольких групп и дат.
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

    public function test_bulk_place_puts_different_students_on_two_days_with_one_status(): void
    {
        [$first, $team] = $this->makeStudentWithTeam();
        $second = $this->makeStudent();
        app(TeamUserSyncService::class)->syncTeamsForStudent($second, [(int) $team->id]);
        $this->makeMonthlyFlexibleAssignment($first, (int) $team->id, '2026-10-01', lessons: 4);
        $this->attachPostpay($second->id, (int) $team->id, '2026-10-01');
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'lesson_occurrence_status_id' => $statusId,
            'lessons' => [
                ['user_id' => $second->id, 'occurrence_date' => '2026-10-04'],
                ['user_id' => $first->id, 'occurrence_date' => '2026-10-03'],
            ],
        ], $this->ajaxHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('result.failed', []);
        $response->assertJsonPath('message', 'Занятие поставлено выбранным ученикам.');

        $placed = collect($response->json('result.placed'));
        $this->assertSame(['2026-10-03', '2026-10-04'], $placed->pluck('occurrence_date')->all());
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $first->id)->whereDate('starts_at', '2026-10-03')->count());
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $second->id)->whereDate('starts_at', '2026-10-04')->count());
    }

    public function test_bulk_place_keeps_the_earlier_day_when_prepaid_has_one_lesson_left(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 1);
        $statusId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'lesson_occurrence_status_id' => $statusId,
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-04'],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-03'],
            ],
        ], $this->ajaxHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Занятие поставлено не всем ученикам.');
        $response->assertJsonPath('result.placed.0.occurrence_date', '2026-10-03');
        $response->assertJsonPath('result.failed.0.occurrence_date', '2026-10-04');
        $response->assertJsonPath('result.failed.0.user_id', $student->id);
        $response->assertJsonPath('result.failed.0.message', 'В абонементе не осталось занятий.');
        $this->assertSame(0, (int) $ulp->fresh()->lessons_remaining);
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_id', $student->id)->whereDate('starts_at', '2026-10-03')->count());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->whereDate('starts_at', '2026-10-04')->count());
    }

    public function test_bulk_place_applies_one_trainer_set_to_every_selected_day(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 4);
        $statusId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'lesson_occurrence_status_id' => $statusId,
            'trainer_profile_ids' => [$trainer->id],
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-04'],
                ['user_id' => $student->id, 'occurrence_date' => '2026-10-03'],
            ],
        ], $this->ajaxHeaders());

        $response->assertOk();
        $response->assertJsonPath('result.failed', []);
        $this->assertCount(2, $response->json('result.placed'));
        $this->assertSame(2, (int) $ulp->fresh()->lessons_remaining);

        $events = UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->orderBy('occurrence_date')
            ->get();
        $this->assertCount(2, $events);
        $this->assertSame('2026-10-03', $events[0]->occurrence_date->toDateString());
        $this->assertSame('2026-10-04', $events[1]->occurrence_date->toDateString());
        foreach ($events as $event) {
            $this->assertSame((int) $trainer->id, (int) $event->trainer_profile_id);
            $this->assertTrue($event->trainerProfiles()->whereKey($trainer->id)->exists());
        }
    }

    public function test_bulk_place_lessons_validation_errors_stay_on_fields(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-10-01', lessons: 4);
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'lesson_occurrence_status_id' => $statusId,
            'lessons' => [],
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.lessons.0', 'Выберите занятия.');

        $badDate = $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'lesson_occurrence_status_id' => $statusId,
            'lessons' => [
                ['user_id' => $student->id, 'occurrence_date' => '05.10.2026'],
            ],
        ], $this->ajaxHeaders());
        $badDate->assertStatus(422);
        $this->assertSame(
            'Некорректный формат даты занятия.',
            $badDate->json('errors')['lessons.0.occurrence_date'][0] ?? null
        );

        $tooManyOnOneDay = [];
        for ($i = 1; $i <= 101; $i++) {
            $tooManyOnOneDay[] = ['user_id' => $i, 'occurrence_date' => '2026-10-05'];
        }
        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'lesson_occurrence_status_id' => $statusId,
            'lessons' => $tooManyOnOneDay,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.lessons.0', 'В одной группе на одну дату можно поставить занятие не больше чем 100 ученикам.');

        $tooManyTotal = [];
        for ($i = 1; $i <= 401; $i++) {
            $day = (($i - 1) % 20) + 1;
            $tooManyTotal[] = [
                'user_id' => $i,
                'occurrence_date' => sprintf('2026-10-%02d', $day),
            ];
        }
        $this->postJson(route('schedule.bulk-place'), [
            'team_id' => $team->id,
            'lesson_occurrence_status_id' => $statusId,
            'lessons' => $tooManyTotal,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.lessons.0', 'За один раз можно поставить не больше 400 занятий.');

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_bulk_place_limit_counts_one_hundred_inside_each_group(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $statusId = (int) LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $lessons = [];
        for ($i = 1; $i <= 100; $i++) {
            $lessons[] = [
                'user_id' => 900000 + $i,
                'occurrence_date' => '2026-10-05',
                'team_id' => $teamA->id,
            ];
        }
        $lessons[] = [
            'user_id' => $student->id,
            'occurrence_date' => '2026-10-05',
            'team_id' => $teamB->id,
        ];

        $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => $statusId,
            'lessons' => $lessons,
        ], $this->ajaxHeaders())->assertOk();
    }

    public function test_bulk_place_same_student_same_day_in_two_groups_keeps_each_billing_and_one_trainer(): void
    {
        [$student, $teamA, $trainer] = $this->makeStudentTeamAndTrainer();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $teamA->id, '2026-10-01', lessons: 4);
        $this->attachPostpay((int) $student->id, (int) $teamB->id, '2026-10-01');
        $statusId = (int) LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        $response = $this->postJson(route('schedule.bulk-place'), [
            'lesson_occurrence_status_id' => $statusId,
            'trainer_profile_ids' => [$trainer->id],
            'lessons' => [
                [
                    'user_id' => $student->id,
                    'occurrence_date' => '2026-10-05',
                    'team_id' => $teamB->id,
                ],
                [
                    'user_id' => $student->id,
                    'occurrence_date' => '2026-10-05',
                    'team_id' => $teamA->id,
                ],
            ],
        ], $this->ajaxHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('result.failed', []);
        $this->assertSame(3, (int) $ulp->fresh()->lessons_remaining);

        $placed = collect($response->json('result.placed'))->keyBy('team_id');
        $this->assertSame('prepaid', $placed[(int) $teamA->id]['billing']);
        $this->assertSame('postpay', $placed[(int) $teamB->id]['billing']);
        $this->assertSame((int) $student->id, (int) $placed[(int) $teamA->id]['user_id']);
        $this->assertSame((int) $student->id, (int) $placed[(int) $teamB->id]['user_id']);

        $this->assertSame(1, UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->whereDate('starts_at', '2026-10-05')
            ->where('user_lesson_package_id', $ulp->id)
            ->whereHas('slot', static fn ($query) => $query->where('team_id', $teamA->id))
            ->count());
        $this->assertSame(1, UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->whereDate('starts_at', '2026-10-05')
            ->whereNull('user_lesson_package_id')
            ->whereHas('slot', static fn ($query) => $query->where('team_id', $teamB->id))
            ->count());

        $events = UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->whereDate('occurrence_date', '2026-10-05')
            ->get();
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame((int) $trainer->id, (int) $event->trainer_profile_id);
            $this->assertTrue($event->trainerProfiles()->whereKey($trainer->id)->exists());
        }
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

        $namedBeforeTail = 6;
        $fillers = User::factory()
            ->count(ScheduleJournalPageLength::DEFAULT - $namedBeforeTail)
            ->sequence(fn ($sequence) => [
                'lastname' => sprintf('МммНаполн%03d', $sequence->index),
                'name' => 'Без',
                'partner_id' => $this->partner->id,
                'role_id' => $this->studentRoleId(),
                'is_enabled' => 1,
                'team_id' => null,
            ])
            ->create();
        foreach ($fillers as $filler) {
            app(TeamUserSyncService::class)->syncTeamsForStudent($filler, [(int) $team->id]);
        }

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

        $html = $this->journalIndex( [
            'year' => 2026,
            'month' => '10',
            'team' => $team->id,
        ])->assertOk()->getContent();
        $this->assertStringContainsString('schedule-group-pager-cell', $html);
        $this->assertStringContainsString('colspan="36"', $html);
        $this->assertStringNotContainsString('schedule-group-pager-host', $html);
        $this->assertNotNull($this->journalStudentRowHtml($html, (int) $first->id));
        $this->assertNull($this->journalStudentRowHtml($html, (int) $last->id));
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

        $this->journalIndex( ['year' => 2026, 'month' => '10', 'team' => $team->id])
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
