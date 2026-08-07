<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\SchoolLeadParentMatchConfirmation;
use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: автосопоставление родителя в заявках (модалка, DataTables, PUT).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadParentMatchFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $matchedLead;

    private ParentProfile $matchedParent;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->matchedParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Доступов',
            'firstname'  => 'Родитель',
            'email'      => 'access-parent@example.test',
            'phone'      => '79990001122',
        ]);

        $this->matchedLead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'name'                   => 'Access Match Lead',
            'phone'                  => '+7 999 000-11-22',
            'parent_lastname'        => 'Иванов',
            'parent_firstname'       => 'Иван',
            'parent_email'           => 'lead-access@example.test',
            'parent_phone'           => '+7 999 111-22-33',
            'parent_id'              => $this->matchedParent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
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

    public function test_guest_is_denied_on_parent_match_endpoints(): void
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

    public function test_user_without_school_leads_view_gets_403_on_parent_match_endpoints(): void
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

    public function test_viewer_with_school_leads_view_gets_200_on_parent_match_workflow(): void
    {
        $this->actingAsSchoolLeadsViewer();

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertSee('id="editLeadModal"', false)
            ->assertSee('id="leadParentMatchBanner"', false)
            ->assertSee('id="leadParentMatchAcceptBtn"', false)
            ->assertSee('acceptLeadParentMatch', false)
            ->assertSee('Выберите родителя', false);
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

    public function test_admin_parent_match_endpoints_return_200_not_empty(): void
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

    public function test_viewer_datatable_exposes_parent_match_fields_for_matched_lead(): void
    {
        $this->actingAsSchoolLeadsViewer();

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $this->matchedLead->id);
        $this->assertIsArray($row);
        $this->assertSame($this->matchedParent->id, $row['parent_id']);
        $this->assertSame('email', $row['parent_match_reason']);
        $this->assertTrue($row['parent_match_needs_decision']);
        $this->assertSame($this->matchedParent->id, $row['matched_parent']['id'] ?? null);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function routesPayload(): array
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
                    'length' => 10,
                ]),
            ],
            [
                'method'  => 'PUT',
                'url'     => route('admin.school-leads.update', ['schoolLead' => $this->matchedLead->id]),
                'data'    => [
                    'school_lead_status_id'  => $this->matchedLead->school_lead_status_id,
                    'parent_id'              => $this->matchedParent->id,
                    'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Accepted->value,
                    'parent_lastname'        => 'Иванов',
                    'parent_firstname'       => 'Иван',
                    'parent_email'           => 'lead-access@example.test',
                    'parent_phone'           => '+7 999 111-22-33',
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
        ];
    }
}
