<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Monolog\Handler\NullHandler;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * P1: native POST подписи из кабинета без X-Requested-With пишет URL и редиректит на документы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountDocumentsPodpislonSigningUrlNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

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

    public function test_native_sign_saves_sms_link_redirects_and_card_shows_it(): void
    {
        $this->seedPodpislonLegalEntity();
        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            '*' => Http::response([[
                'status' => 15,
                'status_text' => 'sent',
                'contacts' => [['link' => self::SAMPLE_URL]],
            ]], 200),
        ]);

        $contract = $this->makeDraftReadyToSign();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (Contract $c) {
            $c->provider_doc_id = '971890';
            $c->save();

            return ['ok' => true];
        });
        $this->app->instance(SignatureProvider::class, $provider);

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.sign', $contract), [
                'signer_lastname' => 'Петров',
                'signer_firstname' => 'Пётр',
                'signer_middlename' => 'Петрович',
                'signer_phone' => '+7 (900) 111-22-33',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertRedirect(route('account.documents.index'))
            ->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
        $this->assertSame(self::SAMPLE_URL, $contract->provider_signing_url);

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('Открыть ссылку из SMS', false)
            ->assertSee(self::SAMPLE_URL, false);
    }

    public function test_native_sign_without_lastname_returns_422_and_does_not_save_url(): void
    {
        $this->seedPodpislonLegalEntity();
        $contract = $this->makeDraftReadyToSign();

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.sign', $contract), [
                'signer_firstname' => 'Пётр',
                'signer_phone' => '+7 (900) 111-22-33',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signer_lastname']);

        $this->assertNull($contract->fresh()->provider_signing_url);
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    public function test_guest_native_sign_does_not_save_url(): void
    {
        $this->seedPodpislonLegalEntity();
        $contract = $this->makeDraftReadyToSign();

        Auth::logout();
        $response = $this->post(route('account.documents.sign', $contract), [
            'signer_lastname' => 'Петров',
            'signer_firstname' => 'Пётр',
            'signer_phone' => '+7 (900) 111-22-33',
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);

        $this->assertNull($contract->fresh()->provider_signing_url);
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    private function makeDraftReadyToSign(): Contract
    {
        $contract = $this->makeAwaitingFillContract([
            [
                'key' => 'parent_full_name',
                'label' => 'ФИО родителя',
                'required' => true,
            ],
        ]);

        $contract->update([
            'status' => Contract::STATUS_DRAFT,
            'source_pdf_path' => 'documents/cabinet-sign-url.pdf',
            'source_sha256' => str_repeat('b', 64),
        ]);
        Storage::disk()->put($contract->source_pdf_path, '%PDF-1.4');

        return $contract;
    }
}
