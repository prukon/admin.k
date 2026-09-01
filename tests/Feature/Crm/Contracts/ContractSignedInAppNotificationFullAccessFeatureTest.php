<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Mockery;

/**
 * Полный доступ / отказ к sync, вебхуку и поверхностям колокольчика после signed.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSignedInAppNotificationFullAccessFeatureTest extends ContractSignedInAppNotificationTestCase
{
    public function test_guest_is_denied_on_status_inbox_and_show_but_signed_webhook_is_public(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        Auth::logout();

        foreach ([
            ['GET', route('contracts.status', $contract)],
            ['GET', route('inAppNotifications.index')],
            ['GET', route('inAppNotifications.bell')],
            ['GET', route('contracts.show', $contract)],
        ] as [$method, $url]) {
            $json = $this->getJson($url);
            $this->assertContains($json->getStatusCode(), [401, 302, 403, 404, 419], 'JSON '.$url);
            $this->assertNotSame(200, $json->getStatusCode(), 'JSON '.$url);
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON '.$url);

            $web = $this->from(route('login'))->get($url);
            $this->assertContains($web->getStatusCode(), [401, 302, 403, 404, 419], 'WEB '.$url);
            $this->assertNotSame(200, $web->getStatusCode(), 'WEB '.$url);
            $this->assertNotSame(500, $web->getStatusCode(), 'WEB '.$url);
        }

        $this->assertSame(0, $this->eventNotificationCount());

        $this->postSignedWebhook($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertSame(1, $this->eventNotificationCount());
    }

    public function test_admin_with_view_and_sync_gets_200_on_show_status_inbox_bell_and_dashboard(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        foreach ([
            route('contracts.show', $contract),
            route('inAppNotifications.index'),
            route('dashboard'),
        ] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertNotSame('', trim((string) $response->getContent()), $url);
        }

        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('items.0.title', 'Договор подписан');

        $this->mockStatusProvider(['status' => 30, 'status_text' => 'Подписан']);
        $already = $this->getJson(route('contracts.status', $contract));
        $this->assertNotSame(500, $already->getStatusCode());
        $already->assertOk();
    }

    public function test_admin_without_sync_opens_show_without_sync_button_and_status_is_403(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->actingWith2fa($admin);
        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="syncStatusBtn"', $html);
        $this->assertStringNotContainsString('Синхронизировать с Подпислон', $html);

        $this->getJson(route('contracts.status', $contract))->assertForbidden();
        $this->from(route('contracts.show', $contract))
            ->get(route('contracts.status', $contract))
            ->assertForbidden();

        $this->assertSame(0, $this->eventNotificationCount());
    }
}
