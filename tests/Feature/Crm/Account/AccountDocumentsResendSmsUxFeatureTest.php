<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Карточка «Мои документы»: «Отправить SMS ещё раз» при sent/opened/expired и маска номера.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-resend-sms
 */
final class AccountDocumentsResendSmsUxFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    private const SIGNER_PHONE = '79001112233';

    private const MASKED_PHONE = '+7 (900) ***-**-33';

    private const FULL_FORMATTED = '+7 (900) 111-22-33';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_sent_card_shows_resend_button_and_masked_phone(): void
    {
        $sent = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_SENT,
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);
        $this->attachSignRequest($sent);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $card = $this->cardAroundContract($html, $sent->id);
        $this->assertStringContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $card);
        $this->assertStringContainsString('SMS уйдёт на '.self::MASKED_PHONE, $card);
        $this->assertStringContainsString('js-contract-resend-sms-form', $card);
        $this->assertStringContainsString(route('account.documents.resendSms', $sent), $card);
        $this->assertLessThan(
            strpos($card, 'js-contract-resend-sms-form'),
            strpos($card, 'SMS уйдёт на '.self::MASKED_PHONE),
            'Маска должна быть рядом с кнопкой, до формы'
        );
        $this->assertLessThan(
            strpos($card, Contract::CLIENT_RESEND_SMS_BUTTON),
            strpos($card, 'SMS уйдёт на '.self::MASKED_PHONE),
            'Маска должна быть рядом с кнопкой, до submit'
        );
        $this->assertStringContainsString('Открыть ссылку из SMS', $card);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $card);
        $this->assertStringNotContainsString(self::FULL_FORMATTED, $card);
        $this->assertStringNotContainsString('111-22-33', $card);
        $this->assertStringNotContainsString('name="signer_phone"', $card);
        $this->assertStringNotContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $card);
    }

    public function test_opened_card_shows_resend_button_and_masked_phone(): void
    {
        $opened = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_OPENED,
            'provider_doc_id' => '971891',
            'provider_signing_url' => 'https://podpislon.ru/sign/pack/971891/9f1d11872061',
        ]);
        $this->attachSignRequest($opened);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $card = $this->cardAroundContract($html, $opened->id);
        $this->assertStringContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $card);
        $this->assertStringContainsString('SMS уйдёт на '.self::MASKED_PHONE, $card);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $card);
        $this->assertStringNotContainsString(self::FULL_FORMATTED, $html);
    }

    public function test_expired_card_shows_resend_button_mask_and_hides_dead_url(): void
    {
        $expired = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_EXPIRED,
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);
        $this->attachSignRequest($expired);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $card = $this->cardAroundContract($html, $expired->id);
        $this->assertStringContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $card);
        $this->assertStringContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $card);
        $this->assertStringContainsString('SMS уйдёт на '.self::MASKED_PHONE, $card);
        $this->assertStringNotContainsString('Открыть ссылку из SMS', $card);
        $this->assertStringNotContainsString(self::SAMPLE_URL, $card);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $card);
        $this->assertStringNotContainsString(self::FULL_FORMATTED, $card);
    }

    public function test_signed_awaiting_and_missing_phone_hide_resend_button(): void
    {
        $signed = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_SIGNED,
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);
        $this->attachSignRequest($signed);

        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $sentNoSr = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_SENT,
            'provider_doc_id' => '971892',
            'provider_signing_url' => 'https://podpislon.ru/sign/pack/971892/9f1d11872062',
        ]);

        $sentNoDoc = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_SENT,
            'provider_doc_id' => null,
        ]);
        $this->attachSignRequest($sentNoDoc);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $this->cardAroundContract($html, $signed->id));
        $this->assertStringNotContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $this->cardAroundContract($html, $awaiting->id));
        $this->assertStringNotContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $this->cardAroundContract($html, $sentNoSr->id));
        $this->assertStringNotContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $this->cardAroundContract($html, $sentNoDoc->id));
        $this->assertStringNotContainsString(self::MASKED_PHONE, $html);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $html);
        $this->assertStringNotContainsString(self::FULL_FORMATTED, $html);
    }

    public function test_failed_revoked_and_fill_expired_do_not_show_resend_button(): void
    {
        $failed = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_FAILED,
            'provider_doc_id' => '971893',
        ]);
        $this->attachSignRequest($failed);

        $revoked = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_REVOKED,
            'provider_doc_id' => '971894',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);
        $this->attachSignRequest($revoked);

        $fillExpired = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $fillExpired->update(['fill_expires_at' => now()->subMinute()]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('js-contract-resend-sms-form', $this->cardAroundContract($html, $failed->id));
        $this->assertStringNotContainsString('js-contract-resend-sms-form', $this->cardAroundContract($html, $revoked->id));

        $fillCard = $this->cardAroundContract($html, $fillExpired->id);
        $this->assertStringContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $fillCard);
        $this->assertStringNotContainsString('js-contract-resend-sms-form', $fillCard);
        $this->assertStringNotContainsString(self::MASKED_PHONE, $html);
    }

    public function test_mask_uses_last_sign_request_phone_not_the_first(): void
    {
        $contract = $this->makeSentCabinetContract();
        $this->attachSignRequest($contract);
        ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Петров Пётр Петрович',
            'signer_lastname' => 'Петров',
            'signer_firstname' => 'Пётр',
            'signer_middlename' => 'Петрович',
            'signer_phone' => '79002223344',
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $card = $this->cardAroundContract($html, $contract->id);
        $this->assertStringContainsString('SMS уйдёт на +7 (900) ***-**-44', $card);
        $this->assertStringNotContainsString(self::MASKED_PHONE, $card);
        $this->assertStringNotContainsString('79002223344', $card);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $card);
        $this->assertStringNotContainsString('+7 (900) 222-33-44', $card);
    }

    public function test_formatted_phone_in_last_request_is_masked_on_card(): void
    {
        $contract = $this->makeSentCabinetContract();
        ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иванов Иван Иванович',
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone' => '+7 (906) 247-55-08',
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $card = $this->cardAroundContract($html, $contract->id);
        $this->assertStringContainsString('SMS уйдёт на +7 (906) ***-**-08', $card);
        $this->assertStringNotContainsString('247-55-08', $card);
        $this->assertStringNotContainsString('89062475508', $card);
    }

    public function test_whitespace_phone_hides_resend_button(): void
    {
        $contract = $this->makeSentCabinetContract();
        ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иванов Иван Иванович',
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_phone' => '   ',
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'js-contract-resend-sms-form',
            $this->cardAroundContract($html, $contract->id)
        );
    }

    public function test_neighbor_card_without_phone_does_not_inherit_mask(): void
    {
        $withPhone = $this->makeSentCabinetContract([
            'provider_doc_id' => '971890',
        ]);
        $this->attachSignRequest($withPhone);

        $withoutPhone = $this->makeSentCabinetContract([
            'provider_doc_id' => '971891',
            'provider_signing_url' => 'https://podpislon.ru/sign/pack/971891/9f1d11872061',
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $withCard = $this->cardAroundContract($html, $withPhone->id);
        $withoutCard = $this->cardAroundContract($html, $withoutPhone->id);

        $this->assertStringContainsString('SMS уйдёт на '.self::MASKED_PHONE, $withCard);
        $this->assertStringContainsString('js-contract-resend-sms-form', $withCard);
        $this->assertStringNotContainsString('js-contract-resend-sms-form', $withoutCard);
        $this->assertStringNotContainsString(self::MASKED_PHONE, $withoutCard);
    }

    public function test_first_sign_modal_still_asks_for_phone_resend_form_does_not(): void
    {
        $draft = $this->makeDraftEditablePhoneContract();
        $sent = $this->makeSentCabinetContract();
        $this->attachSignRequest($sent);

        $indexHtml = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();
        $sentCard = $this->cardAroundContract($indexHtml, $sent->id);
        $this->assertStringNotContainsString('name="signer_phone"', $sentCard);
        $this->assertStringContainsString('js-contract-resend-sms-form', $sentCard);

        $fillHtml = $this->getContractFillModalHtml($draft);
        $this->assertStringContainsString('name="signer_phone"', $fillHtml);
        $this->assertStringNotContainsString('js-contract-resend-sms-form', $fillHtml);
        $this->assertStringContainsString('Подписать договор (отправить SMS)', $fillHtml);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSentCabinetContract(array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/09/account-resend.pdf',
            'source_sha256' => str_repeat('c', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'signed_pdf_path' => null,
            'signed_at' => null,
        ], $overrides));
    }

    private function attachSignRequest(Contract $contract): ContractSignRequest
    {
        return ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иванов Иван Иванович',
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone' => self::SIGNER_PHONE,
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);
    }

    private function cardAroundContract(string $html, int $id): string
    {
        $marker = 'data-id="'.$id.'"';
        $pos = strpos($html, $marker);
        $this->assertNotFalse($pos, 'Карточка договора #'.$id.' не найдена');

        $prefix = substr($html, 0, $pos);
        $start = strrpos($prefix, 'col-12 col-md-6 col-lg-4');
        $this->assertNotFalse($start, 'Начало карточки #'.$id.' не найдено');

        return substr($html, $start, ($pos + strlen($marker)) - $start);
    }
}
