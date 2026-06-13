<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\AuditEvent;
use App\Models\District;
use App\Models\Location;
use App\Models\MyLog;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolLeadsAuditLogsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

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

    public function test_logs_data_returns_200_with_school_leads_view(): void
    {
        $this->grantPermission('schoolLeads.view');

        $this->getJson(route('logs.data.school-lead', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_school_leads_view(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('logs.data.school-lead', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_with_school_leads_view(): void
    {
        $this->grantPermission('schoolLeads.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false);
    }

    public function test_update_writes_school_lead_updated_log_with_field_diffs(): void
    {
        $this->grantPermission('schoolLeads.view');

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name' => 'Иван Петров',
            'phone' => '+7 900 000-00-01',
            'status' => 'new',
            'comment' => 'Старый комментарий',
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'status' => 'processing',
            'comment' => 'Новый комментарий',
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::SchoolLeadUpdated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::SchoolLeadUpdated->level(), $log->level);
        $this->assertStringContainsString('Статус: Новый → Обработка', (string) $log->description);
        $this->assertStringContainsString('Комментарий: Старый комментарий → Новый комментарий', (string) $log->description);
        $this->assertStringContainsString("Заявка #{$lead->id}: Иван Петров", (string) $log->target_label);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $this->grantPermission('schoolLeads.view');

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name' => 'Без изменений',
            'phone' => '+7 900 000-00-02',
            'status' => 'new',
            'comment' => '',
        ]);

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::SchoolLeadUpdated->value)
            ->count();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'status' => 'new',
            'comment' => '',
        ])->assertOk();

        $afterCount = MyLog::query()
            ->where('event', AuditEvent::SchoolLeadUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_update_logs_district_and_location_changes(): void
    {
        $this->grantPermission('schoolLeads.view');
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Центральный']);
        $location = Location::factory()->forDistrict($district)->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект А',
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name' => 'С районом',
            'phone' => '+7 900 000-00-03',
            'status' => 'new',
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'status' => 'new',
            'district_id' => $district->id,
            'location_id' => $location->id,
        ])->assertOk();

        $log = MyLog::query()
            ->where('event', AuditEvent::SchoolLeadUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Район: не указан → Центральный', (string) $log->description);
        $this->assertStringContainsString('Объект: не указан → Объект А', (string) $log->description);
    }

    public function test_destroy_writes_school_lead_deleted_log(): void
    {
        $this->grantPermission('schoolLeads.view');

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление',
            'phone' => '+7 900 000-00-04',
            'status' => 'new',
        ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $lead->id]))
            ->assertOk();

        $log = $this->latestLog(AuditEvent::SchoolLeadDeleted);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::SchoolLeadDeleted->level(), $log->level);
        $this->assertStringContainsString('Заявка удалена:', (string) $log->description);
        $this->assertStringContainsString("Заявка #{$lead->id}: На удаление", (string) $log->target_label);
    }

    public function test_logs_data_returns_written_school_lead_event_in_table(): void
    {
        $this->grantPermission('schoolLeads.view');

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'В таблице логов',
            'phone'      => '+7 900 000-00-05',
            'status'     => 'new',
            'comment'    => 'До',
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'status'  => 'processing',
            'comment' => 'После',
        ])->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.school-lead', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Статус: Новый → Обработка')),
            'Ожидалась запись school_lead.updated в logs-data.'
        );
    }
}
