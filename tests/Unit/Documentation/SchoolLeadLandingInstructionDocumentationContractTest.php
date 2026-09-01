<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#landing-parents-instruction-index и school-leads-landing §4.2.
 */
final class SchoolLeadLandingInstructionDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_parents_instruction_page(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="landing-parents-instruction-index"', $html);
        $start = strpos($html, 'id="landing-parents-instruction-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="partner-self-registration-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/lead/{landingSlug}/instruction', $chunk);
        $this->assertStringContainsString('Инструкция для родителей', $chunk);
        $this->assertStringContainsString('qrcode-generator.min.js', $chunk);
        $this->assertStringContainsString('instruction.pdf', $chunk);
        $this->assertStringContainsString('UrlQrCode', $chunk);
        $this->assertStringContainsString('instrukciya-{slug}.pdf', $chunk);
        $this->assertStringContainsString('landingDisplayName', $chunk);
        $this->assertStringContainsString('lead.show', $chunk);
        $this->assertStringContainsString('throttle:60,1', $chunk);
        $this->assertStringContainsString('instruction_url', $chunk);
        $this->assertStringContainsString('school-leads-landing#4-2', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionUxFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('UrlQrCodeTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#landing-parents-instruction-index', $html);
        $this->assertStringContainsString('school-leads-landing#4-2', $html);
    }

    public function test_landing_doc_describes_instruction_route_and_crm_button(): void
    {
        $html = $this->docFile('school-leads-landing.html');

        $this->assertStringContainsString('id="4-2"', $html);
        $this->assertStringContainsString('/lead/{landingSlug}/instruction', $html);
        $this->assertStringContainsString('lead.instruction', $html);
        $this->assertStringContainsString('lead.instruction.pdf', $html);
        $this->assertStringContainsString('Скачать PDF', $html);
        $this->assertStringContainsString('UrlQrCode', $html);
        $this->assertStringContainsString('instrukciya-{slug}.pdf', $html);
        $this->assertStringContainsString('landing.partner-lead-instruction', $html);
        $this->assertStringContainsString('Инструкция для родителей', $html);
        $this->assertStringContainsString('SchoolLeadLandingInstructionFeatureTest.php', $html);
        $this->assertStringContainsString('SchoolLeadLandingInstructionUxFeatureTest.php', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('window.location.reload', $html);
        $this->assertStringContainsString('errors.landing_slug', $html);
        $this->assertStringContainsString('data-url', $html);
        $this->assertStringContainsString('/doc#landing-parents-instruction-index', $html);
        $this->assertStringContainsString('partners.phone', $html);
        $this->assertStringContainsString('RuPhone::formatForInput', $html);
        $this->assertStringContainsString('#landing-qr', $html);
        $this->assertStringContainsString('qrcode-generator.min.js', $html);
        $this->assertStringContainsString('landing.partner-lead-instruction-pdf', $html);
        $this->assertStringContainsString('landingDisplayName', $html);
        $this->assertStringContainsString('<b>без</b> <code>instruction_url</code>', $html);
        $this->assertStringContainsString('is_landing_active = false', $html);
        $this->assertStringContainsString('кнопка при наличии slug <b>остаётся</b>', $html);
        $this->assertStringNotContainsString('Место под QR-код на странице пустое', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
