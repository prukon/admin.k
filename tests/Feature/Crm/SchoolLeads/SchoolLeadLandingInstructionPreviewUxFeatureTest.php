<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Support\RuPhone;

/**
 * UX модалки инструкции: кнопка не ведёт сразу на страницу, дефолты, селект админов,
 * JS preventDefault/AJAX, сброс при повторном открытии, стандартная ширина модалки.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadLandingInstructionPreviewUxFeatureTest extends SchoolLeadLandingInstructionPreviewTestCase
{
    public function test_instruction_button_opens_modal_instead_of_instruction_page(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $html = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();
        $instructionUrl = route('lead.instruction', ['landingSlug' => 'crm-instr-school']);

        $this->assertStringContainsString('id="openInstructionSettingsBtn"', $html);
        $this->assertStringContainsString('data-bs-target="#instructionPhoneModal"', $html);
        $this->assertStringContainsString('id="instructionPhoneModal"', $html);
        $this->assertStringNotContainsString('href="'.$instructionUrl.'"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/class="modal-dialog[^"]*(modal-xl|modal-fullscreen)/',
            $html
        );
    }

    public function test_modal_defaults_to_phone_required_empty_until_chosen(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $html = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();

        $omitPos = strpos($html, 'id="instructionOmitPhone"');
        $this->assertNotFalse($omitPos);
        $omitChunk = substr($html, $omitPos, 280);
        $this->assertStringNotContainsString('checked', $omitChunk);

        $phonePos = strpos($html, 'id="instructionPhoneInput"');
        $this->assertNotFalse($phonePos);
        $phoneChunk = substr($html, $phonePos, 400);
        $this->assertMatchesRegularExpression('/value=""/', $phoneChunk);

        $this->assertStringNotContainsString('id="instructionAdminPhoneSelect"', $html);
        $this->assertStringNotContainsString('Другой номер', $html);

        $questionPos = strpos($html, 'Нужно ли указывать номер телефона в инструкции?');
        $this->assertNotFalse($questionPos);
        $this->assertLessThan($omitPos, $questionPos);
        $this->assertLessThan($phonePos, $omitPos);
    }

    public function test_reopening_modal_resets_omit_checkbox_and_phone_via_js(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/landing.blade.php');
        $js = $this->instructionModalJavascript($path);

        $this->assertStringContainsString("\$instructionModal.on('show.bs.modal'", $js);
        $this->assertStringContainsString('resetInstructionForm()', $js);
        $this->assertStringContainsString("\$omitPhone.prop('checked', false)", $js);
        $this->assertStringContainsString("\$adminPhoneSelect.val('')", $js);
        $this->assertStringContainsString("window.PhoneInputMask.setValue(\$phoneInput, '')", $js);

        $showPos = strpos($js, "\$instructionModal.on('show.bs.modal'");
        $this->assertNotFalse($showPos);
        $showChunk = substr($js, $showPos, 250);
        $this->assertStringContainsString('resetInstructionForm()', $showChunk);
    }

    public function test_omit_checkbox_hides_phone_fields_and_unchecked_keeps_them(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/landing.blade.php');
        $js = $this->instructionModalJavascript($path);

        $this->assertStringContainsString('syncInstructionPhoneFields()', $js);
        $this->assertStringContainsString("\$phoneFields.toggleClass('d-none', omit)", $js);
        $this->assertStringContainsString("\$phoneFields.find('input, select').prop('disabled', omit)", $js);
        $this->assertStringContainsString("\$omitPhone.on('change'", $js);
    }

    public function test_admin_phone_select_starts_empty_and_offers_custom_number(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();
        $listed = $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Анна',
            'lastname' => 'Селектова',
            'phone' => '79111111111',
            'email' => 'ux-admin-'.uniqid('', true).'@example.test',
        ]);

        $html = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();
        $selectPos = strpos($html, 'id="instructionAdminPhoneSelect"');
        $this->assertNotFalse($selectPos);
        $selectChunk = substr($html, $selectPos, 900);

        $this->assertStringContainsString('<option value="">Выберите номер</option>', $selectChunk);
        $this->assertStringContainsString('Другой номер', $selectChunk);
        $this->assertStringContainsString('value="'.$listed->id.'"', $selectChunk);
        $this->assertStringContainsString('data-phone="79111111111"', $selectChunk);
        $this->assertStringContainsString('Селектова Анна — '.RuPhone::formatForInput('79111111111'), $selectChunk);
        $this->assertStringNotContainsString('selected', $selectChunk);

        $selectPosInPage = strpos($html, 'id="instructionAdminPhoneSelect"');
        $phonePos = strpos($html, 'id="instructionPhoneInput"');
        $this->assertNotFalse($phonePos);
        $this->assertLessThan($phonePos, $selectPosInPage);
    }

    public function test_select_change_fills_phone_or_clears_for_custom_number(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/landing.blade.php');
        $js = $this->instructionModalJavascript($path);

        $this->assertStringContainsString("\$adminPhoneSelect.on('change'", $js);
        $this->assertStringContainsString("value === '__custom__'", $js);
        $this->assertStringContainsString('window.PhoneInputMask.setValue($phoneInput, digits)', $js);
        $this->assertStringContainsString("window.PhoneInputMask.setValue(\$phoneInput, '')", $js);
    }

    public function test_ajax_submit_prevents_native_post_and_shows_errors_under_phone(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/landing.blade.php');
        $js = $this->instructionModalJavascript($path);

        $submitPos = strpos($js, "\$instructionForm.on('submit'");
        $this->assertNotFalse($submitPos);
        $chunk = substr($js, $submitPos, 1600);
        $this->assertStringContainsString('e.preventDefault()', $chunk);
        $this->assertStringContainsString('$.ajax({', $chunk);
        $this->assertStringContainsString("method: 'POST'", $chunk);
        $this->assertStringContainsString('showInstructionErrors(body.errors', $chunk);
        $this->assertStringContainsString("data-error-for=\"' + field + '\"", $js);
        $this->assertStringContainsString("window.open(url, '_blank', 'noopener,noreferrer')", $chunk);
        $this->assertSame(1, substr_count($js, "\$instructionForm.on('submit'"));
        $this->assertStringNotContainsString('elseif', $chunk);
    }

    public function test_modal_form_posts_to_preview_endpoint_when_js_fails(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $html = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();
        $previewUrl = $this->previewUrl();

        $this->assertMatchesRegularExpression(
            '/id="instructionPhoneForm"[^>]*(method="post"|action=")/i',
            $html
        );
        $this->assertStringContainsString('method="post"', strtolower($html));
        $this->assertTrue(
            str_contains($html, 'action="'.$previewUrl.'"')
            || str_contains($html, 'action="'.e($previewUrl).'"')
            || str_contains($html, str_replace('/', '\/', $previewUrl)),
            'Форма модалки должна POST на instruction-preview (native safety-net)'
        );
        $this->assertMatchesRegularExpression('/name="_token"|csrf/', $html);
    }

    public function test_without_slug_modal_and_button_are_hidden(): void
    {
        $this->actingAsLandingViewer();
        app(\App\Services\PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id)
            ->update(['landing_slug' => null]);

        $html = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Инструкция для родителей', $html);
        $this->assertStringNotContainsString('id="instructionPhoneModal"', $html);
        $this->assertStringNotContainsString('id="openInstructionSettingsBtn"', $html);
    }

    private function instructionModalJavascript(string $path): string
    {
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $start = strpos($content, '$instructionForm');
        $this->assertNotFalse($start);

        return substr($content, $start);
    }
}
