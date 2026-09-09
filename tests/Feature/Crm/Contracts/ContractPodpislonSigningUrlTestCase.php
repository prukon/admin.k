<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Http;
use Mockery;
use Monolog\Handler\NullHandler;

/**
 * Общие фикстуры ссылки Подпислона из SMS (provider_signing_url).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class ContractPodpislonSigningUrlTestCase extends ContractsFeatureTestCase
{
    protected const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.channels.podpislon' => [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validSendPayload(): array
    {
        return [
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone' => '+7 (900) 111-22-33',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function sentDocumentPayload(?string $link = self::SAMPLE_URL): array
    {
        return [[
            'status' => 15,
            'status_text' => 'sent',
            'contacts' => [
                [
                    'phone' => '+79001112233',
                    'link' => $link,
                ],
            ],
        ]];
    }

    protected function fakePodpislonHttpWithSigningUrl(?string $link = self::SAMPLE_URL): void
    {
        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            '*' => Http::response($this->sentDocumentPayload($link), 200),
        ]);
    }

    protected function mockSignatureProviderSend(string $providerDocId = '971890'): void
    {
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Contract $c) use ($providerDocId) {
                $c->provider_doc_id = $providerDocId;
                $c->save();

                return ['ok' => true];
            });
        $this->app->instance(SignatureProvider::class, $provider);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeContract(array $overrides = []): Contract
    {
        $student = $overrides['user'] ?? User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        unset($overrides['user']);

        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'source_pdf_path' => 'documents/signing-url/' . uniqid('', true) . '.pdf',
            'source_sha256' => str_repeat('a', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => null,
            'status' => Contract::STATUS_DRAFT,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSentWithSigningUrl(array $overrides = []): Contract
    {
        return $this->makeContract(array_merge([
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
            'status' => Contract::STATUS_SENT,
        ], $overrides));
    }

    protected function attachSignRequest(Contract $contract): ContractSignRequest
    {
        return ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иванов Иван Иванович',
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone' => '79001112233',
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);
    }

    protected function actingAsContractsViewer(): User
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);
        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        return $actor;
    }

    protected function grantContractsSync(User $actor): void
    {
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);
    }
}
