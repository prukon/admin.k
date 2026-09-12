<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\NullHandler;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Native POST повторной SMS без X-Requested-With: 302 + session, не пустой 200 и не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountDocumentsResendSmsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    private const SIGNER_PHONE = '79001112233';

    private const MASKED_PHONE = '+7 (900) ***-**-33';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.channels.podpislon' => [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ],
        ]);
        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_native_resend_expired_redirects_with_success_and_sets_sent(): void
    {
        $this->seedPodpislonLegalEntity();
        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            '*' => Http::response([[
                'status' => 15,
                'status_text' => 'sent',
                'contacts' => [
                    [
                        'phone' => '+79001112233',
                        'link' => self::SAMPLE_URL,
                    ],
                ],
            ]], 200),
        ]);

        $contract = $this->makeResendableContract(Contract::STATUS_EXPIRED);

        $response = $this->from(route('account.documents.index'))
            ->post(route('account.documents.resendSms', $contract), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Успех native resend не должен быть пустым 200');
        $response->assertRedirect(route('account.documents.index'));
        $response->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
        $this->assertSame(self::SIGNER_PHONE, $contract->signRequests()->orderByDesc('id')->value('signer_phone'));

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('SMS отправлена', false)
            ->getContent();

        $this->assertStringContainsString('alert alert-success', $html);
        $this->assertStringContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $html);
        $this->assertStringContainsString(self::MASKED_PHONE, $html);
        $this->assertStringContainsString('Открыть ссылку из SMS', $html);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $html);
    }

    public function test_native_resend_on_draft_redirects_with_session_error(): void
    {
        $path = 'documents/2026/09/resend-native-draft.pdf';
        Storage::disk()->put($path, '%PDF-1.4');
        $contract = Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => $path,
            'source_sha256' => str_repeat('a', 64),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_DRAFT,
        ]);

        $response = $this->from(route('account.documents.index'))
            ->post(route('account.documents.resendSms', $contract), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('account.documents.index'));
        $response->assertSessionHasErrors(['contract']);

        $sessionErrors = session('errors');
        $this->assertNotNull($sessionErrors);
        $this->assertSame('Повторная отправка SMS сейчас недоступна.', $sessionErrors->first('contract'));
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    public function test_guest_native_resend_does_not_change_status(): void
    {
        $this->seedPodpislonLegalEntity();
        $contract = $this->makeResendableContract(Contract::STATUS_EXPIRED);

        Auth::logout();
        $response = $this->post(route('account.documents.resendSms', $contract), [
            '_token' => csrf_token(),
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertSame(Contract::STATUS_EXPIRED, $contract->fresh()->status);
        $this->assertSame(1, $contract->signRequests()->count());
    }

    private function makeResendableContract(string $status = Contract::STATUS_SENT): Contract
    {
        $contract = Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/09/resend-native.pdf',
            'source_sha256' => str_repeat('b', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
            'status' => $status,
        ]);

        ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иванов Иван Иванович',
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone' => self::SIGNER_PHONE,
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);

        return $contract;
    }
}
