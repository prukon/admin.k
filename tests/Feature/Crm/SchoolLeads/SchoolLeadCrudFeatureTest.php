<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

class SchoolLeadCrudFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_destroy_soft_deletes_school_lead(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Удаляемый',
            'phone'                 => '+7 900 000-00-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $lead->id]))
            ->assertOk()
            ->assertJson(['message' => 'Заявка удалена.']);

        $lead->refresh();
        $this->assertNotNull($lead->deleted_at);
        $this->assertNull(SchoolLead::whereNull('deleted_at')->find($lead->id));
    }

    public function test_datatable_filters_by_statuses(): void
    {
        $processingStatusId = $this->schoolLeadProcessingStatusId();

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Новый',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'В работе',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $processingStatusId,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'status_ids' => [$processingStatusId],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsTotal'));
        $this->assertEquals(1, $response->json('recordsFiltered'));

        foreach ($response->json('data') as $row) {
            $this->assertSame($processingStatusId, (int) $row['school_lead_status_id']);
        }
    }

    public function test_update_fails_with_invalid_status(): void
    {
        $systemStatusId = $this->schoolLeadSystemStatusId();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Иван',
            'phone'                 => '+7 999 123-45-67',
            'school_lead_status_id' => $systemStatusId,
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => 999999,
        ])->assertStatus(422);

        $lead->refresh();
        $this->assertSame($systemStatusId, (int) $lead->school_lead_status_id);
    }

    public function test_cannot_destroy_foreign_partner_lead(): void
    {
        $foreignLead = SchoolLead::create([
            'partner_id'            => $this->foreignPartner->id,
            'name'                  => 'Чужой',
            'phone'                 => '+7 900 999-99-99',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $foreignLead->id]))
            ->assertNotFound();
    }
}
