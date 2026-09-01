<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * P1: доступ к /partner/register — гость видит форму; залогиненный уходит в кабинет;
 * флаг закрывает POST (403) и прячет кнопку; CRM-права не нужны и не открывают форму.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PartnerSelfRegistrationAccessFeatureTest extends PartnerSelfRegistrationTestCase
{
    public function test_guest_can_open_registration_form_when_flag_is_enabled(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $response = $this->get($this->registerUrl());
        $this->assertNotServerError($response, 'GET /partner/register гость');
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertViewIs('landing.partner-register');
        $response->assertSee('id="partner-register-form"', false);
        $response->assertSee('name="school_title"', false);
    }

    public function test_guest_sees_closed_page_not_form_when_flag_is_disabled(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();

        $response = $this->get($this->registerUrl());
        $this->assertNotServerError($response, 'GET закрыто гость');
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertViewIs('landing.partner-register-closed');
        $response->assertSee('Регистрация временно закрыта', false);
        $response->assertDontSee('id="partner-register-form"', false);
        $response->assertDontSee('name="school_title"', false);
    }

    public function test_authenticated_user_is_redirected_away_from_register_get_and_post(): void
    {
        $this->asAdmin();
        $this->enablePartnerSelfRegistration();
        $partnersBefore = Partner::query()->count();

        $html = $this->actingAs($this->user)->get($this->registerUrl());
        $this->assertNotServerError($html, 'GET HTML залогиненный');
        $this->assertNotEmptyOk($html, 'GET HTML залогиненный');
        $html->assertRedirect('/cabinet');

        $json = $this->actingAs($this->user)->getJson($this->registerUrl(), $this->ajaxHeaders());
        $this->assertNotServerError($json, 'GET JSON залогиненный');
        $this->assertNotEmptyOk($json, 'GET JSON залогиненный');
        $json->assertRedirect('/cabinet');

        $this->fakeRecaptchaSuccess();
        $post = $this->actingAs($this->user)->post($this->registerStoreUrl(), $this->validRegistrationPayload());
        $this->assertNotServerError($post, 'POST HTML залогиненный');
        $this->assertNotEmptyOk($post, 'POST HTML залогиненный');
        $post->assertRedirect('/cabinet');
        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_admin_without_crm_permission_still_cannot_use_guest_register_form(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->enablePartnerSelfRegistration();
        $partnersBefore = Partner::query()->count();

        $get = $this->actingAs($actor)->get($this->registerUrl());
        $this->assertNotServerError($get, 'GET без groups.view');
        $this->assertNotEmptyOk($get, 'GET без groups.view');
        $get->assertRedirect('/cabinet');

        $this->fakeRecaptchaSuccess();
        $post = $this->actingAs($actor)->post($this->registerStoreUrl(), $this->validRegistrationPayload());
        $this->assertDeniedWithoutServerError($post, 'POST без groups.view');
        $this->assertSame($partnersBefore, Partner::query()->count());
    }

    public function test_trainer_and_student_are_redirected_from_register_like_admin(): void
    {
        $this->enablePartnerSelfRegistration();

        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner);
            $get = $this->actingAs($actor)->get($this->registerUrl());
            $this->assertNotServerError($get, 'GET '.$roleName);
            $this->assertNotEmptyOk($get, 'GET '.$roleName);
            $get->assertRedirect('/cabinet');
        }
    }

    public function test_guest_post_is_forbidden_when_flag_is_disabled(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        $partnersBefore = Partner::query()->count();
        $payload = $this->validRegistrationPayload([
            'email' => 'closed-flag@example.test',
        ]);

        $html = $this->from($this->registerUrl())->post($this->registerStoreUrl(), $payload);
        $this->assertNotServerError($html, 'POST HTML закрыто');
        $this->assertNotEmptyOk($html, 'POST HTML закрыто');
        $html->assertForbidden();

        Cache::flush();

        $json = $this->from($this->registerUrl())->postJson($this->registerStoreUrl(), $payload, $this->ajaxHeaders());
        $this->assertNotServerError($json, 'POST JSON закрыто');
        $this->assertNotEmptyOk($json, 'POST JSON закрыто');
        $json->assertForbidden();
        $this->assertNotSame('', trim((string) $json->getContent()));

        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'closed-flag@example.test']);
        $this->assertGuest();
    }

    public function test_student_registration_setting_does_not_close_partner_self_registration(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        Setting::query()->updateOrCreate(
            ['name' => 'registrationActivity', 'partner_id' => $this->partner->id],
            ['status' => 0]
        );

        $response = $this->get($this->registerUrl());
        $this->assertNotServerError($response, 'GET при выключенной ученической регистрации');
        $response->assertOk();
        $response->assertViewIs('landing.partner-register');
        $response->assertSee('id="partner-register-form"', false);
    }

    public function test_guest_json_get_register_returns_html_form_not_empty_payload(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $response = $this->getJson($this->registerUrl());
        $this->assertNotServerError($response, 'getJson /partner/register гость');
        $response->assertOk();
        $this->assertStringContainsString('id="partner-register-form"', $response->getContent());
        $this->assertStringContainsString('name="school_title"', $response->getContent());
        $this->assertStringNotContainsString('"user":', $response->getContent());
    }
}
