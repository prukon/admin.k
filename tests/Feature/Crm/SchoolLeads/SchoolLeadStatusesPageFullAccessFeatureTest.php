<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: страница заявок и все endpoint'ы статусов —
 * 200 при schoolLeads.view, отказ для гостя и без права.
 */
final class SchoolLeadStatusesPageFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Status access',
            'phone'                 => '+7 900 555-55-55',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
    }

    private function grantSchoolLeadsView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function actingAsViewer(): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        return $actor;
    }

    public function test_guest_is_denied_on_status_and_leads_page_endpoints(): void
    {
        Auth::logout();

        foreach ($this->allRoutesPayload() as $item) {
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

    public function test_user_without_permission_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->allRoutesPayload() as $item) {
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
                "Без schoolLeads.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_school_leads_view_all_endpoints_return_200(): void
    {
        $this->actingAsViewer();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertSee('id="schoolLeadStatusesModal"', false)
            ->assertSee('schoolLeadStatusRoutes', false);

        foreach ($this->allRoutesPayload() as $item) {
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
                "Viewer: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_all_status_and_leads_endpoints_return_200(): void
    {
        $this->asAdmin();

        foreach ($this->allRoutesPayload() as $item) {
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
                "Админ: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->get(route('admin.school-leads'))->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allRoutesPayload(): array
    {
        $deleteLead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Delete status access',
            'phone'                 => '+7 900 666-66-66',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        return array_merge(
            [
                [
                    'method'  => 'GET',
                    'url'     => route('admin.school-leads'),
                    'headers' => ['HTTP_ACCEPT' => 'text/html'],
                ],
                [
                    'method' => 'GET',
                    'url'    => route('admin.school-leads.data', [
                        'draw'       => 1,
                        'start'      => 0,
                        'length'     => 10,
                        'status_ids' => [$this->schoolLeadSystemStatusId()],
                    ]),
                ],
                [
                    'method' => 'GET',
                    'url'    => route('admin.school-leads.columns-settings.get'),
                ],
                [
                    'method' => 'POST',
                    'url'    => route('admin.school-leads.columns-settings.save'),
                    'data'   => ['columns' => ['name' => true, 'status' => true]],
                ],
                [
                    'method' => 'PUT',
                    'url'    => route('admin.school-leads.update', ['schoolLead' => $this->lead->id]),
                    'data'   => [
                        'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                        'comment'               => 'Status access smoke',
                    ],
                ],
                [
                    'method' => 'DELETE',
                    'url'    => route('admin.school-leads.destroy', ['schoolLead' => $deleteLead->id]),
                ],
            ],
            $this->schoolLeadStatusManagementRoutesPayload()
        );
    }
}
