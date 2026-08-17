<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * /admin/users не использует табличный прелоадер: общий компонент ломал AdminLTE
 * (страница «размазана» на всю ширину) и давал вечный спиннер (обёртка create()
 * на parse-time Vite-модуля type=module).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalTablePreloaderFeatureTest
 */
final class AdminUsersTablePreloaderRegressionFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_open_users_list(): void
    {
        Auth::logout();

        $web = $this->get(route('admin.user1'));
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $web->assertStatus(302);

        $json = $this->getJson(route('admin.user1'));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(401);
    }

    public function test_manager_without_users_view_gets_403_on_users_list(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $web = $this->actingAs($actor)->withSession($session)
            ->get(route('admin.user1'));
        $this->assertNotSame(500, $web->getStatusCode());
        $web->assertStatus(403);
    }

    public function test_viewer_with_users_view_sees_plain_table_without_preloader_stage(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantUsersView($actor);

        $page = $this->get(route('admin.user1'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertUsersListKeepsAdminLayoutWithoutPreloader($html);
    }

    public function test_admin_users_list_keeps_toolbar_beside_title_without_wrapping_page_in_stage(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $page = $this->get(route('admin.user1'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertUsersListKeepsAdminLayoutWithoutPreloader($html);

        $this->assertStringContainsString('justify-content-between', $html);
        $this->assertStringContainsString('class="wrapper"', $html);
        $this->assertStringContainsString('content-wrapper', $html);
        $this->assertStringContainsString('KidsCrmDataTable.create', $html);
    }

    public function test_ajax_get_users_list_is_html_without_preloader_not_empty_json(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $page = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('admin.user1'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertUsersListKeepsAdminLayoutWithoutPreloader($html);
    }

    public function test_unsupported_methods_on_users_list_are_not_server_errors(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        foreach (['patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('admin.user1'));
            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertContains($response->getStatusCode(), [404, 405], $method);
        }
    }

    private function grantUsersView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertUsersListKeepsAdminLayoutWithoutPreloader(string $html): void
    {
        $this->assertStringContainsString('id="users-table"', $html);
        $this->assertStringContainsString('table-responsive', $html);
        $this->assertStringContainsString('payments-report-toolbar', $html);
        $this->assertStringContainsString('payments-report-title', $html);
        $this->assertStringNotContainsString('kids-table-preloader', $html);
        $this->assertStringNotContainsString('users-table-stage', $html);
        $this->assertStringNotContainsString('schedule-journal-stage', $html);
        $this->assertStringNotContainsString('KidsCrmTablePreloader', $html);
        $this->assertStringNotContainsString('contain: inline-size', $html);
        $this->assertStringNotContainsString('#users-table-stage:not(.is-ready)', $html);

        $toolbarPos = strpos($html, 'payments-report-toolbar');
        $tablePos = strpos($html, 'id="users-table"');
        $this->assertNotFalse($toolbarPos);
        $this->assertNotFalse($tablePos);
        $this->assertLessThan(
            $tablePos,
            $toolbarPos,
            'тулбар не должен оказаться внутри скрытого table-stage'
        );
    }
}
