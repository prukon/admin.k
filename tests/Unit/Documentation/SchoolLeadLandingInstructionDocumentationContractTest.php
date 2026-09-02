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
        $this->assertStringContainsString('Не указывать номер телефона', $chunk);
        $this->assertStringContainsString('instruction-preview', $chunk);
        $this->assertStringContainsString('window.open', $chunk);
        $this->assertStringContainsString('не берётся', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionUxFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewUxFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('method="post"', $chunk);
        $this->assertStringContainsString('@csrf', $chunk);
        $this->assertStringContainsString('не JSON 200', $chunk);
        $this->assertStringContainsString('#instructionPhoneModal', $chunk);
        $this->assertStringContainsString('CRM-модалка', $chunk);
        $this->assertStringContainsString('PreviewSchoolLeadLandingInstructionRequest', $chunk);
        $this->assertStringContainsString('school-leads-landing#8', $chunk);
        $this->assertStringContainsString('UrlQrCodeTest', $chunk);
        $this->assertStringContainsString('img/logo.png', $chunk);
        $this->assertStringContainsString('онлайн-подписания договоров', $chunk);
        $this->assertStringContainsString('text-decoration: none', $chunk);
        $this->assertStringContainsString('одну страницу A4', $chunk);
        $this->assertStringContainsString('11pt', $chunk);
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
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewAccessFeatureTest.php', $html);
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewAjaxContractFeatureTest.php', $html);
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewNonAjaxSafetyNetFeatureTest.php', $html);
        $this->assertStringContainsString('SchoolLeadLandingInstructionPreviewUxFeatureTest.php', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('#instructionPhoneForm', $html);
        $this->assertStringContainsString('show.bs.modal', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('window.location.reload', $html);
        $this->assertStringContainsString('errors.landing_slug', $html);
        $this->assertStringContainsString('data-url', $html);
        $this->assertStringContainsString('/doc#landing-parents-instruction-index', $html);
        $this->assertStringContainsString('partners.phone', $html);
        $this->assertStringContainsString('не берётся', $html);
        $this->assertStringContainsString('instruction-preview', $html);
        $this->assertStringContainsString('PreviewSchoolLeadLandingInstructionRequest', $html);
        $this->assertStringContainsString('omit_phone', $html);
        $this->assertStringContainsString('Не указывать номер телефона', $html);
        $this->assertStringContainsString('Другой номер', $html);
        $this->assertStringContainsString('window.open', $html);
        $this->assertStringContainsString('phoneOptionsForPartner', $html);
        $this->assertStringContainsString('RuPhone::formatForInput', $html);
        $this->assertStringContainsString('#landing-qr', $html);
        $this->assertStringContainsString('qrcode-generator.min.js', $html);
        $this->assertStringContainsString('landing.partner-lead-instruction-pdf', $html);
        $this->assertStringContainsString('landingDisplayName', $html);
        $this->assertStringContainsString('<b>без</b> <code>instruction_url</code>', $html);
        $this->assertStringContainsString('is_landing_active = false', $html);
        $this->assertStringContainsString('кнопка при наличии slug <b>остаётся</b>', $html);
        $this->assertStringContainsString('#instructionPhoneModal', $html);
        $this->assertStringContainsString('img/logo.png', $html);
        $this->assertStringContainsString('онлайн-подписания договоров', $html);
        $this->assertStringContainsString('text-decoration: none', $html);
        $this->assertStringContainsString('одну страницу A4', $html);
        $this->assertStringContainsString('11pt', $html);
        $this->assertStringContainsString('10mm', $html);
        $this->assertStringNotContainsString('Место под QR-код на странице пустое', $html);
    }

    public function test_widget_doc_describes_instruction_modal_not_direct_link(): void
    {
        $html = $this->docFile('school-leads-widget.html');

        $this->assertStringContainsString('модалка телефона', $html);
        $this->assertStringContainsString('instruction-preview', $html);
        $this->assertStringNotContainsString(
            'кнопка «Инструкция для родителей» на HTML',
            $html
        );
        $this->assertStringNotContainsString(
            'кнопка «Инструкция для родителей» (HTML, новая вкладка',
            $html
        );
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
