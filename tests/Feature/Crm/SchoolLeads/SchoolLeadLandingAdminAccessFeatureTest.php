<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\PartnerWidget;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к вкладке «Страница заявки» в CRM: middleware can:schoolLeadLanding.view.
 */
final class SchoolLeadLandingAdminAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_guest_cannot_access_admin_landing_tab(): void
    {
        Auth::logout();

        $this->get(route('admin.school-leads.landing'))
            ->assertRedirect();
    }

    public function test_user_without_school_lead_landing_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('admin.school-leads.landing'))->assertForbidden();
    }

    public function test_user_with_school_lead_landing_view_landing_tab_returns_ok(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $widget = PartnerWidget::query()
            ->where('partner_id', $this->partner->id)
            ->firstOrFail();

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'landing')
            ->assertViewHas('landingUrl')
            ->assertSee('>Страница заявки</a>', false)
            ->assertSee(route('lead.show', ['landingKey' => $widget->landing_key]), false);
    }

    private function grantPermission(\App\Models\User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
