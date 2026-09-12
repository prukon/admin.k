<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Карточка «Мои документы»: просрочка заполнения и SMS — бейдж, alert, без кнопок.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-expiry-notice
 */
final class AccountDocumentsExpiryNoticeUxFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_fill_expired_card_shows_badge_alert_and_hides_fill_button(): void
    {
        $expired = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $expired->update(['fill_expires_at' => now()->subMinute()]);

        $live = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $expiredCard = $this->cardAroundContract($html, $expired->id);
        $this->assertStringContainsString(Contract::CLIENT_FILL_EXPIRED_BADGE, $expiredCard);
        $this->assertStringContainsString('badge bg-warning text-dark', $expiredCard);
        $this->assertStringContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $expiredCard);
        $this->assertStringContainsString('alert alert-warning', $expiredCard);
        $this->assertStringNotContainsString('Требуется заполнение', $expiredCard);
        $this->assertStringNotContainsString('Заполнить договор', $expiredCard);
        $this->assertStringNotContainsString('js-open-contract-fill', $expiredCard);
        $this->assertStringNotContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $expiredCard);

        $liveCard = $this->cardAroundContract($html, $live->id);
        $this->assertStringContainsString('Требуется заполнение', $liveCard);
        $this->assertStringContainsString('Заполнить договор', $liveCard);
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_EXPIRED_BADGE, $liveCard);
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $liveCard);
    }

    public function test_sms_expired_card_shows_notice_and_hides_dead_signing_url(): void
    {
        $expired = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_EXPIRED,
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $sent = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_SENT,
            'provider_doc_id' => '971891',
            'provider_signing_url' => 'https://podpislon.ru/sign/pack/971891/9f1d11872061',
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $expiredCard = $this->cardAroundContract($html, $expired->id);
        $this->assertStringContainsString('Истёк срок', $expiredCard);
        $this->assertStringContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $expiredCard);
        $this->assertStringContainsString('alert alert-warning', $expiredCard);
        $this->assertStringNotContainsString('Открыть ссылку из SMS', $expiredCard);
        $this->assertStringNotContainsString(self::SAMPLE_URL, $expiredCard);
        $this->assertStringNotContainsString('Заполнить договор', $expiredCard);
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $expiredCard);
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_EXPIRED_BADGE, $expiredCard);
        $this->assertStringNotContainsString('js-contract-resend-sms-form', $expiredCard);

        $sentCard = $this->cardAroundContract($html, $sent->id);
        $this->assertStringContainsString('Открыть ссылку из SMS', $sentCard);
        $this->assertStringContainsString('https://podpislon.ru/sign/pack/971891/9f1d11872061', $sentCard);
        $this->assertStringNotContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $sentCard);
        $this->assertStringNotContainsString('js-contract-resend-sms-form', $sentCard);
    }

    public function test_signed_contract_does_not_show_expiry_notice(): void
    {
        $this->makeSentCabinetContract([
            'status' => Contract::STATUS_SIGNED,
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('Открыть ссылку из SMS', false)
            ->getContent();

        $this->assertStringNotContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $html);
        $this->assertStringNotContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $html);
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_EXPIRED_BADGE, $html);
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
            'source_pdf_path' => 'documents/2026/09/account.pdf',
            'source_sha256' => str_repeat('c', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => '971890',
            'status' => Contract::STATUS_SENT,
            'signed_pdf_path' => null,
            'signed_at' => null,
        ], $overrides));
    }

    private function cardAroundContract(string $html, int $id): string
    {
        $marker = 'data-id="' . $id . '"';
        $pos = strpos($html, $marker);
        $this->assertNotFalse($pos, 'Карточка договора #' . $id . ' не найдена');

        $prefix = substr($html, 0, $pos);
        $start = strrpos($prefix, 'col-12 col-md-6 col-lg-4');
        $this->assertNotFalse($start, 'Начало карточки #' . $id . ' не найдено');

        return substr($html, $start, ($pos + strlen($marker)) - $start);
    }
}
