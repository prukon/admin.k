<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI раздела «Заявки с сайта»: вкладки «Заявки» и «Виджет для сайта».
 */
final class SchoolLeadsTabsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        config([
            'services.telegram.bot_token'    => 'test-token',
            'services.telegram.bot_username' => 'kidscrmLeadFormBot',
        ]);
    }

    public function test_leads_tab_renders_index_with_active_tab_leads(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'leads')
            ->assertViewHas('leadStats');
    }

    public function test_landing_tab_renders_index_with_active_tab_landing(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeadLanding.view');

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'landing')
            ->assertViewHas(['landingUrl', 'partner']);
    }

    public function test_widget_tab_renders_index_with_active_tab_widget(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-leads.widget'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'widget')
            ->assertViewHas(['widgetUrl', 'iframeCode', 'partner']);
    }

    public function test_leads_tab_shows_navigation_and_leads_toolbar(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeadLanding.view');

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('nav nav-tabs', $html);
        $this->assertStringContainsString('>Заявки</a>', $html);
        $this->assertStringContainsString('>Страница заявки</a>', $html);
        $this->assertStringContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringContainsString(route('admin.school-leads'), $html);
        $this->assertStringContainsString(route('admin.school-leads.landing'), $html);
        $this->assertStringContainsString(route('admin.school-leads.widget'), $html);
        $this->assertStringContainsString('nav-link active', $html);
        $this->assertStringContainsString('>Заявки с сайта</h4>', $html);
        $this->assertStringContainsString('id="schoolLeadsReportToolbar"', $html);
        $this->assertStringContainsString('payments-report-title', $html);
        $this->assertStringContainsString('Заявки', $html);
        $this->assertStringContainsString('id="leads-table"', $html);
        $this->assertStringNotContainsString('id="iframeCode"', $html);
    }

    public function test_widget_tab_shows_widget_content_and_telegram_controls(): void
    {
        $this->asAdmin();

        $html = $this->get(route('admin.school-leads.widget'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Заявки</a>', $html);
        $this->assertStringContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringContainsString('nav-link active', $html);
        $this->assertStringContainsString('id="iframeCode"', $html);
        $this->assertStringContainsString('id="widgetUrl"', $html);
        $this->assertStringContainsString('id="copyIframeBtn"', $html);
        $this->assertStringContainsString('id="connectTelegramBtn"', $html);
        $this->assertStringContainsString('Подключить Telegram', $html);
        $this->assertStringNotContainsString('id="leads-table"', $html);
        $this->assertStringNotContainsString('id="schoolLeadsReportToolbar"', $html);
    }

    public function test_user_without_school_lead_landing_view_does_not_see_landing_tab(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'schoolWidget.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Заявки</a>', $html);
        $this->assertStringNotContainsString('>Страница заявки</a>', $html);
        $this->assertStringContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringNotContainsString(route('admin.school-leads.landing'), $html);
        $this->assertStringContainsString(route('admin.school-leads.widget'), $html);
    }

    public function test_user_without_school_widget_view_does_not_see_widget_tab(): void
    {
        $actor = $this->createUserWithoutPermission('schoolWidget.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Заявки</a>', $html);
        $this->assertStringContainsString('>Страница заявки</a>', $html);
        $this->assertStringNotContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringContainsString(route('admin.school-leads.landing'), $html);
        $this->assertStringNotContainsString(route('admin.school-leads.widget'), $html);
    }

    public function test_legacy_school_widget_route_renders_widget_tab_in_shared_index(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-widget'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'widget')
            ->assertSee('id="iframeCode"', false);
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
