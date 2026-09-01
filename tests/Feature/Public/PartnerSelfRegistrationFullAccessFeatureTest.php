<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * HTTP-матрица GET/POST/PATCH/PUT/DELETE /partner/register: JSON и HTML, гость и залогиненный,
 * флаг вкл/выкл. Ни один метод не 500; мутации кроме осмысленного GET не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PartnerSelfRegistrationFullAccessFeatureTest extends PartnerSelfRegistrationTestCase
{
    public function test_guest_http_matrix_when_enabled_never_returns_500(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $getHtml = $this->get($this->registerUrl());
        $this->assertNotServerError($getHtml, 'GET HTML гость');
        $getHtml->assertOk();
        $this->assertStringContainsString('name="school_title"', $getHtml->getContent());

        $getJson = $this->getJson($this->registerUrl(), $this->ajaxHeaders());
        $this->assertNotServerError($getJson, 'GET JSON гость');
        $getJson->assertOk();
        $this->assertNotSame('', trim((string) $getJson->getContent()));

        $postHtml = $this->from($this->registerUrl())->post($this->registerStoreUrl(), [
            'email' => 'full-access@example.test',
        ]);
        $this->assertNotServerError($postHtml, 'POST HTML гость');
        $this->assertNotEmptyOk($postHtml, 'POST HTML гость');

        $postJson = $this->from($this->registerUrl())->postJson($this->registerStoreUrl(), [
            'email' => 'full-access@example.test',
        ], $this->ajaxHeaders());
        $this->assertNotServerError($postJson, 'POST JSON гость');
        $this->assertNotSame(200, $postJson->getStatusCode());

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->registerUrl(), [], $this->ajaxHeaders());
            $this->assertDeniedWithoutServerError($json, $method.' JSON гость');

            $html = $this->call($method, $this->registerUrl());
            $this->assertDeniedWithoutServerError($html, $method.' HTML гость');
        }
    }

    public function test_guest_http_matrix_when_disabled_never_returns_500(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        $partnersBefore = Partner::query()->count();

        $getHtml = $this->get($this->registerUrl());
        $this->assertNotServerError($getHtml, 'GET HTML закрыто');
        $getHtml->assertOk();
        $this->assertStringContainsString('Регистрация временно закрыта', $getHtml->getContent());

        $getJson = $this->getJson($this->registerUrl(), $this->ajaxHeaders());
        $this->assertNotServerError($getJson, 'GET JSON закрыто');
        $getJson->assertOk();
        $this->assertNotSame('', trim((string) $getJson->getContent()));

        $postHtml = $this->from($this->registerUrl())->post(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'matrix-closed@example.test'])
        );
        $this->assertNotServerError($postHtml, 'POST HTML закрыто');
        $this->assertNotEmptyOk($postHtml, 'POST HTML закрыто');
        $postHtml->assertForbidden();

        Cache::flush();

        $postJson = $this->from($this->registerUrl())->postJson(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'matrix-closed-json@example.test']),
            $this->ajaxHeaders()
        );
        $this->assertNotServerError($postJson, 'POST JSON закрыто');
        $this->assertNotEmptyOk($postJson, 'POST JSON закрыто');
        $postJson->assertForbidden();

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->registerUrl(), [], $this->ajaxHeaders());
            $this->assertDeniedWithoutServerError($json, $method.' JSON закрыто');

            $html = $this->call($method, $this->registerUrl());
            $this->assertDeniedWithoutServerError($html, $method.' HTML закрыто');
        }

        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertGuest();
    }

    public function test_authenticated_http_matrix_never_returns_500(): void
    {
        $this->asAdmin();
        $this->enablePartnerSelfRegistration();
        $partnersBefore = Partner::query()->count();

        foreach (['GET', 'POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->actingAs($this->user)
                ->json($method, $this->registerUrl(), $this->validRegistrationPayload(), $this->ajaxHeaders());
            $this->assertNotServerError($json, $method.' JSON залогиненный');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON залогиненный не 200');

            $html = $this->actingAs($this->user)->call($method, $this->registerUrl(), $this->validRegistrationPayload());
            $this->assertNotServerError($html, $method.' HTML залогиненный');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML залогиненный не 200');
        }

        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_ip_throttle_returns_429_not_500_after_limit(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->from($this->registerUrl())->post($this->registerStoreUrl(), [
                'email' => 'throttle-'.$i.'@example.test',
            ]);
            $this->assertNotServerError($last, 'throttle попытка '.($i + 1));
        }

        $this->assertNotNull($last);
        $this->assertSame(429, $last->getStatusCode());
        $this->assertNotSame(200, $last->getStatusCode());
        $this->assertGuest();
    }

    public function test_landing_home_http_matrix_never_returns_500_for_guest(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $get = $this->get(route('landing.home'));
        $this->assertNotServerError($get, 'GET лендинг');
        $get->assertOk();
        $this->assertNotSame('', trim((string) $get->getContent()));

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $html = $this->call($method, route('landing.home'));
            $this->assertDeniedWithoutServerError($html, $method.' лендинг HTML');

            $json = $this->json($method, route('landing.home'), [], $this->ajaxHeaders());
            $this->assertDeniedWithoutServerError($json, $method.' лендинг JSON');
        }
    }
}
