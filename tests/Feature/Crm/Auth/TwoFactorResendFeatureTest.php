<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * POST /two-factor/resend: метод контроллера есть, кулдаун, SMS, не 500.
 */
final class TwoFactorResendFeatureTest extends SessionAuthTestCase
{
    public function test_guest_resend_redirects_to_login_and_is_not_500(): void
    {
        Auth::logout();

        $response = $this->from(route('login'))->post(route('two-factor.resend'));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('login'));
    }

    public function test_resend_sends_new_sms_code_and_redirects_back(): void
    {
        $this->asAdmin();
        $this->enableTwoFactorOnActor('+79001234567');
        $oldHash = (string) $this->user->two_factor_code;

        $this->fakeSmsRuOk();

        $response = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.resend'));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('two-factor.challenge'));
        $response->assertSessionHas('status', 'Код отправлен повторно.');
        $response->assertSessionHas('2fa:last_sent_at');

        $fresh = $this->user->fresh();
        $this->assertNotSame($oldHash, (string) $fresh->two_factor_code);
        $this->assertNotNull($fresh->two_factor_expires_at);
        $this->assertTrue($fresh->two_factor_expires_at->greaterThan(now()->addMinutes(9)));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sms.ru/sms/send')
                && str_contains((string) $request['msg'], 'Код для входа:');
        });
    }

    public function test_resend_during_cooldown_does_not_send_sms(): void
    {
        $this->asAdmin();
        $this->enableTwoFactorOnActor('+79001234567');
        $oldHash = (string) $this->user->two_factor_code;

        $this->fakeSmsRuOk();

        $response = $this->from(route('two-factor.challenge'))
            ->withSession(['2fa:last_sent_at' => now()->subSeconds(10)])
            ->post(route('two-factor.resend'));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('two-factor.challenge'));
        $response->assertSessionHasErrors('resend');
        $this->assertStringContainsString('Повторная отправка доступна через', (string) session('errors')->first('resend'));
        $this->assertSame($oldHash, (string) $this->user->fresh()->two_factor_code);

        Http::assertNothingSent();
    }

    public function test_resend_without_phone_redirects_to_phone_form(): void
    {
        $this->asAdmin();
        $this->enableTwoFactorOnActor(null);

        $this->fakeSmsRuOk();

        $response = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.resend'));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('two-factor.phone'));
        Http::assertNothingSent();
    }

    public function test_resend_sms_failure_returns_error_under_resend_field(): void
    {
        $this->asAdmin();
        $this->enableTwoFactorOnActor('+79001234567');

        config([
            'services.sms_ru.api_id' => 'test-smsru-key',
            'services.sms_ru.from' => 'kidscrm',
        ]);
        Http::fake(['sms.ru/*' => Http::response('gateway-down', 502)]);

        $response = $this->from(route('two-factor.challenge'))
            ->post(route('two-factor.resend'));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('two-factor.challenge'));
        $response->assertSessionHasErrors('resend');
        $this->assertNotSame('', (string) session('errors')->first('resend'));
    }

    public function test_resend_wrong_methods_are_not_empty_200(): void
    {
        $this->asAdmin();
        $this->enableTwoFactorOnActor('+79001234567');

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $html = $this->call($method, route('two-factor.resend'));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML resend не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML resend не пустой 200');

            $json = $this->json($method, route('two-factor.resend'), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON resend не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON resend не пустой 200');
        }
    }

    private function enableTwoFactorOnActor(?string $phone): void
    {
        $this->user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
            'phone' => $phone,
        ])->save();
    }

    private function fakeSmsRuOk(): void
    {
        config([
            'services.sms_ru.api_id' => 'test-smsru-key',
            'services.sms_ru.from' => 'kidscrm',
        ]);
        Http::fake([
            'sms.ru/*' => Http::response([
                'status' => 'OK',
            ], 200),
        ]);
    }
}
