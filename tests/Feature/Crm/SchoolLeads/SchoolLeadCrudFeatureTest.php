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
            'partner_id' => $this->partner->id,
            'name'       => 'Удаляемый',
            'phone'      => '+7 900 000-00-01',
            'status'     => 'new',
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
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Новый',
            'phone'      => '+7 900 111-11-11',
            'status'     => 'new',
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'В работе',
            'phone'      => '+7 900 222-22-22',
            'status'     => 'processing',
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'statuses' => ['processing'],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsTotal'));
        $this->assertEquals(1, $response->json('recordsFiltered'));

        foreach ($response->json('data') as $row) {
            $this->assertSame('processing', $row['status']);
        }
    }

    public function test_update_fails_with_invalid_status(): void
    {
        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'status'     => 'new',
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'status' => 'invalid-status',
        ])->assertStatus(422);

        $lead->refresh();
        $this->assertSame('new', $lead->status?->value);
    }

    public function test_cannot_destroy_foreign_partner_lead(): void
    {
        $foreignLead = SchoolLead::create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'Чужой',
            'phone'      => '+7 900 999-99-99',
            'status'     => 'new',
        ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $foreignLead->id]))
            ->assertNotFound();
    }
}
