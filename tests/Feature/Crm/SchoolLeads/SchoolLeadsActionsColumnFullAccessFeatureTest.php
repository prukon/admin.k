<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: вкладка «Заявки» и столбец «Действия» (скрытие редактирования после создания клиента).
 * Страница и все endpoint'ы → 200 при schoolLeads.view; отказ для гостя и без права.
 */
final class SchoolLeadsActionsColumnFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $leadWithoutClient;

    private SchoolLead $leadWithClient;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->leadWithoutClient = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Access без клиента',
            'phone'                 => '+7 900 820-20-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $client = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'name'       => 'Access',
            'lastname'   => 'Client',
        ]);

        $this->leadWithClient = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Access с клиентом',
            'phone'                 => '+7 900 820-20-02',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $client->id,
            'child_lastname'        => 'Client',
            'child_firstname'       => 'Access',
        ]);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
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

    public function test_guest_is_denied_on_all_leads_page_endpoints(): void
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

    public function test_user_without_permission_gets_403_on_all_endpoints(): void
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

    public function test_viewer_with_school_leads_view_all_endpoints_return_200(): void
    {
        $this->actingAsViewer();

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertSee("key: 'actions'", false)
            ->assertSee('if (!row.user_id)', false)
            ->assertSee('delete-lead', false)
            ->getContent();

        $actionsColumnPos = strpos($html, "key: 'actions'");
        $this->assertNotFalse($actionsColumnPos);
        $this->assertStringContainsString(
            'if (!row.user_id)',
            substr($html, $actionsColumnPos, 900)
        );

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
        }
    }

    public function test_viewer_datatable_returns_user_id_for_leads_with_and_without_client(): void
    {
        $this->actingAsViewer();

        $rows = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))
            ->assertOk()
            ->json('data');

        $withoutClient = collect($rows)->firstWhere('id', $this->leadWithoutClient->id);
        $withClient = collect($rows)->firstWhere('id', $this->leadWithClient->id);

        $this->assertNotNull($withoutClient);
        $this->assertNotNull($withClient);
        $this->assertNull($withoutClient['user_id']);
        $this->assertSame((int) $this->leadWithClient->user_id, (int) $withClient['user_id']);
    }

    public function test_admin_all_leads_page_endpoints_return_200(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee("key: 'actions'", false)
            ->assertSee('if (!row.user_id)', false)
            ->assertSee('edit-lead', false)
            ->assertSee('delete-lead', false);

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
        }
    }

    public function test_admin_datatable_with_client_contract_states_returns_200(): void
    {
        $this->asAdmin();

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/access-contract.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'status'          => Contract::STATUS_DRAFT,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Access admin contract',
            'phone'                 => '+7 900 820-20-03',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'user_id'               => $user->id,
        ]);

        $rows = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))
            ->assertOk()
            ->json('data');

        $row = collect($rows)->firstWhere('name', 'Access admin contract');

        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row['user_id']);
        $this->assertArrayHasKey('latest_contract', $row);
        $this->assertArrayNotHasKey('create_contract_url', $row);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function routesPayload(): array
    {
        $deleteLead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Access delete target',
            'phone'                 => '+7 900 820-20-99',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        return array_merge($this->readOnlyRoutesPayload($location, $team), [
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.columns-settings.save'),
                'data'   => [
                    'columns' => [
                        'name'     => true,
                        'phone'    => true,
                        'status'   => true,
                        'contract' => true,
                        'actions'  => true,
                    ],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.update', ['schoolLead' => $this->leadWithoutClient->id]),
                'data'   => [
                    'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                    'comment'               => 'actions access smoke',
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.school-leads.destroy', ['schoolLead' => $deleteLead->id]),
            ],
        ], $this->schoolLeadStatusManagementRoutesPayload());
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function readOnlyRoutesPayload(Location $location, Team $team): array
    {
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
                    'length' => 50,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'       => 1,
                    'start'      => 0,
                    'length'     => 50,
                    'status_ids' => [
                        $this->schoolLeadSystemStatusId(),
                        $this->schoolLeadProcessingStatusId(),
                    ],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'                   => 1,
                    'start'                  => 0,
                    'length'                 => 50,
                    'status_ids'             => [$this->schoolLeadSystemStatusId()],
                    'location_id'            => (string) $location->id,
                    'team_id'                => (string) $team->id,
                    'has_special_conditions' => '1',
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.columns-settings.get'),
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.school-lead', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.statuses.index'),
            ],
        ];
    }
}
