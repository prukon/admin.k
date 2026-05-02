<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Location;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\TeamScheduleSlotException;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamScheduleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TeamScheduleSlotsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('scheduleSlots.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.team-schedule-slots.index'))->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->get(route('admin.team-schedule-slots.index'))
            ->assertOk()
            ->assertSee('Расписание школы');
    }

    public function test_store_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->post(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_store_creates_slot_and_normalizes_open_ended_date(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
        ]);
    }

    public function test_store_rejects_time_overlap_on_same_weekday_within_partner(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '09:30',
            'time_end' => '10:30',
            'date_start' => '2026-02-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('errors.weekday.0', 'В этой локации слот пересекается по времени с уже существующим занятием');
    }

    public function test_store_allows_same_time_different_locations(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $locA = Location::factory()->create(['partner_id' => $this->partner->id]);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'location_id' => $locA->id,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $teamB->id,
            'location_id' => $locB->id,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();
    }

    public function test_store_allows_non_overlapping_time(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-02-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();
    }

    public function test_skip_occurrence_hides_single_calendar_day(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $slot), [
            'occurrence_date' => '2026-05-04',
        ])->assertOk();

        $calendar = app(TeamScheduleCalendarService::class);
        $weekMay4 = $calendar->occurrencesForWeek((int) $this->partner->id, CarbonImmutable::parse('2026-05-04'), null);
        $this->assertFalse(collect($weekMay4)->contains(fn (array $o): bool => (int) $o['id'] === $slot->id && $o['date'] === '2026-05-04'));

        $weekMay11 = $calendar->occurrencesForWeek((int) $this->partner->id, CarbonImmutable::parse('2026-05-11'), null);
        $this->assertTrue(collect($weekMay11)->contains(fn (array $o): bool => (int) $o['id'] === $slot->id && $o['date'] === '2026-05-11'));

        $this->assertDatabaseHas('team_schedule_slot_exceptions', [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => '2026-05-04',
            'deleted_at' => null,
        ]);

        $this->assertTrue(MyLog::query()->where('type', 46)->where('action', 461)->where('target_id', $slot->id)->exists());
    }

    public function test_skip_occurrence_blocked_when_assignment_exists(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-05-04',
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
            'user_lesson_package_id' => null,
        ]);

        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $slot), [
            'occurrence_date' => '2026-05-04',
        ])->assertStatus(422)
            ->assertJsonPath('conflicts.0.occurrence_date', '2026-05-04');
    }

    public function test_truncate_from_date_sets_date_end_before_anchor(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.team-schedule-slots.truncate-from-date', $slot), [
            'occurrence_date' => '2026-05-11',
        ])->assertOk();

        $slot->refresh();
        $this->assertSame('2026-05-10', $slot->date_end->format('Y-m-d'));

        $calendar = app(TeamScheduleCalendarService::class);
        $weekMay11 = $calendar->occurrencesForWeek((int) $this->partner->id, CarbonImmutable::parse('2026-05-11'), null);
        $this->assertFalse(collect($weekMay11)->contains(fn (array $o): bool => (int) $o['id'] === $slot->id));
    }

    public function test_destroy_soft_deletes_when_no_assignments(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 3,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->deleteJson(route('admin.team-schedule-slots.destroy', $slot))->assertOk();

        $this->assertSoftDeleted('team_schedule_slots', ['id' => $slot->id]);
        $this->assertTrue(MyLog::query()->where('type', 46)->where('action', 463)->where('target_id', $slot->id)->exists());
    }

    public function test_destroy_blocked_when_assignment_exists(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 4,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-05-07',
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
            'user_lesson_package_id' => null,
        ]);

        $this->deleteJson(route('admin.team-schedule-slots.destroy', $slot))
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'conflicts']);
    }

    public function test_store_same_tuple_allowed_after_previous_soft_deleted(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $payload = [
            'team_id' => $team->id,
            'weekday' => 5,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'date_start' => '2026-03-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ];

        $this->postJson(route('admin.team-schedule-slots.store'), $payload)->assertOk();
        $firstId = (int) TeamScheduleSlot::query()->where('team_id', $team->id)->orderByDesc('id')->value('id');
        $this->assertGreaterThan(0, $firstId);

        $this->deleteJson(route('admin.team-schedule-slots.destroy', $firstId))->assertOk();

        $this->postJson(route('admin.team-schedule-slots.store'), $payload)->assertOk();
        $this->assertSame(1, TeamScheduleSlot::query()->where('team_id', $team->id)->count());
        $this->assertSame(2, TeamScheduleSlot::withTrashed()->where('team_id', $team->id)->count());
    }

    public function test_update_shorten_period_blocked_by_assignment(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '17:00',
            'time_end' => '18:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-06-08',
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
            'user_lesson_package_id' => null,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '17:00',
            'time_end' => '18:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-06-01',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonStructure(['conflicts']);
    }

    public function test_update_split_truncates_left_and_creates_new_segment(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $teamB->id,
            'weekday' => 2,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'apply_changes_from' => '2026-06-01',
            'is_enabled' => 1,
        ])->assertOk()
            ->assertJsonPath('truncated_slot_id', $slot->id);

        $slot->refresh();
        $this->assertSame('2026-05-31', $slot->date_end->format('Y-m-d'));
        $this->assertSame((int) $teamA->id, (int) $slot->team_id);
        $this->assertSame(1, (int) $slot->weekday);

        $newId = (int) TeamScheduleSlot::query()
            ->where('partner_id', $this->partner->id)
            ->where('id', '!=', $slot->id)
            ->orderByDesc('id')
            ->value('id');
        $this->assertGreaterThan(0, $newId);
        $new = TeamScheduleSlot::query()->findOrFail($newId);
        $this->assertSame('2026-06-01', $new->date_start->format('Y-m-d'));
        $this->assertSame('2026-08-31', $new->date_end->format('Y-m-d'));
        $this->assertSame((int) $teamB->id, (int) $new->team_id);
        $this->assertSame(2, (int) $new->weekday);

        $this->assertTrue(MyLog::query()->where('type', 46)->where('action', 464)->where('target_id', $newId)->exists());
    }

    public function test_update_split_blocked_when_assignment_starts_on_or_after_apply_date(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '13:00',
            'time_end' => '14:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-06-15',
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
            'user_lesson_package_id' => null,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 3,
            'time_start' => '13:00',
            'time_end' => '14:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-06-01',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('conflicts.0.occurrence_date', '2026-06-15');
    }

    public function test_update_split_removes_exceptions_on_and_after_apply_date(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '15:00',
            'time_end' => '16:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'is_enabled' => 1,
        ]);

        TeamScheduleSlotException::query()->create([
            'partner_id' => $this->partner->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => '2026-05-18',
        ]);
        TeamScheduleSlotException::query()->create([
            'partner_id' => $this->partner->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => '2026-06-08',
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 2,
            'time_start' => '15:00',
            'time_end' => '16:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'apply_changes_from' => '2026-06-01',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('team_schedule_slot_exceptions', [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => '2026-05-18',
            'deleted_at' => null,
        ]);

        $this->assertSoftDeleted('team_schedule_slot_exceptions', [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => '2026-06-08',
        ]);
    }

    public function test_update_full_period_identity_change_blocked_when_any_assignment(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 4,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'date_start' => '2026-03-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-03-05',
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
            'user_lesson_package_id' => null,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 5,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'date_start' => '2026-03-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-03-01',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonStructure(['conflicts']);
    }

    public function test_update_full_period_identity_change_ok_without_assignments(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-04-01',
            'date_end' => '2026-10-31',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $teamB->id,
            'weekday' => 2,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-04-01',
            'date_end' => '2026-10-31',
            'apply_changes_from' => '2026-04-01',
            'is_enabled' => 1,
        ])->assertOk();

        $slot->refresh();
        $this->assertSame((int) $teamB->id, (int) $slot->team_id);
        $this->assertSame(2, (int) $slot->weekday);
    }

    public function test_update_split_rejects_overlap_on_new_segment(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $loc->id,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-06-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $loc->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'location_id' => $loc->id,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-06-01',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('errors.weekday.0', 'В этой локации слот пересекается по времени с уже существующим занятием');
    }

    public function test_show_ok_with_view_permission(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-02-01',
            'date_end' => '2026-12-15',
            'is_enabled' => 1,
        ]);

        $this->getJson(route('admin.team-schedule-slots.show', $slot))
            ->assertOk()
            ->assertJsonPath('id', $slot->id)
            ->assertJsonPath('date_start', '2026-02-01')
            ->assertJsonPath('date_end', '2026-12-15');
    }

    public function test_show_forbidden_without_view_permission(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $user = $this->createUserWithoutPermission('scheduleSlots.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.team-schedule-slots.show', $slot))->assertStatus(403);
    }

    public function test_show_not_found_for_foreign_partner_slot(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $foreignSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'team_id' => $foreignTeam->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->getJson(route('admin.team-schedule-slots.show', $foreignSlot))->assertNotFound();
    }

    public function test_update_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 3,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 3,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_destroy_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 5,
            'time_start' => '15:00',
            'time_end' => '16:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $this->deleteJson(route('admin.team-schedule-slots.destroy', $slot))->assertStatus(403);
    }

    public function test_skip_occurrence_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $slot), [
            'occurrence_date' => '2026-06-01',
        ])->assertStatus(403);
    }

    public function test_truncate_from_date_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.team-schedule-slots.truncate-from-date', $slot), [
            'occurrence_date' => '2026-06-08',
        ])->assertStatus(403);
    }

    public function test_update_full_period_preserves_slot_id_when_only_period_or_flags_change(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 4,
            'time_start' => '18:00',
            'time_end' => '19:00',
            'date_start' => '2026-05-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $beforeId = $slot->id;

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 4,
            'time_start' => '18:00',
            'time_end' => '19:00',
            'date_start' => '2026-05-01',
            'date_end' => '2027-03-31',
            'apply_changes_from' => '2026-05-01',
            'is_enabled' => 1,
        ])->assertOk();

        $slot->refresh();
        $this->assertSame($beforeId, $slot->id);
        $this->assertSame('2027-03-31', $slot->date_end->format('Y-m-d'));
    }

    public function test_update_location_change_ok_without_assignments(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $locA = Location::factory()->create(['partner_id' => $this->partner->id]);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $locA->id,
            'weekday' => 2,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'date_start' => '2026-04-01',
            'date_end' => '2026-11-30',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'location_id' => $locB->id,
            'weekday' => 2,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'date_start' => '2026-04-01',
            'date_end' => '2026-11-30',
            'apply_changes_from' => '2026-04-01',
            'is_enabled' => 1,
        ])->assertOk();

        $slot->refresh();
        $this->assertSame((int) $locB->id, (int) $slot->location_id);
    }

    public function test_update_validation_rejects_apply_changes_from_before_slot_period(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-08-10',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-08-10',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-08-01',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apply_changes_from']);
    }

    public function test_update_validation_rejects_apply_changes_from_after_slot_period_end(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 3,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-05-01',
            'date_end' => '2026-09-30',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 3,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-05-01',
            'date_end' => '2026-09-30',
            'apply_changes_from' => '2026-10-15',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apply_changes_from']);
    }

    public function test_update_split_left_overlap_rejected(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $loc->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-05-31',
            'is_enabled' => 1,
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $loc->id,
            'weekday' => 1,
            'time_start' => '09:30',
            'time_end' => '10:30',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'location_id' => $loc->id,
            'weekday' => 2,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'apply_changes_from' => '2026-06-01',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('errors.weekday.0', 'В этой локации слот пересекается по времени с уже существующим занятием');
    }

    public function test_destroy_not_found_for_foreign_partner_slot(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $foreignSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'team_id' => $foreignTeam->id,
            'location_id' => null,
            'weekday' => 6,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->deleteJson(route('admin.team-schedule-slots.destroy', $foreignSlot))->assertNotFound();
    }

    public function test_update_not_found_for_foreign_partner_slot(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);
        $foreignSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'team_id' => $foreignTeam->id,
            'location_id' => null,
            'weekday' => 2,
            'time_start' => '13:00',
            'time_end' => '14:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $foreignSlot), [
            'team_id' => $foreignTeam->id,
            'weekday' => 2,
            'time_start' => '13:00',
            'time_end' => '14:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ])->assertNotFound();
    }

    public function test_assignments_on_left_segment_do_not_block_split(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-05-18',
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
            'user_lesson_package_id' => null,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 2,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-05-04',
            'date_end' => '2026-08-31',
            'apply_changes_from' => '2026-06-01',
            'is_enabled' => 1,
        ])->assertOk();

        $slot->refresh();
        $this->assertSame('2026-05-31', $slot->date_end->format('Y-m-d'));
    }
}

