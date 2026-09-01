<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Общие хелперы публичной саморегистрации школы (/partner/register).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class PartnerSelfRegistrationTestCase extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'cache.default' => 'array',
            'cache.limiter' => 'array',
            'app.partner_self_registration_enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret' => 'test-secret',
            'services.recaptcha.min_score' => 0.5,
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        $this->app->forgetInstance(RateLimiter::class);
        Cache::flush();
        RateLimiterFacade::clear('partner-registration-ip:127.0.0.1');
        RateLimiterFacade::clear('partner-registration-ip|127.0.0.1');
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

    protected function registerUrl(): string
    {
        return route('partner.register');
    }

    protected function registerStoreUrl(): string
    {
        return route('partner.register.store');
    }

    protected function enablePartnerSelfRegistration(): void
    {
        config(['app.partner_self_registration_enabled' => true]);
    }

    protected function disablePartnerSelfRegistration(): void
    {
        config(['app.partner_self_registration_enabled' => false]);
    }

    protected function actingAsGuest(): self
    {
        Auth::logout();

        return $this;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validRegistrationPayload(array $overrides = []): array
    {
        $email = 'school-'.Str::lower(Str::random(10)).'@example.test';

        return array_merge([
            'school_title' => 'Футбольная школа Тест',
            'name' => 'Иван Админов',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'recaptcha_token' => 'fake-token',
        ], $overrides);
    }

    protected function fakeRecaptchaSuccess(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'partner_register',
            ], 200),
        ]);
    }

    protected function fakeRecaptchaWrongAction(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'homepage',
            ], 200),
        ]);
    }

    protected function fakeRecaptchaLowScore(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.1,
                'action' => 'partner_register',
            ], 200),
        ]);
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
            $response->isRedirect() || in_array($response->getStatusCode(), [401, 403, 404, 405, 419, 422, 429], true),
            $context.': отказ (redirect/401/403/404/405/419/422/429), получено '.$response->getStatusCode()
        );
    }

    protected function assertCreatedPartnerAndAdmin(string $email, string $schoolTitle, string $adminName): User
    {
        $this->assertDatabaseHas('partners', [
            'email' => $email,
            'title' => $schoolTitle,
            'is_enabled' => 1,
        ]);

        $partner = Partner::query()->where('email', $email)->firstOrFail();
        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertSame($partner->id, (int) $user->partner_id);
        $this->assertSame($adminName, $user->name);
        $this->assertSame($this->roleId('admin'), (int) $user->role_id);
        $this->assertTrue((bool) $user->is_enabled);
        $this->assertDatabaseHas('partner_widgets', [
            'partner_id' => $partner->id,
        ]);

        return $user;
    }

    /**
     * Inline-скрипт формы #partner-register-form (не JSON-LD и не модалка демо).
     */
    protected function partnerRegisterInlineScript(string $html): string
    {
        if (! preg_match(
            '/<script(?![^>]*\bsrc\b)[^>]*>\s*\(function\s*\(\)\s*\{[\s\S]*?getElementById\(\'partner-register-form\'\)[\s\S]*?<\/script>/i',
            $html,
            $match
        )) {
            return '';
        }

        return $match[0];
    }
}
