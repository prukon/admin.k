<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтр «Объект» на расписании школы: «Все», «Без объекта», конкретный объект;
 * модалка слота (location_id=none), week-API и smoke-доступ 200.
 */
final class SchoolScheduleLocationFilterFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

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
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantSchoolSchedulePermissions(): void
    {
        foreach ([
            'lessonPackages.view',
            'locations.view',
            'scheduleSlots.view',
            'scheduleSlots.manage',
            'scheduleSlots.table',
        ] as $permission) {
            $this->grantPermission($permission);
        }
    }

    /**
     * @return array{location: Location, team: Team, slot: TeamScheduleSlot}
     */
    private function seedLocatedSlot(string $timeStart = '10:00', string $timeEnd = '11:00'): array
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект фильтра',
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        return compact('location', 'team', 'slot');
    }

    /**
     * @return array{team: Team, slot: TeamScheduleSlot}
     */
    private function seedSlotWithoutObject(string $timeStart = '12:00', string $timeEnd = '13:00'): array
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        return compact('team', 'slot');
    }

    private function grantPermissionToUser(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_week_json_without_filter_returns_all_slots(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->seedLocatedSlot('09:00', '10:00');
        $this->seedSlotWithoutObject('11:00', '12:00');

        $occurrences = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->json('occurrences');

        $locationIds = collect($occurrences)->pluck('location_id')->unique()->values()->all();
        $this->assertContains(null, $locationIds);
        $this->assertGreaterThanOrEqual(2, count($occurrences));
    }

    public function test_week_json_location_filter_by_object_returns_only_matching_slots(): void
    {
        $this->grantPermission('lessonPackages.view');

        $located = $this->seedLocatedSlot('10:00', '11:00');
        $this->seedSlotWithoutObject('12:00', '13:00');

        $otherLocation = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $otherLocation->id,
        ]);
        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $otherTeam->id,
            'location_id' => $otherLocation->id,
            'weekday' => 1,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $filtered = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
            'location_id' => $located['location']->id,
        ]))
            ->assertOk()
            ->json('occurrences');

        $this->assertNotEmpty($filtered);
        foreach ($filtered as $occurrence) {
            $this->assertSame((int) $located['location']->id, (int) $occurrence['location_id']);
        }
    }

    public function test_week_json_location_filter_none_returns_only_slots_without_object(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->seedLocatedSlot();
        $without = $this->seedSlotWithoutObject();

        $none = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
            'location_id' => 'none',
        ]))
            ->assertOk()
            ->json('occurrences');

        $this->assertNotEmpty($none);
        foreach ($none as $occurrence) {
            $this->assertNull($occurrence['location_id']);
        }

        $slotIds = collect($none)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $without['slot']->id, $slotIds);
    }

    public function test_store_slot_accepts_location_id_none_for_team_without_object(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => 'none',
            'weekday' => 2,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Слот создан');

        $this->assertDatabaseHas('team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 2,
        ]);
    }

    public function test_store_slot_accepts_empty_location_id_as_null(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => '',
            'weekday' => 3,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 3,
        ]);
    }

    public function test_store_slot_rejects_object_location_for_team_without_object(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 4,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_store_slot_with_matching_team_and_object_location_succeeds(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 5,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('team_schedule_slots', [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 5,
        ]);
    }

    public function test_school_schedule_page_renders_location_filter_options(): void
    {
        $this->grantSchoolSchedulePermissions();

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Smoke объект',
            'is_enabled' => true,
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="schoolCalLocation"', $html);
        $this->assertStringContainsString('>Все</option>', $html);
        $this->assertStringContainsString('value="none"', $html);
        $this->assertStringContainsString('Без объекта</option>', $html);
        $this->assertStringContainsString('Smoke объект', $html);

        $createFormPos = strpos($html, 'id="slotCreateForm"');
        $this->assertNotFalse($createFormPos);
        $createFormHtml = substr($html, (int) $createFormPos, 4000);
        $locationPos = strpos($createFormHtml, 'js-slot-location-select');
        $teamSelectPos = strpos($createFormHtml, 'js-slot-team-select');
        $this->assertNotFalse($locationPos);
        $this->assertNotFalse($teamSelectPos);
        $this->assertLessThan($teamSelectPos, $locationPos);
    }

    public function test_school_schedule_page_and_location_filter_endpoints_return_200(): void
    {
        $this->grantSchoolSchedulePermissions();

        $located = $this->seedLocatedSlot('08:00', '09:00');
        $this->seedSlotWithoutObject('09:30', '10:30');

        $this->get(route('admin.lesson-packages.school-schedule'))->assertOk();
        $this->get(route('admin.lesson-packages.team-schedule-slots'))->assertOk();

        $weekUrls = [
            route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_MONDAY]),
            route('admin.lesson-packages.school-schedule.week', [
                'week' => self::WEEK_MONDAY,
                'location_id' => 'none',
            ]),
            route('admin.lesson-packages.school-schedule.week', [
                'week' => self::WEEK_MONDAY,
                'location_id' => $located['location']->id,
            ]),
        ];

        foreach ($weekUrls as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonStructure(['week_start', 'occurrences']);
        }

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))->assertOk();
        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 540,
            'view_end_min' => 1260,
        ])->assertOk();

        $teamWithoutObject = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $teamWithoutObject->id,
            'location_id' => 'none',
            'weekday' => 6,
            'time_start' => '15:00',
            'time_end' => '16:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();
    }

    public function test_school_schedule_location_surface_forbidden_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->grantPermissionToUser($actor, 'locations.view');
        $this->grantPermissionToUser($actor, 'scheduleSlots.view');
        $this->grantPermissionToUser($actor, 'scheduleSlots.manage');
        $this->actingAs($actor);

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $calls = [
            fn () => $this->get(route('admin.lesson-packages.school-schedule')),
            fn () => $this->getJson(route('admin.lesson-packages.school-schedule.week', [
                'week' => self::WEEK_MONDAY,
                'location_id' => 'none',
            ])),
            fn () => $this->getJson(route('admin.lesson-packages.school-schedule.week', [
                'week' => self::WEEK_MONDAY,
                'location_id' => $location->id,
            ])),
        ];

        foreach ($calls as $call) {
            $call()->assertForbidden();
        }

        $teamWithoutObject = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $teamWithoutObject->id,
            'location_id' => 'none',
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'is_enabled' => 1,
        ])->assertOk();
    }

    public function test_guest_cannot_access_school_schedule_location_endpoints(): void
    {
        Auth::logout();

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $calls = [
            fn () => $this->get(route('admin.lesson-packages.school-schedule')),
            fn () => $this->getJson(route('admin.lesson-packages.school-schedule.week', [
                'week' => self::WEEK_MONDAY,
                'location_id' => 'none',
            ])),
            fn () => $this->getJson(route('admin.lesson-packages.school-schedule.week', [
                'week' => self::WEEK_MONDAY,
                'location_id' => $location->id,
            ])),
        ];

        foreach ($calls as $call) {
            $this->assertContains($call()->getStatusCode(), [302, 401, 403]);
        }
    }
}
