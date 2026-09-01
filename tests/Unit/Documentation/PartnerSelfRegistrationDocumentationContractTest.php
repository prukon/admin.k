<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class PartnerSelfRegistrationDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_partner_self_registration_flag(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="partner-self-registration-index"', $html);
        $start = strpos($html, 'id="partner-self-registration-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="landing-team-info-rows-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('PARTNER_SELF_REGISTRATION_ENABLED', $chunk);
        $this->assertStringContainsString('false', $chunk);
        $this->assertStringContainsString('/partner/register', $chunk);
        $this->assertStringContainsString('Регистрация', $chunk);
        $this->assertStringContainsString('школы (партнёра)', $chunk);
        $this->assertStringContainsString('не регистрация ученика', $chunk);
        $this->assertStringContainsString('FILTER_VALIDATE_BOOLEAN', $chunk);
        $this->assertStringContainsString('302', $chunk);
        $this->assertStringContainsString('/cabinet', $chunk);
        $this->assertStringContainsString('registrationActivity', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-self-registration', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationFlagFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationAccessFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationUxFeatureTest', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#partner-self-registration-index', $html);
    }

    public function test_partner_self_registration_page_matches_code_contract(): void
    {
        $html = $this->docFile('partner-self-registration.html');

        $this->assertStringContainsString('/doc#partner-self-registration-index', $html);
        $this->assertStringContainsString("env('PARTNER_SELF_REGISTRATION_ENABLED', true)", $html);
        $this->assertStringContainsString('PARTNER_SELF_REGISTRATION_ENABLED=false', $html);
        $this->assertStringContainsString('partner_self_registration_enabled', $html);
        $this->assertStringContainsString('FILTER_VALIDATE_BOOLEAN', $html);
        $this->assertStringContainsString('registrationActivity', $html);
        $this->assertStringContainsString('public-navbar.blade.php', $html);
        $this->assertStringContainsString('landing.partner-register-closed', $html);
        $this->assertStringContainsString('StorePartnerSelfRegistrationRequest::authorize()', $html);
        $this->assertStringContainsString('errors[field]', $html);
        $this->assertStringContainsString('throttle:partner-registration-ip', $html);
        $this->assertStringContainsString('partner-registration:email:', $html);
        $this->assertStringContainsString('Слишком много попыток регистрации с этого email. Повторите через час.', $html);
        $this->assertStringContainsString("whereNull('deleted_at')", $html);
        $this->assertStringContainsString('Auth::login($user, false)', $html);
        $this->assertStringContainsString('session()->regenerate()', $html);
        $this->assertStringContainsString('noindex, nofollow', $html);
        $this->assertStringContainsString('assignBasePermissionsForPartner', $html);
        $this->assertStringContainsString('LessonOccurrenceStatusesSeeder::ensureForPartner', $html);
        $this->assertStringContainsString('TrainerTypeCatalog::ensureSystemType', $html);
        $this->assertStringContainsString('business_type', $html);
        $this->assertStringContainsString('не пишет', $html);
        $this->assertStringContainsString('https://t.me/prukon', $html);
        $this->assertStringContainsString("action <code>contact</code>", $html);
        $this->assertStringContainsString('нет скрипта <code>partner_register</code>', $html);
        $this->assertStringNotContainsString('без формы и без recaptcha JS', $html);
        $this->assertStringContainsString('Ошибка проверки защиты от спама. Попробуйте позже.', $html);
        $this->assertStringContainsString('e.preventDefault()', $html);
        $this->assertStringContainsString("{action: 'partner_register'}", $html);
        $this->assertStringContainsString('PartnerSelfRegistrationFlagFeatureTest', $html);
        $this->assertStringContainsString('PartnerSelfRegistrationAccessFeatureTest', $html);
        $this->assertStringContainsString('PartnerSelfRegistrationNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('PartnerSelfRegistrationAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('PartnerSelfRegistrationUxFeatureTest', $html);
        $this->assertStringContainsString('PartnerSelfRegistrationFullAccessFeatureTest', $html);
        $this->assertStringContainsString('test_partner_register_recaptcha_script_prevents_submit_until_token_and_is_not_ajax', $html);
        $this->assertStringContainsString('PartnerSelfRegistrationDocumentationContractTest', $html);
        $this->assertStringContainsString('config:cache', $html);
    }

    public function test_documentation_controller_lists_partner_self_registration_page(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'partner-self-registration'", $controller);
        $this->assertStringContainsString('PARTNER_SELF_REGISTRATION_ENABLED', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
