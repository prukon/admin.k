<?php

namespace Tests\Feature\Crm\Partners;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI раздела «Партнеры»: вкладки «Партнеры», «Лиды», «Выплаты T‑Bank».
 */
final class PartnersSectionTabsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_partners_tab_renders_index_with_active_tab_partners(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->grantPermission($actor, 'partner.view');
        $this->actingAs($actor);

        $this->get(route('admin.partner.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'partners');
    }

    public function test_leads_tab_renders_index_with_active_tab_leads(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPermission($actor, 'partnerLeads.view');
        $this->actingAs($actor);

        $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'leads');
    }

    public function test_payouts_tab_renders_index_with_active_tab_payouts(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermission($actor, 'tbank.payouts.manage');
        $this->actingAs($actor);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'payouts');
    }

    public function test_user_with_all_permissions_sees_all_three_tabs(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->grantPermission($actor, 'partner.view');
        $this->grantPermission($actor, 'partnerLeads.view');
        $this->grantPermission($actor, 'tbank.payouts.manage');
        $this->actingAs($actor);

        $html = $this->get(route('admin.partner.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('partnersSectionTabs', $html);
        $this->assertStringContainsString('>Партнеры</a>', $html);
        $this->assertStringContainsString('>Лиды</a>', $html);
        $this->assertStringContainsString('>Выплаты T‑Bank</a>', $html);
        $this->assertStringContainsString(route('admin.partner.index'), $html);
        $this->assertStringContainsString(route('admin.partner-leads'), $html);
        $this->assertStringContainsString(route('admin.tinkoff.payouts.index'), $html);
    }

    public function test_user_with_only_payouts_manage_sees_only_payouts_tab(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermission($actor, 'tbank.payouts.manage');
        $this->actingAs($actor);

        $html = $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Выплаты T‑Bank</a>', $html);
        $this->assertStringNotContainsString('>Партнеры</a>', $html);
        $this->assertStringNotContainsString('>Лиды</a>', $html);
        $this->assertStringContainsString('id="payouts-table"', $html);
        $this->assertStringNotContainsString('id="partners-table"', $html);
        $this->assertStringNotContainsString('id="leads-table"', $html);
    }

    public function test_user_with_only_partner_view_does_not_see_payouts_tab(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->grantPermission($actor, 'partner.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.partner.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Партнеры</a>', $html);
        $this->assertStringNotContainsString('>Выплаты T‑Bank</a>', $html);
        $this->assertStringNotContainsString(route('admin.tinkoff.payouts.index'), $html);
    }

    public function test_user_with_only_partner_leads_view_does_not_see_payouts_tab(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPermission($actor, 'partnerLeads.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Лиды</a>', $html);
        $this->assertStringNotContainsString('>Выплаты T‑Bank</a>', $html);
    }

    public function test_payouts_tab_shows_toolbar_and_hides_other_tab_content(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermission($actor, 'tbank.payouts.manage');
        $this->actingAs($actor);

        $html = $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('nav-link active', $html);
        $this->assertStringContainsString('>Партнеры</h4>', $html);
        $this->assertStringContainsString('id="tbankPayoutsToolbarTotals"', $html);
        $this->assertStringContainsString('id="tbankPayoutsFiltersCollapse"', $html);
        $this->assertStringContainsString('id="tbank-payouts-filters"', $html);
        $this->assertStringContainsString('id="payouts-table"', $html);
        $this->assertStringContainsString('serverSide: true', $html);
        $this->assertStringNotContainsString('id="partners-table"', $html);
        $this->assertStringNotContainsString('id="leads-table"', $html);
    }

    public function test_partners_tab_marks_partners_link_as_active(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->grantPermission($actor, 'partner.view');
        $this->grantPermission($actor, 'tbank.payouts.manage');
        $this->actingAs($actor);

        $html = $this->get(route('admin.partner.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.partner.index'), $html);
        $this->assertStringContainsString('nav-link active', $html);
        $this->assertStringNotContainsString(
            route('admin.tinkoff.payouts.index') . '" class="nav-link active',
            $html
        );
    }

    public function test_payouts_tab_marks_payouts_link_as_active(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermission($actor, 'tbank.payouts.manage');
        $this->actingAs($actor);

        $html = $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.tinkoff.payouts.index'), $html);
        $this->assertStringContainsString('nav-link active', $html);
    }

    private function grantPermission(User $actor, string $permissionName): void
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
