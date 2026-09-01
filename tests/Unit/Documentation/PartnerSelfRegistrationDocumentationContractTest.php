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
        $this->assertStringContainsString('registrationActivity', $chunk);
        $this->assertStringContainsString('/docs/documentation/partner-self-registration', $chunk);
        $this->assertStringContainsString('PartnerSelfRegistrationFlagFeatureTest', $chunk);
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
        $this->assertStringContainsString('registrationActivity', $html);
        $this->assertStringContainsString('public-navbar.blade.php', $html);
        $this->assertStringContainsString('landing.partner-register-closed', $html);
        $this->assertStringContainsString('StorePartnerSelfRegistrationRequest::authorize()', $html);
        $this->assertStringContainsString('PartnerSelfRegistrationFlagFeatureTest', $html);
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
