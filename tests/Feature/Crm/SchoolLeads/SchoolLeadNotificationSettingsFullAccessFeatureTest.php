<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Доступ к настройкам email-уведомлений заявок: гость / без права / viewer / admin;
 * чужие методы и отсутствие организации — не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SchoolLeadNotificationSettingsFeatureTest
 */
final class SchoolLeadNotificationSettingsFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function notificationRoutes(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.notifications.show'),
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.notifications.update'),
                'data'   => [
                    'emails'                       => ['full-access@example.test'],
                    'email_notifications_disabled' => false,
                ],
            ],
        ];
    }

    public function test_guest_is_denied_on_notification_endpoints_without_500(): void
    {
        Auth::logout();

        foreach ($this->notificationRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertNotSame(500, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertNotSame(200, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->partner->refresh();
        $this->assertNull($this->partner->school_leads_notification_emails);
    }

    public function test_user_without_school_leads_view_gets_403_on_notification_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->notificationRoutes() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $response->assertForbidden();
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_viewer_with_school_leads_view_can_open_page_and_save_notifications(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="schoolLeadNotificationsModal"', false)
            ->assertSee('data-bs-target="#schoolLeadNotificationsModal"', false)
            ->assertSee('>Уведомления</span>', false);

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonStructure([
                'emails',
                'emails_configured',
                'email_notifications_disabled',
                'suggested_emails',
            ]);

        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['viewer-leads@example.test'],
            'email_notifications_disabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('emails', ['viewer-leads@example.test']);

        $this->partner->refresh();
        $this->assertSame(['viewer-leads@example.test'], $this->partner->school_leads_notification_emails);
    }

    public function test_admin_notification_endpoints_return_200(): void
    {
        $this->asAdmin();

        foreach ($this->notificationRoutes() as $item) {
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

    public function test_admin_without_organization_is_logged_out_with_email_error(): void
    {
        $this->asAdmin();
        $this->user->partner_id = null;
        $this->user->save();
        $this->actingAs($this->user);
        $this->withSession([
            'current_partner' => null,
            '2fa:passed'      => true,
        ]);

        $response = $this->from(route('login'))
            ->put(route('admin.school-leads.notifications.update'), [
                'emails'                       => ['no-org@example.test'],
                'email_notifications_disabled' => false,
            ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'Ваша организация недоступна.']);
    }

    public function test_non_superadmin_cannot_read_foreign_partner_notifications_via_session(): void
    {
        $this->asAdmin();
        $this->user->email = 'own-admin@example.test';
        $this->user->save();

        $this->partner->email = 'own-org@example.test';
        $this->partner->school_leads_notification_emails = ['own-list@example.test'];
        $this->partner->save();

        $this->foreignPartner->school_leads_notification_emails = ['foreign-list@example.test'];
        $this->foreignPartner->save();

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails', ['own-list@example.test'])
            ->assertJsonMissing(['emails' => ['foreign-list@example.test']]);
    }

    public function test_unsupported_methods_on_notifications_do_not_save_and_are_not_500(): void
    {
        $this->asAdmin();

        $showRoute = Route::getRoutes()->getByName('admin.school-leads.notifications.show');
        $updateRoute = Route::getRoutes()->getByName('admin.school-leads.notifications.update');
        $this->assertNotNull($showRoute);
        $this->assertNotNull($updateRoute);
        $this->assertContains('can:schoolLeads.view', $showRoute->gatherMiddleware());
        $this->assertContains('can:schoolLeads.view', $updateRoute->gatherMiddleware());

        $url = route('admin.school-leads.notifications.show');
        $this->assertSame($url, route('admin.school-leads.notifications.update'));

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $response = $this->json($method, $url, [
                'emails'                       => ['wrong-method@example.test'],
                'email_notifications_disabled' => false,
            ]);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' notifications');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' notifications');
            $this->assertContains($response->getStatusCode(), [404, 405], $method.' notifications');
        }

        $this->partner->refresh();
        $this->assertNull($this->partner->school_leads_notification_emails);
    }

    public function test_landing_and_widget_tabs_do_not_render_notifications_modal(): void
    {
        $this->asAdmin();
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('schoolLeadLanding.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('schoolWidget.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $landing = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="schoolLeadNotificationsModal"', $landing);
        $this->assertStringNotContainsString('data-bs-target="#schoolLeadNotificationsModal"', $landing);

        $widget = $this->get(route('admin.school-leads.widget'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="schoolLeadNotificationsModal"', $widget);
        $this->assertStringNotContainsString('data-bs-target="#schoolLeadNotificationsModal"', $widget);
        $this->assertStringContainsString('кнопкой «Уведомления»', $widget);
    }
}
