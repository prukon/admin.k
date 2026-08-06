<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\TeamScheduleSlot;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\Auth;

/**
 * Журнал без «Расписания школы»: школьный слот с будущим date_start не блокирует раскладку —
 * создаётся служебный открытый слот, школьный не меняется.
 */
final class ScheduleJournalSlotCoverEnsureFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_place_creates_open_service_slot_when_school_slot_starts_in_future(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);

        $schoolSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2026-06-05',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);
        // Понедельник в октябре 2025 — до date_start школьного слота.
        $startDate = '2025-10-06';

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => $startDate,
            'weekdays' => [1],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.linked_count', 2);

        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());

        $usedSlotIds = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->pluck('team_schedule_slot_id')
            ->unique()
            ->values()
            ->all();
        $this->assertCount(1, $usedSlotIds);
        $this->assertNotSame((int) $schoolSlot->id, (int) $usedSlotIds[0]);

        $serviceSlot = TeamScheduleSlot::query()->findOrFail($usedSlotIds[0]);
        $this->assertSame(1, (int) $serviceSlot->weekday);
        $this->assertSame('09:00:00', substr((string) $serviceSlot->time_start, 0, 8));
        $this->assertSame('2000-01-01', $serviceSlot->date_start?->format('Y-m-d'));
        $this->assertSame('9999-12-31', $serviceSlot->date_end?->format('Y-m-d'));

        $schoolSlot->refresh();
        $this->assertSame('2026-06-05', $schoolSlot->date_start?->format('Y-m-d'));
        $this->assertSame('10:00:00', substr((string) $schoolSlot->time_start, 0, 8));
    }

    public function test_second_placement_reuses_same_open_service_slot(): void
    {
        [$studentA, $team] = $this->makeStudentWithTeam();
        [$studentB] = $this->makeStudentWithTeam();
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($studentB, [(int) $team->id]);
        $this->attachWeekdays($team, [1]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2026-06-05',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        $ulpA = $this->makeFixedAssignment($studentA, lessons: 1, durationDays: 7);
        $ulpB = $this->makeFixedAssignment($studentB, lessons: 1, durationDays: 7);
        $startDate = '2025-10-06';

        $this->postJson(route('schedule.abonement.place-fixed', $studentA), [
            'user_lesson_package_id' => $ulpA->id,
            'team_id' => $team->id,
            'start_date' => $startDate,
            'weekdays' => [1],
        ], $this->ajaxHeaders())->assertOk();

        $this->postJson(route('schedule.abonement.place-fixed', $studentB), [
            'user_lesson_package_id' => $ulpB->id,
            'team_id' => $team->id,
            'start_date' => $startDate,
            'weekdays' => [1],
        ], $this->ajaxHeaders())->assertOk();

        $slotA = (int) UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulpA->id)
            ->value('team_schedule_slot_id');
        $slotB = (int) UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulpB->id)
            ->value('team_schedule_slot_id');

        $this->assertSame($slotA, $slotB);
        $this->assertSame(
            1,
            TeamScheduleSlot::query()
                ->where('team_id', $team->id)
                ->where('weekday', 1)
                ->whereDate('date_start', '2000-01-01')
                ->whereDate('date_end', '9999-12-31')
                ->count()
        );
    }

    public function test_uses_existing_school_slot_when_it_covers_placement_period(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);

        $schoolSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2025-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2025-10-06',
            'weekdays' => [1],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $usedSlotId = (int) UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->value('team_schedule_slot_id');

        $this->assertSame((int) $schoolSlot->id, $usedSlotId);
        $this->assertSame(
            0,
            TeamScheduleSlot::query()
                ->where('team_id', $team->id)
                ->whereDate('date_start', '2000-01-01')
                ->count()
        );
    }

    public function test_place_non_ajax_creates_open_service_slot_when_school_slot_starts_in_future(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);

        $schoolSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2026-06-05',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $response = $this->post(route('schedule.abonement.place-fixed', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2025-10-06',
            'weekdays' => [1],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());

        $usedSlotId = (int) UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->value('team_schedule_slot_id');
        $this->assertNotSame((int) $schoolSlot->id, $usedSlotId);

        $serviceSlot = TeamScheduleSlot::query()->findOrFail($usedSlotId);
        $this->assertSame('2000-01-01', $serviceSlot->date_start?->format('Y-m-d'));
        $schoolSlot->refresh();
        $this->assertSame('2026-06-05', $schoolSlot->date_start?->format('Y-m-d'));
    }

    public function test_place_preview_ajax_with_future_school_slot_succeeds_without_writing(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2026-06-05',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);

        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2025-10-06',
                'weekdays' => [1],
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview', true)
            ->assertJsonPath('result.linked_count', 2);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $this->assertNull($ulp->fresh()->starts_at);
    }

    public function test_guest_and_without_permission_cannot_place_over_future_school_slot(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2026-06-05',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);
        $ulp = $this->makeFixedAssignment($student, lessons: 1, durationDays: 7);
        $payload = [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2025-10-06',
            'weekdays' => [1],
        ];

        Auth::logout();
        $this->postJson(route('schedule.abonement.place-fixed', $student), $payload)->assertUnauthorized();
        $this->post(route('schedule.abonement.place-fixed', $student), $payload)->assertRedirect();

        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.abonement.place-fixed', $student), $payload)
            ->assertForbidden();
        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.abonement.place-fixed', $student), array_merge($payload, ['_token' => csrf_token()]))
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }
}
