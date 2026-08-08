<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к модалке «Добавить слот» и CRUD endpoint'ам team-schedule-slots.
 *
 * Страница school-schedule — lessonPackages.view; store/update/destroy — scheduleSlots.view + manage.
 *
 * @see SchoolSchedulePageFullAccessFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SlotCreateModalAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermissionToUser($user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        $this->grantPermissionToUser($this->user, $permissionName);
    }

    /**
     * @return array{team: Team, location: Location, slot: TeamScheduleSlot, storePayload: array<string, mixed>, updatePayload: array<string, mixed>}
     */
    private function seedContext(): array
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
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
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $storePayload = [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 3,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ];

        $updatePayload = [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ];

        return compact('team', 'location', 'slot', 'storePayload', 'updatePayload');
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function modalRelatedRoutes(array $ctx): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.school-schedule'),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.team-schedule-slots.show', $ctx['slot']),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.team-schedule-slots.store'),
                'data' => $ctx['storePayload'],
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.team-schedule-slots.update', $ctx['slot']),
                'data' => $ctx['updatePayload'],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.team-schedule-slots.destroy', $ctx['slot']),
            ],
        ];
    }

    public function test_guest_is_denied_on_page_and_slot_modal_endpoints(): void
    {
        $ctx = $this->seedContext();
        Auth::logout();

        foreach ($this->modalRelatedRoutes($ctx) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_without_lesson_packages_view_page_returns_403(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();
    }

    public function test_without_schedule_slots_manage_mutations_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.manage', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->grantPermissionToUser($actor, 'scheduleSlots.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $ctx = $this->seedContext();

        $this->get(route('admin.lesson-packages.school-schedule'))->assertOk();
        $this->getJson(route('admin.team-schedule-slots.show', $ctx['slot']))->assertOk();

        $this->postJson(route('admin.team-schedule-slots.store'), $ctx['storePayload'])->assertForbidden();
        $this->putJson(route('admin.team-schedule-slots.update', $ctx['slot']), $ctx['updatePayload'])->assertForbidden();
        $this->deleteJson(route('admin.team-schedule-slots.destroy', $ctx['slot']))->assertForbidden();
    }

    public function test_authorized_user_page_and_slot_endpoints_return_expected_statuses(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $ctx = $this->seedContext();

        $page = $this->get(route('admin.lesson-packages.school-schedule'));
        $page->assertOk();
        $this->assertNotSame('', trim($page->getContent()));
        $page->assertSee('id="slotCreateModal"', false);
        $page->assertSee('id="slotCreateSubmit">Добавить</button>', false);

        $show = $this->getJson(route('admin.team-schedule-slots.show', $ctx['slot']));
        $show->assertOk()
            ->assertJsonPath('id', $ctx['slot']->id)
            ->assertJsonStructure(['id', 'team_id', 'location_id', 'weekday', 'time_start', 'time_end']);
        $this->assertNotSame('', trim((string) $show->getContent()));

        $store = $this->postJson(route('admin.team-schedule-slots.store'), $ctx['storePayload']);
        $store->assertOk()
            ->assertJsonPath('message', 'Слот создан')
            ->assertJsonStructure(['message', 'slot' => ['id', 'team_id', 'location_id']]);
        $this->assertNotSame('', trim((string) $store->getContent()));
        $createdId = (int) $store->json('slot.id');
        $this->assertGreaterThan(0, $createdId);

        $update = $this->putJson(route('admin.team-schedule-slots.update', $ctx['slot']), $ctx['updatePayload']);
        $update->assertOk()
            ->assertJsonPath('message', 'Слот обновлён');
        $this->assertNotSame('', trim((string) $update->getContent()));

        $destroy = $this->deleteJson(route('admin.team-schedule-slots.destroy', TeamScheduleSlot::query()->findOrFail($createdId)));
        $destroy->assertOk()
            ->assertJsonPath('message', 'Слот удалён');
        $this->assertNotSame('', trim((string) $destroy->getContent()));
    }
}
