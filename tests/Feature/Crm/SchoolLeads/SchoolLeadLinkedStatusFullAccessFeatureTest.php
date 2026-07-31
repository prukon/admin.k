<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: смена статуса у лида с клиентом и связанные endpoint'ы раздела.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadLinkedStatusFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $linkedLead;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) Role::query()->where('name', 'user')->value('id'),
            'is_enabled' => 1,
        ]);

        $this->linkedLead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Linked access lead',
            'phone'                 => '+7 900 700-70-70',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $student->id,
            'child_lastname'        => 'Доступов',
            'child_firstname'       => 'Линк',
        ]);
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

    private function actingAsSchoolLeadsViewer(): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');

        return $actor;
    }

    public function test_guest_is_denied_on_linked_status_endpoints(): void
    {
        Auth::logout();

        foreach ($this->routesPayload() as $item) {
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

    public function test_user_without_school_leads_view_gets_403_on_linked_status_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->routesPayload() as $item) {
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

    public function test_viewer_with_school_leads_view_gets_200_on_linked_status_workflow(): void
    {
        $this->actingAsSchoolLeadsViewer();

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertSee('id="editLeadModal"', false)
            ->assertSee('id="leadModalStatusPicker"', false)
            ->assertSee('saveLeadStatusInline', false);
        $this->assertNotSame('', trim((string) $page->getContent()));

        foreach ($this->routesPayload() as $item) {
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
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_admin_linked_status_endpoints_return_200_not_empty(): void
    {
        $this->asAdmin();

        foreach ($this->routesPayload() as $item) {
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
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function routesPayload(): array
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Access linked status']);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.statuses.index'),
            ],
            [
                'method'  => 'PUT',
                'url'     => route('admin.school-leads.update', ['schoolLead' => $this->linkedLead->id]),
                'data'    => [
                    'school_lead_status_id' => $status->id,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.school-leads.statuses.store'),
                'data'    => [
                    'name'                 => 'Access status ' . uniqid('', true),
                    'color'                => '#0d6efd',
                    'sort_order'           => 40,
                    'is_default_in_filter' => false,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
        ];
    }
}
