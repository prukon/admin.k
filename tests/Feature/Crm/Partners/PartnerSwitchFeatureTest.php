<?php

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use Tests\Feature\Crm\CrmTestCase;

class PartnerSwitchFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['2fa:passed' => true]);
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    public function test_superadmin_can_switch_to_active_partner(): void
    {
        $target = Partner::factory()->create(['is_enabled' => true]);

        $response = $this->from(route('dashboard'))
            ->post(route('partner.switch'), [
                'partner_id' => $target->id,
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('current_partner', $target->id);
    }

    public function test_switch_rejects_disabled_partner(): void
    {
        $disabled = Partner::factory()->create(['is_enabled' => false]);

        $response = $this->from(route('dashboard'))
            ->post(route('partner.switch'), [
                'partner_id' => $disabled->id,
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('partner_id');
        $this->assertNotEquals($disabled->id, session('current_partner'));
    }

    public function test_switch_rejects_soft_deleted_partner(): void
    {
        $deleted = Partner::factory()->create(['is_enabled' => true]);
        $deletedId = $deleted->id;
        $deleted->delete();

        $response = $this->from(route('dashboard'))
            ->post(route('partner.switch'), [
                'partner_id' => $deletedId,
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('partner_id');
    }
}
