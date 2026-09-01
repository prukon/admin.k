<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

/**
 * UX /partner/register и кнопки «Регистрация» в шапке:
 * порядок полей, old() без пароля, ошибки под полями, обе копии кнопки (десктоп/мобильное меню),
 * recaptcha JS не уходит в AJAX, дефолт «открыто» не навязывается при выключенном флаге.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PartnerSelfRegistrationUxFeatureTest extends PartnerSelfRegistrationTestCase
{
    public function test_guest_form_fields_render_in_school_name_email_password_order(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $html = $this->get($this->registerUrl())->assertOk()->getContent();
        $schoolPos = strpos($html, 'id="school_title"');
        $namePos = strpos($html, 'id="name"');
        $emailPos = strpos($html, 'id="email"');
        $passwordPos = strpos($html, 'id="password"');
        $confirmPos = strpos($html, 'id="password_confirmation"');
        $this->assertNotFalse($schoolPos);
        $this->assertNotFalse($namePos);
        $this->assertNotFalse($emailPos);
        $this->assertNotFalse($passwordPos);
        $this->assertNotFalse($confirmPos);
        $this->assertLessThan($namePos, $schoolPos);
        $this->assertLessThan($emailPos, $namePos);
        $this->assertLessThan($passwordPos, $emailPos);
        $this->assertLessThan($confirmPos, $passwordPos);
    }

    public function test_failed_validation_keeps_old_values_except_passwords_and_shows_error_under_email(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'school_title' => 'Школа Олд',
            'name' => 'Пётр',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]))->assertRedirect($this->registerUrl());

        $html = $this->get($this->registerUrl())->assertOk()->getContent();
        $this->assertStringContainsString('value="Школа Олд"', $html);
        $this->assertStringContainsString('value="Пётр"', $html);
        $this->assertStringContainsString('value="not-an-email"', $html);
        $this->assertStringNotContainsString('value="password123"', $html);
        $this->assertStringContainsString('Введите корректный email.', $html);
        $this->assertStringContainsString('is-invalid', $html);

        $emailPos = strpos($html, 'id="email"');
        $passwordPos = strpos($html, 'id="password"');
        $this->assertNotFalse($emailPos);
        $this->assertNotFalse($passwordPos);
        $emailBlock = substr($html, $emailPos, $passwordPos - $emailPos);
        $this->assertStringContainsString('is-invalid', $emailBlock);
        $this->assertStringContainsString('Введите корректный email.', $emailBlock);
        $this->assertStringNotContainsString('Укажите название школы.', $emailBlock);
    }

    public function test_password_mismatch_error_renders_under_password_not_confirmation(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'password' => 'password123',
            'password_confirmation' => 'other-password',
        ]))->assertRedirect($this->registerUrl());

        $html = $this->get($this->registerUrl())->assertOk()->getContent();
        $this->assertStringContainsString('Пароли не совпадают.', $html);

        $passwordPos = strpos($html, 'id="password"');
        $confirmPos = strpos($html, 'id="password_confirmation"');
        $this->assertNotFalse($passwordPos);
        $this->assertNotFalse($confirmPos);
        $passwordBlock = substr($html, $passwordPos, $confirmPos - $passwordPos);
        $this->assertStringContainsString('is-invalid', $passwordBlock);
        $this->assertStringContainsString('Пароли не совпадают.', $passwordBlock);
    }

    public function test_landing_shows_register_button_in_desktop_and_mobile_nav_when_enabled(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $html = $this->get(route('landing.home'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '>Регистрация</a>'));
        $this->assertStringContainsString(route('partner.register', absolute: false), $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, '>Войти</a>'));

        $desktop = strpos($html, 'id="mainNav"');
        $drawer = strpos($html, 'id="publicNavDrawer"');
        $this->assertNotFalse($desktop);
        $this->assertNotFalse($drawer);
        $desktopChunk = substr($html, $desktop, $drawer - $desktop);
        $drawerChunk = substr($html, $drawer);
        $this->assertStringContainsString('>Регистрация</a>', $desktopChunk);
        $this->assertStringContainsString('>Регистрация</a>', $drawerChunk);
        $this->assertStringContainsString('btn-outline-primary', $desktopChunk);
        $this->assertStringContainsString('public-nav-drawer__auth', $drawerChunk);
    }

    public function test_landing_hides_register_button_in_both_navs_but_keeps_login_when_disabled(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();

        $html = $this->get(route('landing.home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Регистрация</a>', $html);
        $this->assertStringNotContainsString(route('partner.register', absolute: false), $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, '>Войти</a>'));
    }

    public function test_seo_and_blog_pages_hide_register_button_when_flag_is_disabled(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();

        foreach ([
            route('landing.seo.football'),
            route('blog.index'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('>Регистрация</a>', $html, $url);
            $this->assertStringContainsString('>Войти</a>', $html, $url);
        }
    }

    public function test_closed_page_offers_demo_and_telegram_without_registration_form(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();

        $html = $this->get($this->registerUrl())->assertOk()->getContent();
        $this->assertStringContainsString('Регистрация временно закрыта', $html);
        $this->assertStringContainsString('data-bs-target="#createOrder"', $html);
        $this->assertStringContainsString('Записаться на демо', $html);
        $this->assertStringContainsString('https://t.me/prukon', $html);
        $this->assertStringContainsString('id="createOrder"', $html);
        $this->assertStringNotContainsString('id="partner-register-form"', $html);
        $this->assertStringNotContainsString('name="school_title"', $html);
        $this->assertStringContainsString(route('login', absolute: false), $html);
        $this->assertStringNotContainsString("{action: 'partner_register'}", $html);
        $this->assertStringNotContainsString('id="partner-register-submit"', $html);
    }

    public function test_enabled_form_is_native_post_and_recaptcha_script_uses_partner_register_action(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        config(['services.recaptcha.site_key' => 'ux-site-key']);

        $html = $this->get($this->registerUrl())->assertOk()->getContent();
        $this->assertStringContainsString('id="partner-register-form"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="recaptcha_token"', $html);
        $this->assertStringContainsString('id="recaptcha_token"', $html);
        $this->assertStringContainsString("grecaptcha.execute('ux-site-key', {action: 'partner_register'})", $html);

        $formScript = $this->partnerRegisterInlineScript($html);
        $this->assertStringContainsString('e.preventDefault()', $formScript);
        $this->assertStringContainsString('form.submit()', $formScript);
        $this->assertStringContainsString("{action: 'partner_register'}", $formScript);
        $this->assertStringNotContainsString('$.ajax', $formScript);
        $this->assertStringNotContainsString('fetch(', $formScript);
    }

    public function test_recaptcha_script_is_not_rendered_when_site_key_is_missing(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        config(['services.recaptcha.site_key' => null]);

        $html = $this->get($this->registerUrl())->assertOk()->getContent();
        $this->assertStringContainsString('id="partner-register-form"', $html);
        $this->assertStringContainsString('id="partner-register-submit"', $html);
        $this->assertStringNotContainsString("{action: 'partner_register'}", $html);
        $this->assertSame('', $this->partnerRegisterInlineScript($html));
    }

    public function test_authenticated_landing_does_not_show_register_button(): void
    {
        $this->asAdmin();
        $this->enablePartnerSelfRegistration();

        $html = $this->actingAs($this->user)->get(route('landing.home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Регистрация</a>', $html);
        $this->assertStringContainsString('В личный кабинет', $html);
    }

    public function test_opening_register_again_after_failed_submit_does_not_keep_password(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'email' => '',
            'password' => 'secret-old-pass',
            'password_confirmation' => 'secret-old-pass',
        ]))->assertRedirect($this->registerUrl());

        $html = $this->get($this->registerUrl())->assertOk()->getContent();
        $this->assertStringNotContainsString('secret-old-pass', $html);
        $this->assertStringContainsString('Укажите email.', $html);
    }

    public function test_blade_navbar_requires_flag_not_only_named_route(): void
    {
        $source = (string) file_get_contents(resource_path('views/includes/public-navbar.blade.php'));
        $this->assertStringContainsString('$partnerSelfRegistrationEnabled', $source);
        $this->assertStringContainsString("config('app.partner_self_registration_enabled', true)", $source);
        $this->assertSame(2, substr_count($source, '>Регистрация</a>'));
        $this->assertStringNotContainsString('@if (Route::has(\'partner.register\'))', $source);
    }
}
