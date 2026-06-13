<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SportTypes;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\SportType;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SportTypesAuditLogsFeatureTest extends CrmTestCase
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

    public function test_logs_data_returns_200_with_sport_types_view(): void
    {
        $this->grantPermission('sport_types.view');

        $this->getJson(route('logs.data.sport-type', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_sport_types_view(): void
    {
        $actor = $this->createUserWithoutPermission('sport_types.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('logs.data.sport-type', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_with_sport_types_view(): void
    {
        $this->grantPermission('sport_types.view');

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false);
    }

    public function test_store_writes_sport_type_created_log(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Футбол',
            'description' => 'Командный вид спорта',
            'sort' => 10,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = $this->latestLog(AuditEvent::SportTypeCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::SportTypeCreated->level(), $log->level);
        $this->assertStringContainsString('Название: Футбол', (string) $log->description);
        $this->assertStringContainsString('Описание: Командный вид спорта', (string) $log->description);
        $this->assertStringContainsString('Сортировка: 10', (string) $log->description);
        $this->assertSame('Футбол', $log->target_label);
    }

    public function test_update_writes_sport_type_updated_log_with_field_diffs(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый вид',
            'description' => 'Старое описание',
            'sort' => 1,
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.sport-types.update', $sportType->id), [
            'name' => 'Новый вид',
            'description' => 'Новое описание',
            'sort' => 5,
            'is_enabled' => 0,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::SportTypeUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Название: Старый вид → Новый вид', (string) $log->description);
        $this->assertStringContainsString('Описание: Старое описание → Новое описание', (string) $log->description);
        $this->assertStringContainsString('Сортировка: 1 → 5', (string) $log->description);
        $this->assertStringContainsString('Активность: Да → Нет', (string) $log->description);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Без изменений',
            'description' => '',
            'sort' => 0,
            'is_enabled' => 1,
        ]);

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::SportTypeUpdated->value)
            ->count();

        $this->putJson(route('admin.sport-types.update', $sportType->id), [
            'name' => 'Без изменений',
            'description' => '',
            'sort' => 0,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $afterCount = MyLog::query()
            ->where('event', AuditEvent::SportTypeUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_destroy_writes_sport_type_deleted_log(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление',
        ]);

        $this->deleteJson(route('admin.sport-types.destroy', $sportType->id), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::SportTypeDeleted);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::SportTypeDeleted->level(), $log->level);
        $this->assertStringContainsString('Вид спорта удалён: На удаление', (string) $log->description);
        $this->assertSame('На удаление', $log->target_label);
    }

    public function test_logs_data_returns_written_sport_type_event_in_table(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $sportType = SportType::factory()->create([
            'partner_id'  => $this->partner->id,
            'name'        => 'В таблице логов',
            'description' => 'До',
            'sort'        => 1,
            'is_enabled'  => 1,
        ]);

        $this->putJson(route('admin.sport-types.update', $sportType->id), [
            'name'        => 'После изменения',
            'description' => 'После',
            'sort'        => 1,
            'is_enabled'  => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.sport-type', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'В таблице логов → После изменения')),
            'Ожидалась запись sport_type.updated в logs-data.'
        );
    }
}
