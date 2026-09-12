<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\ParentProfile;
use App\Services\Contracts\ContractPdfConverterInterface;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Support\Contracts\CapturingFakeContractPdfConverter;

/**
 * UX: в PDF договора телефон с маской +7 (999) 999-99-99, а не 10 цифр autoUnmask.
 * Первое открытие / повтор / «Изменить» показывают ту же маску.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountContractFillPhoneMaskFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
        CapturingFakeContractPdfConverter::reset();
        $this->app->instance(ContractPdfConverterInterface::class, new CapturingFakeContractPdfConverter());
    }

    public function test_ten_digit_unmasked_phone_is_written_to_pdf_with_ui_mask(): void
    {
        $contract = $this->makeRequiredPhoneFillContract(
            [
                ['key' => 'student_phone', 'label' => 'Телефон ученика', 'required' => false],
                ['key' => 'spouse_phones', 'label' => 'Супруг(а): телефон', 'required' => false],
                ['key' => 'custom_mobile', 'label' => 'Доп. мобильный', 'required' => false],
                ['key' => 'trusted_person_1_contacts', 'label' => 'Контакты', 'required' => false],
            ],
            ['student_phone', 'spouse_phones', 'custom_mobile', 'trusted_person_1_contacts'],
        );

        $masked = $this->expectedMaskedPhone('79062475508');

        $this->post(route('account.documents.generate', $contract), [
            'fields' => $this->phoneGenerateFields([
                'student_phone'             => '79001112233',
                'spouse_phones'             => '+7 (900) 111-22-33',
                'custom_mobile'             => '8 (912) 345-67-89',
                'trusted_person_1_contacts' => '9062475508, дом 12',
            ]),
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $this->assertSame($masked, $contract->filled_data['parent_phone'] ?? null);
        $this->assertSame('+7 (900) 111-22-33', $contract->filled_data['student_phone'] ?? null);
        $this->assertSame('+7 (912) 345-67-89', $contract->filled_data['custom_mobile'] ?? null);
        $this->assertSame('9062475508, дом 12', $contract->filled_data['trusted_person_1_contacts'] ?? null);

        $filledXml = CapturingFakeContractPdfConverter::$lastDocumentXml;
        $this->assertNotNull($filledXml);
        $this->assertStringContainsString($masked, $filledXml);
        $this->assertStringNotContainsString('{{parent_phone}}', $filledXml);
        $this->assertStringContainsString('9062475508, дом 12', $filledXml);
        $this->assertStringNotContainsString('+7 (906) 247-55-08, дом 12', $filledXml);

        $profile = ParentProfile::query()->find($this->user->fresh()->parent_id);
        $this->assertNotNull($profile);
        $this->assertSame('79062475508', $profile->phone);
    }

    public function test_invalid_phone_text_is_left_as_is_in_pdf(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $this->post(route('account.documents.generate', $contract), [
            'fields' => $this->phoneGenerateFields([
                'parent_phone' => 'не указан',
            ]),
        ])->assertRedirect();

        $contract->refresh();
        $this->assertSame('не указан', $contract->filled_data['parent_phone'] ?? null);
        $this->assertStringContainsString('не указан', (string) CapturingFakeContractPdfConverter::$lastDocumentXml);
        $this->assertStringNotContainsString('+7 (', (string) CapturingFakeContractPdfConverter::$lastDocumentXml);
    }

    public function test_first_open_prefills_parent_phone_with_mask_from_crm_digits(): void
    {
        $this->linkParentWithPhone('79062475508');
        $html = $this->getContractFillModalHtml($this->makeRequiredPhoneFillContract());

        $this->assertFillInputValue($html, 'parent_phone', '+7 (906) 247-55-08');
        $this->assertStringContainsString('js-contract-fill-phone', $html);
        $this->assertStringContainsString('js-phone-mask-unmask', $html);
    }

    public function test_empty_parent_phone_stays_empty_on_first_open(): void
    {
        $this->linkParentWithPhone(null);
        $html = $this->getContractFillModalHtml($this->makeRequiredPhoneFillContract());

        $this->assertFillInputValue($html, 'parent_phone', '');
        $this->assertStringNotContainsString('value="+7 (', $html);
    }

    public function test_reopening_fill_keeps_masked_prefill_and_does_not_drop_to_digits(): void
    {
        $this->linkParentWithPhone('79001112233');
        $contract = $this->makeRequiredPhoneFillContract();

        $first = $this->getContractFillModalHtml($contract);
        $second = $this->getContractFillModalHtml($contract);

        $this->assertFillInputValue($first, 'parent_phone', '+7 (900) 111-22-33');
        $this->assertFillInputValue($second, 'parent_phone', '+7 (900) 111-22-33');
        $this->assertStringNotContainsString('value="79001112233"', $second);
        $this->assertStringNotContainsString('value="9001112233"', $second);
    }

    public function test_edit_mode_shows_mask_for_legacy_ten_digit_filled_data(): void
    {
        $contract = $this->makeDraftEditablePhoneContract([
            'parent_phone' => '9062475508',
        ]);

        $html = $this->getContractFillModalHtml($contract, 'edit');

        $this->assertFillInputValue($html, 'parent_phone', '+7 (906) 247-55-08');
        $this->assertStringNotContainsString('value="9062475508"', $html);
    }

    public function test_documents_index_exposes_fill_and_edit_open_triggers(): void
    {
        $awaiting = $this->makeRequiredPhoneFillContract();
        $draft = $this->makeDraftEditablePhoneContract();

        $html = (string) $this->get(route('account.documents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('js-open-contract-fill', $html);
        $this->assertStringContainsString('data-contract-id="' . $awaiting->id . '"', $html);
        $this->assertStringContainsString('js-open-contract-fill-edit', $html);
        $this->assertStringContainsString('data-contract-id="' . $draft->id . '"', $html);
        $this->assertStringContainsString('jquery.inputmask', $html);
        $this->assertStringContainsString('PhoneInputMask', $html);
    }

    public function test_phone_fields_use_mask_classes_email_and_contacts_do_not(): void
    {
        $contract = $this->makeRequiredPhoneFillContract(
            [
                ['key' => 'parent_email', 'label' => 'Email', 'required' => false],
                ['key' => 'trusted_person_1_contacts', 'label' => 'Контакты', 'required' => false],
                [
                    'key'            => 'student_phone',
                    'label'          => 'Телефон ученика',
                    'required'       => false,
                    'prefill_source' => ContractTemplatePrefillSources::STUDENT_PHONE,
                ],
            ],
            ['parent_email', 'trusted_person_1_contacts', 'student_phone'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertMatchesRegularExpression(
            '/name="fields\[parent_phone]"[^>]*class="[^"]*js-contract-fill-phone/u',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/name="fields\[student_phone]"[^>]*class="[^"]*js-contract-fill-phone/u',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="fields\[parent_email]"[^>]*js-phone-mask/u',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="fields\[trusted_person_1_contacts]"[^>]*js-phone-mask/u',
            $html,
        );
        $this->assertStringContainsString('data-error-for="fields.parent_phone"', $html);
        $this->assertStringContainsString('class="contract-fill-form" novalidate', $html);
    }

    public function test_student_phone_prefill_from_user_is_masked_in_form(): void
    {
        $this->user->forceFill(['phone' => '79112223344'])->save();
        $contract = $this->makeRequiredPhoneFillContract(
            [
                [
                    'key'            => 'student_phone',
                    'label'          => 'Телефон ученика',
                    'required'       => false,
                    'prefill_source' => ContractTemplatePrefillSources::STUDENT_PHONE,
                ],
            ],
            ['student_phone'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertFillInputValue($html, 'student_phone', '+7 (911) 222-33-44');
    }
}
