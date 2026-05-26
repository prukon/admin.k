<?php

namespace Tests\Feature\Crm\PartnerLeads;

use App\Models\PartnerLead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Middleware can:partnerLeads.view и ответы endpoint'ов раздела «Лиды».
 */
class PartnerLeadsAccessFeatureTest extends CrmTestCase
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
            'name'   => 'Тест',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function protectedRouteNames(): array
    {
        return [
            'admin.partner-leads'                      => 'can:partnerLeads.view',
            'admin.partner-leads.data'                 => 'can:partnerLeads.view',
            'admin.partner-leads.columns-settings.get' => 'can:partnerLeads.view',
            'admin.partner-leads.columns-settings.save'=> 'can:partnerLeads.view',
            'admin.partner-leads.update'               => 'can:partnerLeads.view',
            'admin.partner-leads.destroy'              => 'can:partnerLeads.view',
        ];
    }

    public function test_protected_routes_have_partner_leads_view_middleware(): void
    {
        foreach ($this->protectedRouteNames() as $routeName => $expectedMiddleware) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");
            $this->assertTrue(
                in_array($expectedMiddleware, $route->gatherMiddleware(), true),
                "Маршрут {$routeName} должен быть защищён middleware {$expectedMiddleware}"
            );
        }
    }

    public function test_guest_cannot_access_partner_leads_routes(): void
    {
        Auth::logout();

        foreach ($this->httpRoutes() as [$method, $url, $data]) {
            $response = $this->call($method, $url, $data);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: ожидался 302/401/403/419 на {$method} {$url}, получен {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_partner_leads_view_gets_403_on_all_routes(): void
    {
        $denied = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.partner-leads'))->assertForbidden();
        $this->getJson(route('admin.partner-leads.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertForbidden();
        $this->getJson(route('admin.partner-leads.columns-settings.get'))
            ->assertForbidden();
        $this->postJson(route('admin.partner-leads.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertForbidden();
        $this->putJson(route('admin.partner-leads.update', ['partnerLead' => $this->lead->id]), [
            'status' => 'processing',
        ])->assertForbidden();
        $this->deleteJson(route('admin.partner-leads.destroy', ['partnerLead' => $this->lead->id]))
            ->assertForbidden();
    }

    public function test_user_with_partner_leads_view_only_all_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPartnerLeadsView($actor);
        $this->actingAs($actor);

        $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->assertViewHas('activeTab', 'leads');

        $this->getJson(route('admin.partner-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'statuses' => ['new', 'processing'],
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'stats', 'data']);

        $this->getJson(route('admin.partner-leads.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.partner-leads.columns-settings.save'), [
            'columns' => ['name' => true, 'phone' => true],
        ])->assertOk();

        $this->putJson(route('admin.partner-leads.update', ['partnerLead' => $this->lead->id]), [
            'status'  => 'processing',
            'comment' => 'Access test',
        ])->assertOk();

        $disposable = PartnerLead::create([
            'name'   => 'Удалить access',
            'phone'  => '+7 900 999-99-99',
            'status' => 'new',
        ]);

        $this->deleteJson(route('admin.partner-leads.destroy', ['partnerLead' => $disposable->id]))
            ->assertOk();
    }

    /**
     * @return list<array{0: string, 1: string, 2?: array<string, mixed>}>
     */
    private function httpRoutes(): array
    {
        return [
            ['GET', route('admin.partner-leads'), []],
            ['GET', route('admin.partner-leads.data', ['draw' => 1, 'start' => 0, 'length' => 10]), []],
            ['GET', route('admin.partner-leads.columns-settings.get'), []],
            ['POST', route('admin.partner-leads.columns-settings.save'), ['columns' => ['name' => true]]],
            ['PUT', route('admin.partner-leads.update', ['partnerLead' => $this->lead->id]), ['status' => 'processing']],
            ['DELETE', route('admin.partner-leads.destroy', ['partnerLead' => $this->lead->id]), []],
        ];
    }

    private function grantPartnerLeadsView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('partnerLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
