<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт модалки слота: postJson/putJson с Accept JSON → 200/422 + message/slot/errors.
 *
 * @see LessonOccurrenceStatusesAjaxContractFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SlotCreateModalAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach (['scheduleSlots.view', 'scheduleSlots.manage', 'lessonPackages.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array{team: Team, location: Location}
     */
    private function seedTeamWithLocation(): array
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        return compact('team', 'location');
    }

    public function test_store_ajax_json_contract_creates_slot(): void
    {
        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $response = $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Слот создан')
            ->assertJsonPath('slot.team_id', $team->id)
            ->assertJsonPath('slot.location_id', $location->id)
            ->assertJsonStructure([
                'message',
                'slot' => ['id', 'team_id', 'location_id', 'weekday', 'time_start', 'time_end'],
            ]);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('team_schedule_slots', [
            'id' => (int) $response->json('slot.id'),
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 2,
        ]);
    }

    public function test_store_ajax_validation_returns_422_with_errors_under_fields(): void
    {
        $response = $this->postJson(route('admin.team-schedule-slots.store'), [
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '09:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_id', 'time_end', 'date_start']);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_store_ajax_rejects_team_location_mismatch_with_422_on_team_id(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $response = $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 3,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
        $this->assertNotSame('', (string) ($response->json('errors.team_id.0') ?? ''));
    }

    public function test_update_ajax_json_contract(): void
    {
        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $response = $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '09:30',
            'time_end' => '10:30',
            'date_start' => '2026-01-01',
            'date_end' => '2026-06-30',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Слот обновлён');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $slot->refresh();
        $this->assertSame('09:30', substr((string) $slot->time_start, 0, 5));
        $this->assertSame('10:30', substr((string) $slot->time_end, 0, 5));
        $this->assertSame('2026-06-30', $slot->date_end?->format('Y-m-d'));
    }

    public function test_update_ajax_validation_returns_422(): void
    {
        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $response = $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '11:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_show_ajax_json_contract_for_edit_modal(): void
    {
        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 6,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-02-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ]);

        $this->getJson(route('admin.team-schedule-slots.show', $slot))
            ->assertOk()
            ->assertJsonPath('id', $slot->id)
            ->assertJsonPath('team_id', $team->id)
            ->assertJsonPath('location_id', $location->id)
            ->assertJsonPath('weekday', 6)
            ->assertJsonPath('time_start', '12:00')
            ->assertJsonPath('time_end', '13:00')
            ->assertJsonPath('date_start', '2026-02-01')
            ->assertJsonPath('date_end', '2026-12-31');
    }
}
