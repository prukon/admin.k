<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Districts;

use App\Enums\AuditEvent;
use App\Models\District;
use App\Models\Location;
use App\Models\MyLog;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class DistrictsAuditLogsFeatureTest extends CrmTestCase
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

    public function test_logs_data_returns_200_with_districts_view(): void
    {
        $this->grantPermission('districts.view');

        $this->getJson(route('logs.data.district', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('logs.data.district', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_with_districts_view(): void
    {
        $this->grantPermission('districts.view');

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false);
    }

    public function test_store_writes_district_created_log(): void
    {
        $this->grantPermission('districts.view');

        $this->postJson(route('admin.districts.store'), [
            'name' => 'Новый район',
            'sort_order' => 5,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = $this->latestLog(AuditEvent::DistrictCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::DistrictCreated->level(), $log->level);
        $this->assertStringContainsString('Название: Новый район', (string) $log->description);
        $this->assertStringContainsString('Сортировка: 5', (string) $log->description);
        $this->assertStringContainsString('Активность: Да', (string) $log->description);
        $this->assertSame('Новый район', $log->target_label);
    }

    public function test_update_writes_district_updated_log_with_field_diffs(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Старый район',
            'sort_order' => 1,
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'Новое название',
            'sort_order' => 10,
            'is_enabled' => 0,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::DistrictUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Название: Старый район → Новое название', (string) $log->description);
        $this->assertStringContainsString('Сортировка: 1 → 10', (string) $log->description);
        $this->assertStringContainsString('Активность: Да → Нет', (string) $log->description);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Без изменений',
            'sort_order' => 3,
            'is_enabled' => 1,
        ]);

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::DistrictUpdated->value)
            ->count();

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'Без изменений',
            'sort_order' => 3,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $afterCount = MyLog::query()
            ->where('event', AuditEvent::DistrictUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_update_logs_location_changes_when_locations_synced(): void
    {
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Район с объектами',
        ]);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект А',
            'district_id' => null,
        ]);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'Район с объектами',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = MyLog::query()
            ->where('event', AuditEvent::DistrictUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Объекты: не указаны → Объект А', (string) $log->description);
    }

    public function test_destroy_writes_district_deleted_log(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'На удаление',
        ]);

        $this->deleteJson(route('admin.districts.destroy', $district->id), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::DistrictDeleted);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::DistrictDeleted->level(), $log->level);
        $this->assertStringContainsString('Район удалён: На удаление', (string) $log->description);
        $this->assertSame('На удаление', $log->target_label);
    }

    public function test_logs_data_returns_written_district_event_in_table(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name'       => 'В таблице логов',
            'sort_order' => 1,
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name'       => 'После изменения',
            'sort_order' => 1,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.district', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'В таблице логов → После изменения')),
            'Ожидалась запись district.updated в logs-data.'
        );
    }

    public function test_destroy_with_attached_locations_does_not_write_log(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Район с привязкой',
        ]);

        Location::factory()->forDistrict($district)->create([
            'partner_id' => $this->partner->id,
            'name' => 'Привязанный объект',
        ]);

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::DistrictDeleted->value)
            ->count();

        $this->deleteJson(route('admin.districts.destroy', $district->id), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(422);

        $afterCount = MyLog::query()
            ->where('event', AuditEvent::DistrictDeleted->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }
}
