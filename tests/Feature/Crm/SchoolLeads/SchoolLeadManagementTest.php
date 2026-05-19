<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\SchoolLeadStatus;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

class SchoolLeadManagementTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_datatable_returns_only_current_partner_leads(): void
    {
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Свой',
            'phone'      => '+7 900 111-11-11',
            'status'     => 'new',
        ]);

        SchoolLead::create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'Чужой',
            'phone'      => '+7 900 222-22-22',
            'status'     => 'new',
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsTotal'));
        $this->assertEquals('Свой', $response->json('data.0.name'));
    }

    public function test_update_school_lead_status_and_comment(): void
    {
        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'status'     => 'new',
        ]);

        $response = $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'status'  => 'processing',
            'comment' => 'Перезвонить',
        ]);

        $response->assertOk()->assertJson([
            'status'  => 'processing',
            'comment' => 'Перезвонить',
        ]);

        $lead->refresh();
        $this->assertEquals(SchoolLeadStatus::Processing, $lead->status);
        $this->assertEquals('Перезвонить', $lead->comment);
    }

    public function test_cannot_update_foreign_partner_lead(): void
    {
        $lead = SchoolLead::create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'Чужой',
            'phone'      => '+7 900 000-00-00',
            'status'     => 'new',
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'status' => 'spam',
        ])->assertNotFound();
    }
}
