<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Locations;

use App\Enums\AuditEvent;
use App\Models\District;
use App\Models\Location;
use App\Models\MyLog;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LocationsAuditLogsFeatureTest extends CrmTestCase
{
    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Центральный',
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

    private function latestLog(AuditEvent $event): ?MyLog
    {
        return MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', $event->value)
            ->latest('id')
            ->first();
    }

    public function test_logs_data_returns_200_with_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $this->getJson(route('logs.data.location', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('logs.data.location', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_with_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false);
    }

    public function test_store_writes_location_created_log(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Новый объект',
            'district_id' => $this->district->id,
            'address' => 'ул. Тестовая, 1',
            'description' => 'Описание объекта',
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = $this->latestLog(AuditEvent::LocationCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::LocationCreated->level(), $log->level);
        $this->assertStringContainsString('Название: Новый объект', (string) $log->description);
        $this->assertStringContainsString('Район: Центральный', (string) $log->description);
        $this->assertStringContainsString('Адрес: ул. Тестовая, 1', (string) $log->description);
        $this->assertSame('Новый объект', $log->target_label);
    }

    public function test_update_writes_location_updated_log_with_field_diffs(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый объект',
            'address' => 'Старый адрес',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.locations.update', $location->id), [
            'name' => 'Новое название',
            'district_id' => $this->district->id,
            'address' => 'Новый адрес',
            'description' => '',
            'admin_user_ids' => [],
            'team_ids' => [],
            'is_enabled' => 0,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::LocationUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Название: Старый объект → Новое название', (string) $log->description);
        $this->assertStringContainsString('Адрес: Старый адрес → Новый адрес', (string) $log->description);
        $this->assertStringContainsString('Активность: Да → Нет', (string) $log->description);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name' => 'Без изменений',
            'address' => 'Адрес',
            'is_enabled' => 1,
        ]);

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::LocationUpdated->value)
            ->count();

        $this->putJson(route('admin.locations.update', $location->id), [
            'name' => 'Без изменений',
            'district_id' => $this->district->id,
            'address' => 'Адрес',
            'description' => '',
            'admin_user_ids' => [],
            'team_ids' => [],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $afterCount = MyLog::query()
            ->where('event', AuditEvent::LocationUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_update_logs_team_changes_when_teams_synced(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект с группами',
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа А',
            'location_id' => null,
        ]);

        $this->putJson(route('admin.locations.update', $location->id), [
            'name' => 'Объект с группами',
            'district_id' => $this->district->id,
            'address' => '',
            'description' => '',
            'admin_user_ids' => [],
            'team_ids' => [$team->id],
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = MyLog::query()
            ->where('event', AuditEvent::LocationUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Группы: не указаны → Группа А', (string) $log->description);
    }

    public function test_destroy_writes_location_deleted_log(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление',
        ]);

        $this->deleteJson(route('admin.locations.destroy', $location->id), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::LocationDeleted);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::LocationDeleted->level(), $log->level);
        $this->assertStringContainsString('Объект удалён: На удаление', (string) $log->description);
        $this->assertSame('На удаление', $log->target_label);
    }

    public function test_logs_data_returns_written_location_event_in_table(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'В таблице логов',
            'address'    => 'До',
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.locations.update', $location->id), [
            'name'           => 'После изменения',
            'district_id'    => $this->district->id,
            'address'        => 'После',
            'description'    => '',
            'admin_user_ids' => [],
            'team_ids'       => [],
            'is_enabled'     => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.location', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'В таблице логов → После изменения')),
            'Ожидалась запись location.updated в logs-data.'
        );
    }
}
