<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к разделу «Заявки с сайта»: middleware can:schoolLeads.view и ответы endpoint’ов.
 */
class SchoolLeadsAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Тест',
            'phone'      => '+7 900 111-11-11',
            'status'     => 'new',
        ]);
    }

    public function test_guest_cannot_access_school_leads_routes(): void
    {
        Auth::logout();

        $routes = [
            ['GET', route('admin.school-leads')],
            ['GET', route('admin.school-leads.data', ['draw' => 1, 'start' => 0, 'length' => 10])],
            ['PUT', route('admin.school-leads.update', ['schoolLead' => $this->lead->id])],
            ['DELETE', route('admin.school-leads.destroy', ['schoolLead' => $this->lead->id])],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->call($method, $url);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: ожидался 302/401/403/419 на {$method} {$url}, получен {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_leads_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('admin.school-leads'))->assertForbidden();
        $this->getJson(route('admin.school-leads.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertForbidden();
        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'status' => 'processing',
        ])->assertForbidden();
        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $this->lead->id]))
            ->assertForbidden();
    }

    public function test_admin_with_school_leads_view_all_endpoints_return_ok(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('Заявки с сайта', false);

        $data = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));
        $data->assertOk();
        $data->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'status'  => 'processing',
            'comment' => 'OK',
        ])->assertOk();

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $this->lead->id]))
            ->assertOk();
    }
}
