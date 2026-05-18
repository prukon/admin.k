<?php

namespace Tests\Feature\Crm\PartnerLeads;

use App\Models\PartnerLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerLeadSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function partner_lead_model_soft_delete_works(): void
    {
        $partnerLead = PartnerLead::create([
            'name'   => 'Тест',
            'phone'  => '+7 900 000-00-00',
            'status' => 'new',
        ]);

        $this->assertDatabaseHas('partner_leads', [
            'id' => $partnerLead->id,
        ]);

        $partnerLead->delete();

        $fresh = PartnerLead::withTrashed()->find($partnerLead->id);

        $this->assertNotNull(
            $fresh,
            'Запись должна существовать при withTrashed().'
        );

        $this->assertNotNull(
            $fresh->deleted_at,
            'deleted_at должен быть заполнен после soft delete.'
        );
    }
}
