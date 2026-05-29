<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\PartnerWidget;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к вкладке «Страница заявки» в CRM: middleware can:schoolWidget.view.
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

    public function test_user_without_school_widget_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolWidget.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('admin.school-leads.landing'))->assertForbidden();
    }

    public function test_admin_with_school_widget_view_landing_tab_returns_ok(): void
    {
        $this->asAdmin();

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
}
