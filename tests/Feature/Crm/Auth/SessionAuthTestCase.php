<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Общие хелперы /login и /broadcasting/auth (сессия 30 дней, remember, HTML 403).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class SessionAuthTestCase extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // .env.testing держит CACHE_DRIVER=file, phpunit.xml без force —
        // throttle логина пишет FileStore и падает fopen, если каталог не writable (dev vs prukon).
        config([
            'cache.default' => 'array',
            'cache.limiter' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        $this->app->forgetInstance(RateLimiter::class);
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    protected function loginUrl(): string
    {
        return route('login');
    }

    protected function broadcastingAuthUrl(): string
    {
        return '/broadcasting/auth';
    }

    /**
     * @return array{channel_name: string, socket_id: string}
     */
    protected function broadcastingAuthPayload(?int $userId = null): array
    {
        return [
            'channel_name' => 'private-inbox.'.($userId ?? 1),
            'socket_id' => '1.1',
        ];
    }

    protected function enableBroadcastAuthDriver(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
    }

    protected function actingAsGuest(): self
    {
        Auth::logout();

        return $this;
    }

    protected function assertNotServerError(TestResponse $response, string $context): void
    {
        $this->assertNotSame(500, $response->getStatusCode(), $context.': не 500, получено '.$response->getStatusCode());
    }

    protected function assertNotEmptyOk(TestResponse $response, string $context): void
    {
        $this->assertNotSame(200, $response->getStatusCode(), $context.': не пустой/бессмысленный 200, получено '.$response->getStatusCode());
    }

    protected function assertDeniedWithoutServerError(TestResponse $response, string $context): void
    {
        $this->assertNotServerError($response, $context);
        $this->assertNotEmptyOk($response, $context);
        $this->assertTrue(
            $response->isRedirect() || in_array($response->getStatusCode(), [401, 403, 404, 405, 419, 422], true),
            $context.': отказ (redirect/401/403/404/405/419/422), получено '.$response->getStatusCode()
        );
    }

    protected function assertFailedLoginFieldError(TestResponse $response, string $field, string $message): void
    {
        $this->assertNotServerError($response, 'неуспешный вход '.$field);
        $this->assertNotEmptyOk($response, 'неуспешный вход '.$field);
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 422,
            'неуспешный вход: 302 или 422, получено '.$response->getStatusCode()
        );

        if ($response->status() === 422) {
            $response->assertJsonValidationErrors($field);
            $this->assertSame($message, $response->json('errors.'.$field.'.0'));
        } else {
            $response->assertSessionHasErrors($field);
            $this->assertSame(
                $message,
                session('errors')->first($field)
            );
        }
    }
}
