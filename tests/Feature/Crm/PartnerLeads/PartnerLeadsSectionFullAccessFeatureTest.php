<?php

namespace Tests\Feature\Crm\PartnerLeads;

use App\Models\PartnerLead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к разделу «Лиды» (/admin/partner-leads) и всем связанным endpoint'ам:
 * при наличии partnerLeads.view — 200, иначе 403.
 */
final class PartnerLeadsSectionFullAccessFeatureTest extends CrmTestCase
{
    private PartnerLead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->lead = PartnerLead::create([
            'name'   => 'Секция доступ',
            'phone'  => '+7 900 100-00-01',
            'status' => 'new',
        ]);
    }

    public function test_guest_cannot_access_any_section_endpoint(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_partner_leads_view_gets_403_on_all_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без partnerLeads.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_partner_leads_view_page_and_all_api_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPartnerLeadsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'leads');

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С partnerLeads.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_superadmin_all_section_endpoints_return_200(): void
    {
        $this->asSuperadmin();

        $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'leads');

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Суперадмин: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_only_partner_view_cannot_open_leads_tab(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPartnerView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.partner-leads'))->assertForbidden();
        $this->getJson(route('admin.partner-leads.data', ['draw' => 1]))->assertForbidden();
    }

    public function test_datatable_filter_query_variants_return_200(): void
    {
        $this->asSuperadmin();

        PartnerLead::create([
            'name'   => 'Filter smoke',
            'phone'  => '+7 900 555-55-55',
            'status' => 'new',
        ]);

        $queries = [
            ['draw' => 1, 'start' => 0, 'length' => 10, 'statuses' => ['new', 'processing']],
            ['draw' => 1, 'start' => 0, 'length' => 10, 'statuses' => ['sale']],
            ['draw' => 1, 'start' => 0, 'length' => 10, 'statuses' => ['rejected', 'spam']],
            ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => 'Filter']],
        ];

        foreach ($queries as $params) {
            $this->getJson(route('admin.partner-leads.data', $params))->assertOk();
        }
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(): array
    {
        $deleteLead = PartnerLead::create([
            'name'   => 'Удалить секция',
            'phone'  => '+7 900 100-00-99',
            'status' => 'new',
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.partner-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.partner-leads.data', [
                    'draw'     => 1,
                    'start'    => 0,
                    'length'   => 10,
                    'statuses' => ['new', 'processing'],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.partner-leads.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.partner-leads.columns-settings.save'),
                'data'   => [
                    'columns' => [
                        'name'    => true,
                        'phone'   => true,
                        'email'   => true,
                        'status'  => true,
                        'actions' => true,
                    ],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.partner-leads.update', ['partnerLead' => $this->lead->id]),
                'data'   => [
                    'status'  => 'processing',
                    'comment' => 'Section smoke',
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.partner-leads.destroy', ['partnerLead' => $deleteLead->id]),
            ],
        ];
    }

    private function grantPartnerLeadsView(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('partnerLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantPartnerView(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
