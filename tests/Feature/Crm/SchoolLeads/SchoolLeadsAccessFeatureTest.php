<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function allSchoolLeadsRoutes(): array
    {
        $updateStatus = $this->createPartnerSchoolLeadStatus(['name' => 'Access update']);
        $deleteStatus = $this->createPartnerSchoolLeadStatus(['name' => 'Access delete']);

        return [
            ['GET', route('admin.school-leads')],
            ['GET', route('admin.school-leads.data', ['draw' => 1, 'start' => 0, 'length' => 10])],
            ['GET', route('admin.school-leads.statuses.index')],
            ['POST', route('admin.school-leads.statuses.store')],
            ['PUT', route('admin.school-leads.statuses.update', ['schoolLeadStatus' => $updateStatus->id])],
            ['DELETE', route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $deleteStatus->id])],
            ['GET', route('admin.school-leads.columns-settings.get')],
            ['POST', route('admin.school-leads.columns-settings.save')],
            ['PUT', route('admin.school-leads.update', ['schoolLead' => $this->lead->id])],
            ['DELETE', route('admin.school-leads.destroy', ['schoolLead' => $this->lead->id])],
        ];
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

    public function test_guest_cannot_access_school_leads_routes(): void
    {
        Auth::logout();

        foreach ($this->allSchoolLeadsRoutes() as [$method, $url]) {
            $data = match ($method) {
                'POST' => str_contains($url, 'statuses') ? [
                    'name'  => 'Гость',
                    'color' => '#0d6efd',
                ] : [
                    '_token'  => 'test',
                    'columns' => ['name' => true],
                ],
                'PUT' => str_contains($url, 'statuses') ? [
                    'name'  => 'Гость update',
                    'color' => '#ffc107',
                ] : [
                    'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                ],
                default => [],
            };

            $response = $this->call($method, $url, $data);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: ожидался 302/401/403/419 на {$method} {$url}, получен {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_leads_view_gets_403_on_all_routes(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.school-leads'))->assertForbidden();
        $this->getJson(route('admin.school-leads.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertForbidden();
        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertForbidden();
        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertForbidden();
        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
        ])->assertForbidden();
        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $this->lead->id]))
            ->assertForbidden();
    }

    public function test_user_with_school_leads_view_only_all_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Доступ',
            'phone'      => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('Заявки с сайта', false)
            ->assertSee('id="columnsDropdownSchoolLeads"', false)
            ->assertSee('id="schoolLeadsFiltersCollapse"', false);

        $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId()],
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'stats' => ['total', 'new'],
                'data',
            ]);

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJson([]);

        $this->getJson(route('admin.school-leads.statuses.index'))
            ->assertOk()
            ->assertJsonStructure(['statuses']);

        $this->postJson(route('admin.school-leads.statuses.store'), [
            'name'  => 'Доступный статус',
            'color' => '#198754',
        ])->assertOk();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'   => true,
                'phone'  => false,
                'status' => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJsonFragment(['name' => true, 'phone' => false]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'comment' => 'Проверка доступа',
        ])
            ->assertOk()
            ->assertJson([
                'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                'comment' => 'Проверка доступа',
            ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $lead->id]))
            ->assertOk()
            ->assertJson(['message' => 'Заявка удалена.']);
    }

    public function test_admin_with_school_leads_view_all_endpoints_return_ok(): void
    {
        $this->asAdmin();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Smoke-локация',
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Smoke',
            'phone'       => '+7 900 444-44-44',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $location->id,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('Заявки с сайта', false)
            ->assertSee('id="columnsDropdownSchoolLeads"', false)
            ->assertSee('data-column-key="location"', false)
            ->assertSee('Smoke-локация', false)
            ->assertSee('school-leads-stat-total', false);

        $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId()],
            'location_id' => (string) $location->id,
            'search'       => ['value' => 'Smoke'],
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'location_id',
                        'location_name',
                    ],
                ],
            ]);

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk();

        $this->getJson(route('admin.school-leads.statuses.index'))
            ->assertOk()
            ->assertJsonStructure(['statuses']);

        $this->postJson(route('admin.school-leads.statuses.store'), [
            'name'  => 'Smoke статус',
            'color' => '#dc3545',
        ])->assertOk();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'     => true,
                'location' => true,
                'utm'      => false,
            ],
        ])->assertOk();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'comment'     => 'OK',
            'location_id' => $location->id,
        ])
            ->assertOk()
            ->assertJson([
                'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                'comment'       => 'OK',
                'location_id'   => $location->id,
                'location_name' => 'Smoke-локация',
            ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $lead->id]))
            ->assertOk()
            ->assertJson(['message' => 'Заявка удалена.']);
    }

    public function test_datatable_with_location_sort_params_returns_ok(): void
    {
        $this->asAdmin();

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Альфа',
            'is_enabled' => true,
        ]);

        $columns = [
            ['data' => 'id'],
            ['data' => 'name'],
            ['data' => 'phone'],
            ['data' => 'location_name'],
            ['data' => 'utm_summary'],
            ['data' => 'page_url'],
            ['data' => 'status_label'],
            ['data' => 'comment'],
        ];

        $this->getJson(route('admin.school-leads.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'order'   => [['column' => 3, 'dir' => 'asc']],
            'columns' => $columns,
        ]))->assertOk();
    }
}
