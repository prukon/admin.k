<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
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
            ->assertJsonPath('errors.weekday.0', 'Слот пересекается с уже существующим расписанием школы');
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
}

