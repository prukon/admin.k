<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для store/update слотов (модалка «Добавить слот» / «Редактировать занятие»).
 *
 * Backend сейчас всегда отвечает JSON (как custom payments), не web-redirect 302.
 * Проверяем: без X-Requested-With запись создаётся/обновляется, ответ не пустой 200 и не 500.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see CustomPaymentsCrudAccessFeatureTest::test_update_non_ajax_returns_json_contract_and_persists_not_empty_200
 */
final class SlotCreateModalNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_store_non_ajax_returns_json_and_creates_slot_not_empty_200(): void
    {
        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.team-schedule-slots.store'), [
                'team_id' => $team->id,
                'location_id' => $location->id,
                'weekday' => 2,
                'time_start' => '10:00',
                'time_end' => '11:00',
                'date_start' => '2026-01-01',
                'date_end' => '2026-12-31',
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Текущий контракт store — JSON, не redirect');
        $response->assertOk();
        $response->assertJsonPath('message', 'Слот создан');
        $response->assertJsonStructure(['message', 'slot' => ['id', 'team_id', 'location_id']]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
        ]);
    }

    public function test_store_non_ajax_validation_failure_returns_422_or_redirect_not_empty_200(): void
    {
        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.team-schedule-slots.store'), [
                'weekday' => 1,
            ]);

        $this->assertContains($response->getStatusCode(), [302, 422]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['team_id', 'time_start', 'time_end', 'date_start']);
        } else {
            $response->assertJsonValidationErrors(['team_id', 'time_start', 'time_end', 'date_start']);
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_update_non_ajax_returns_json_and_updates_slot_not_empty_200(): void
    {
        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 4,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->put(route('admin.team-schedule-slots.update', $slot), [
                'team_id' => $team->id,
                'location_id' => $location->id,
                'weekday' => 4,
                'time_start' => '14:00',
                'time_end' => '15:30',
                'date_start' => '2026-01-01',
                'date_end' => '2026-12-31',
                'apply_changes_from' => '2026-01-01',
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Текущий контракт update — JSON, не redirect');
        $response->assertOk();
        $response->assertJsonPath('message', 'Слот обновлён');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $slot->refresh();
        $this->assertSame('15:30', substr((string) $slot->time_end, 0, 5));
        $this->assertSame('2026-12-31', $slot->date_end?->format('Y-m-d'));
    }

    public function test_update_non_ajax_validation_failure_returns_422_or_redirect_not_empty_200(): void
    {
        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 5,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->put(route('admin.team-schedule-slots.update', $slot), [
                'team_id' => $team->id,
                'weekday' => 5,
                'time_start' => '16:00',
                'time_end' => '15:00',
                'date_start' => '2026-01-01',
                'date_end' => '2026-12-31',
                'apply_changes_from' => '2026-01-01',
                'is_enabled' => 1,
            ]);

        $this->assertContains($response->getStatusCode(), [302, 422]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['time_end']);
        } else {
            $this->assertNotSame('', trim((string) $response->getContent()));
        }

        $slot->refresh();
        $this->assertSame('17:00', substr((string) $slot->time_end, 0, 5));
    }

    public function test_store_non_ajax_forbidden_without_manage_permission(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.manage', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('scheduleSlots.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        ['team' => $team, 'location' => $location] = $this->seedTeamWithLocation();

        $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.team-schedule-slots.store'), [
                'team_id' => $team->id,
                'location_id' => $location->id,
                'weekday' => 1,
                'time_start' => '09:00',
                'time_end' => '10:00',
                'date_start' => '2026-01-01',
                'date_end' => '2026-12-31',
                'is_enabled' => 1,
            ])
            ->assertForbidden();
    }
}
