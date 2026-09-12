<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Services\Contracts\ContractSmsCooldown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\NullHandler;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX POST повторной SMS из кабинета: успех, cooldown, запрет смены номера.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-resend-sms
 */
final class AccountDocumentsResendSmsAjaxContractFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;
    use InteractsWithFamilyAccountDocuments;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    private const SIGNER_PHONE = '79001112233';

    private const ATTACKER_PHONE = '79998887766';

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

    public function test_ajax_resend_expired_returns_200_and_sets_sent(): void
    {
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonResend();
        $contract = $this->makeResendableContract(Contract::STATUS_EXPIRED);

        $this->postJson(route('account.documents.resendSms', $contract), [], $this->contractFillAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'SMS отправлена')
            ->assertJsonPath('status', Contract::STATUS_SENT);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
        $this->assertSame(self::SAMPLE_URL, $contract->provider_signing_url);
        $this->assertSame(2, $contract->signRequests()->count());
        $this->assertSame(self::SIGNER_PHONE, $contract->signRequests()->orderByDesc('id')->value('signer_phone'));
    }

    public function test_ajax_resend_cooldown_returns_422_without_new_sign_request(): void
    {
        $contract = $this->makeResendableContract();
        Cache::put(
            ContractSmsCooldown::cacheKey($contract->id),
            time() + ContractSmsCooldown::SECONDS,
            ContractSmsCooldown::SECONDS
        );

        $before = $contract->signRequests()->count();

        $json = $this->postJson(route('account.documents.resendSms', $contract), [], $this->contractFillAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('code', 'sms_cooldown')
            ->json();

        $this->assertStringContainsString('через', (string) data_get($json, 'errors.contract.0'));

        $this->assertSame($before, $contract->signRequests()->count());
        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
    }

    public function test_ajax_resend_rejects_posted_phone_and_does_not_create_request(): void
    {
        $contract = $this->makeResendableContract();
        $before = $contract->signRequests()->count();

        $this->postJson(route('account.documents.resendSms', $contract), [
            'signer_phone' => self::ATTACKER_PHONE,
        ], $this->contractFillAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['signer_phone']);

        $this->assertSame($before, $contract->signRequests()->count());
        $this->assertSame(self::SIGNER_PHONE, $contract->signRequests()->orderByDesc('id')->value('signer_phone'));
        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
    }

    public function test_ajax_resend_on_draft_returns_422_unavailable(): void
    {
        $path = 'documents/2026/09/resend-draft.pdf';
        Storage::disk()->put($path, '%PDF-1.4');
        $contract = Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => $path,
            'source_sha256' => str_repeat('e', 64),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_DRAFT,
        ]);

        $this->postJson(route('account.documents.resendSms', $contract), [], $this->contractFillAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.contract.0', 'Повторная отправка SMS сейчас недоступна.');
    }

    public function test_guest_ajax_resend_is_401(): void
    {
        $contract = $this->makeResendableContract();
        Auth::logout();

        $this->postJson(route('account.documents.resendSms', $contract), [], $this->contractFillAjaxHeaders())
            ->assertUnauthorized();
    }

    public function test_user_without_documents_view_gets_403_on_ajax_resend(): void
    {
        $contract = $this->makeResendableContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->postJson(route('account.documents.resendSms', $contract), [], $this->contractFillAjaxHeaders())
            ->assertForbidden();
    }

    public function test_sibling_ajax_resend_succeeds_for_brother_contract(): void
    {
        $this->seedFamilyStudents();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonResend();

        $contract = $this->makeContractFor($this->brother2, Contract::STATUS_EXPIRED, [
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
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

        $this->actingAsBrother1($this->brother2->id)
            ->postJson(route('account.documents.resendSms', $contract), [], $this->contractFillAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('status', Contract::STATUS_SENT);

        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
        $this->assertSame(self::SIGNER_PHONE, $contract->signRequests()->orderByDesc('id')->value('signer_phone'));
    }

    private function makeResendableContract(string $status = Contract::STATUS_SENT): Contract
    {
        $contract = Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/09/resend-ajax.pdf',
            'source_sha256' => str_repeat('f', 64),
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

    private function fakePodpislonResend(?string $link = self::SAMPLE_URL): void
    {
        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            '*' => Http::response([[
                'status' => 15,
                'status_text' => 'sent',
                'contacts' => [
                    [
                        'phone' => '+79001112233',
                        'link' => $link,
                    ],
                ],
            ]], 200),
        ]);
    }
}
