<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PartnerSelfRegistrationFlagFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiled = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_compiled_views_partner_register_'
            . (string) Str::uuid();

        if (! is_dir($compiled)) {
            @mkdir($compiled, 0777, true);
        }
        @chmod($compiled, 0777);
        config(['view.compiled' => $compiled]);
        config(['logging.default' => 'errorlog']);
    }

    public function test_landing_shows_register_button_when_flag_is_enabled(): void
    {
        Config::set('app.partner_self_registration_enabled', true);

        $html = $this->get(route('landing.home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('partner.register', absolute: false), $html);
        $this->assertStringContainsString('>Регистрация</a>', $html);
        $this->assertStringContainsString('>Войти</a>', $html);
    }

    public function test_landing_hides_register_button_when_flag_is_disabled(): void
    {
        Config::set('app.partner_self_registration_enabled', false);

        $html = $this->get(route('landing.home'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('partner.register', absolute: false), $html);
        $this->assertStringNotContainsString('>Регистрация</a>', $html);
        $this->assertStringContainsString('>Войти</a>', $html);
    }

    public function test_register_page_shows_form_when_flag_is_enabled(): void
    {
        Config::set('app.partner_self_registration_enabled', true);

        $this->get(route('partner.register'))
            ->assertOk()
            ->assertViewIs('landing.partner-register')
            ->assertSee('Регистрация школы/секции')
            ->assertSee('name="school_title"', false);
    }

    public function test_register_page_shows_closed_notice_when_flag_is_disabled(): void
    {
        Config::set('app.partner_self_registration_enabled', false);

        $this->get(route('partner.register'))
            ->assertOk()
            ->assertViewIs('landing.partner-register-closed')
            ->assertSee('Регистрация временно закрыта')
            ->assertDontSee('name="school_title"', false);
    }

    public function test_register_post_is_forbidden_and_creates_nothing_when_flag_is_disabled(): void
    {
        Config::set('app.partner_self_registration_enabled', false);

        $partnersBefore = Partner::query()->count();

        $this->from(route('partner.register'))
            ->post(route('partner.register.store'), [
                'school_title' => 'Тестовая школа',
                'name' => 'Иван',
                'email' => 'new-partner@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'recaptcha_token' => 'token',
            ])
            ->assertForbidden();

        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertDatabaseMissing('users', [
            'email' => 'new-partner@example.com',
        ]);
    }
}
