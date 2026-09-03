<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Доступ к персональному «Показать N» на /admin/teams: гость / без права / viewer / admin;
 * PUT/PATCH/DELETE не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see TeamsPageLengthFeatureTest
 */
final class TeamsPageLengthFullAccessFeatureTest extends CrmTestCase
{
    private string $settingsUrl = '/admin/teams/columns-settings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    private function grantGroupsView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function pageLengthRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'url'    => route('admin.team.index'),
            ],
            [
                'method' => 'GET',
                'url'    => $this->settingsUrl,
            ],
            [
                'method' => 'POST',
                'url'    => $this->settingsUrl,
                'data'   => ['page_length' => 20],
            ],
        ];
    }

    public function test_guest_is_denied_on_page_length_endpoints_without_500(): void
    {
        Auth::logout();

        foreach ($this->pageLengthRoutes() as $item) {
            $response = $this->call($item['method'], $item['url'], $item['data'] ?? []);
            $this->assertNotSame(500, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertNotSame(200, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        }

        $this->assertSame(
            0,
            UserTableSetting::where('table_key', 'teams_index')->count()
        );
    }

    public function test_user_without_groups_view_gets_403_on_page_length_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->pageLengthRoutes() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $response->assertForbidden();
        }
    }

    public function test_viewer_with_groups_view_can_save_show_by_and_sees_it_after_reload(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantGroupsView($actor);

        $this->postJson($this->settingsUrl, [
            'page_length' => 50,
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewHas('teamsPageLength', 50)
            ->getContent();

        $this->assertStringContainsString('persistPageLength: true', $html);
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $html);

        $this->getJson($this->settingsUrl)
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_admin_can_save_show_by_via_ajax_and_non_ajax(): void
    {
        $this->asAdmin();

        $this->postJson($this->settingsUrl, [
            'page_length' => 20,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $nonAjax = $this->from(route('admin.team.index'))
            ->post($this->settingsUrl, [
                'page_length' => 100,
            ]);

        $this->assertNotSame(500, $nonAjax->getStatusCode());
        $this->assertSame(200, $nonAjax->getStatusCode());
        $this->assertNotSame('', trim((string) $nonAjax->getContent()));
        $nonAjax->assertJson(['success' => true]);

        $this->assertSame(
            100,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'teams_index')
                ->value('page_length')
        );
    }

    public function test_unsupported_methods_on_columns_settings_do_not_save_page_length(): void
    {
        $this->asAdmin();

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->json($method, $this->settingsUrl, [
                'page_length' => 50,
            ]);

            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertContains($response->getStatusCode(), [404, 405], $method);
        }

        $this->assertSame(
            0,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'teams_index')
                ->count()
        );
    }
}
