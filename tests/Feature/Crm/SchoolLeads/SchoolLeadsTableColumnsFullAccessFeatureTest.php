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
 * Контроль доступа к таблице «Заявки» после удаления колонки «Действия» и DELETE заявки:
 * гость / без права / с schoolLeads.view; DELETE URL → 405 (не 500, запись не удаляется).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SchoolLeadsTableColumnsFeatureTest
 */
final class SchoolLeadsTableColumnsFullAccessFeatureTest extends CrmTestCase
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
            'name'                  => 'Columns access lead',
            'phone'                 => '+7 900 840-40-01',
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function coreRoutesPayload(): array
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
                'method' => 'GET',
                'url'    => route('admin.school-leads.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.columns-settings.save'),
                'data'   => [
                    'columns' => [
                        'child_full_name' => true,
                        'name'            => true,
                        'status'          => true,
                        'phone'           => false,
                    ],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.update', ['schoolLead' => $this->lead->id]),
                'data'   => [
                    'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                    'comment'               => 'columns access smoke',
                ],
            ],
        ];
    }

    public function test_guest_is_denied_on_core_leads_endpoints(): void
    {
        Auth::logout();

        foreach ($this->coreRoutesPayload() as $item) {
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
                [302, 401, 403],
                "Гость: ожидался 302/401/403 на {$item['method']} {$item['url']}, получен {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_school_leads_view_gets_403_on_core_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->coreRoutesPayload() as $item) {
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

    public function test_viewer_with_school_leads_view_core_endpoints_return_200_without_actions_column(): void
    {
        $this->actingAsViewer();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertDontSee("key: 'actions'", false)
            ->assertDontSee('delete-lead', false)
            ->assertDontSee('deleteLeadModal', false)
            ->assertSee("linkClass: 'edit-lead'", false);

        foreach ($this->coreRoutesPayload() as $item) {
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
            $this->assertNotSame('', trim((string) $response->getContent()), 'Не допускаем пустой 200');
        }

        $this->assertSame(
            $this->schoolLeadProcessingStatusId(),
            (int) $this->lead->fresh()->school_lead_status_id
        );
    }

    public function test_admin_page_shows_new_column_order_and_endpoints_return_200(): void
    {
        $this->asAdmin();

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee("key: 'contract'", false)
            ->assertDontSee("key: 'actions'", false)
            ->getContent();

        $childPos = strpos($html, "key: 'child_full_name'");
        $namePos = strpos($html, "key: 'name'");
        $statusPos = strpos($html, "key: 'status'");
        $locationPos = strpos($html, "key: 'location'");
        $teamPos = strpos($html, "key: 'team_title'");
        $phonePos = strpos($html, "key: 'phone'");
        $contractPos = strpos($html, "key: 'contract'");

        $this->assertNotFalse($childPos);
        $this->assertNotFalse($namePos);
        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($locationPos);
        $this->assertNotFalse($teamPos);
        $this->assertNotFalse($phonePos);
        $this->assertNotFalse($contractPos);
        $this->assertLessThan($namePos, $childPos);
        $this->assertLessThan($statusPos, $namePos);
        $this->assertLessThan($locationPos, $statusPos);
        $this->assertLessThan($teamPos, $locationPos);
        $this->assertLessThan($phonePos, $teamPos);
        $this->assertLessThan($contractPos, $phonePos);

        foreach ($this->coreRoutesPayload() as $item) {
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
            $this->assertNotSame('', trim((string) $response->getContent()), 'Не допускаем пустой 200');
        }
    }

    public function test_admin_delete_lead_url_returns_405_not_500(): void
    {
        $this->asAdmin();

        $target = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Delete 405 target',
            'phone'                 => '+7 900 840-40-99',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->deleteJson('/admin/school-leads/' . $target->id);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNull($target->fresh()->deleted_at);
    }

    public function test_viewer_delete_lead_url_returns_405_not_500(): void
    {
        $this->actingAsViewer();

        $response = $this->deleteJson('/admin/school-leads/' . $this->lead->id);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNull($this->lead->fresh()->deleted_at);
    }

    public function test_guest_delete_lead_url_returns_405_not_500(): void
    {
        Auth::logout();

        $response = $this->deleteJson('/admin/school-leads/' . $this->lead->id);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNull($this->lead->fresh()->deleted_at);
    }

    public function test_guest_is_denied_on_columns_settings_get_and_save(): void
    {
        Auth::logout();

        $get = $this->getJson(route('admin.school-leads.columns-settings.get'));
        $this->assertContains($get->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $get->getStatusCode());

        $post = $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => ['name' => true],
        ]);
        $this->assertContains($post->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $post->getStatusCode());
        $this->assertNotSame(200, $post->getStatusCode());
    }

    public function test_user_without_school_leads_view_gets_403_on_columns_settings(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.school-leads.columns-settings.get'))->assertForbidden();
        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertForbidden();
    }

    public function test_unsupported_methods_on_columns_settings_do_not_return_500_or_empty_200(): void
    {
        $this->asAdmin();

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->json($method, route('admin.school-leads.columns-settings.save'), [
                'columns' => ['name' => true],
            ]);

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                "{$method} columns-settings.save не должен отдавать 500"
            );
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame('', trim((string) $response->getContent()));
            }
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405],
                "{$method} columns-settings.save: ожидался 404/405, получен {$response->getStatusCode()}"
            );
        }
    }
}
