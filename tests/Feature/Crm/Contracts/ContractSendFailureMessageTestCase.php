<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Monolog\Handler\NullHandler;

/**
 * Общие фикстуры текста ошибки Подпислона на карточке / в списке / при send.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class ContractSendFailureMessageTestCase extends ContractsFeatureTestCase
{
    protected const PROVIDER_ERROR = 'Не выбран провайдер отправки смс.';

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
     * @param  array<string, mixed>  $overrides
     */
    protected function makeDraftContract(array $overrides = []): Contract
    {
        $student = $overrides['user'] ?? User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        unset($overrides['user']);

        $path = $overrides['source_pdf_path'] ?? ('documents/send-fail/' . uniqid('', true) . '.pdf');
        unset($overrides['source_pdf_path']);

        Storage::put($path, '%PDF-test');

        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'source_pdf_path' => $path,
            'source_sha256' => str_repeat('a', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => null,
            'status' => Contract::STATUS_DRAFT,
        ], $overrides));
    }

    protected function makeFailedContractWithProviderMessage(string $message = self::PROVIDER_ERROR): Contract
    {
        $contract = $this->makeDraftContract([
            'status' => Contract::STATUS_FAILED,
        ]);

        ContractEvent::create([
            'contract_id' => $contract->id,
            'author_id' => $this->user->id,
            'type' => 'failed',
            'payload_json' => json_encode([
                'res' => [
                    'provider_doc_id' => null,
                    'raw' => [
                        'status' => false,
                        'message' => $message,
                        'sessError' => '',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return $contract->fresh();
    }

    protected function fakePodpislonAddDocumentRejected(string $message = self::PROVIDER_ERROR): void
    {
        Http::fake([
            '*add-document*' => Http::response([
                'status' => false,
                'message' => $message,
                'sessError' => '',
            ], 200),
            '*' => Http::response([
                'status' => true,
                'signings' => 22,
                'company' => ['name' => 'Test LE'],
            ], 200),
        ]);
    }

    protected function fakePodpislonAddDocumentRejectedWithoutMessage(): void
    {
        Http::fake([
            '*add-document*' => Http::response([
                'status' => false,
                'sessError' => '',
            ], 200),
            '*' => Http::response([
                'status' => true,
                'signings' => 22,
                'company' => ['name' => 'Test LE'],
            ], 200),
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
     * @return array<string, mixed>|null
     */
    protected function listRowFor(Contract $contract): ?array
    {
        $row = collect($this->getJson('/client-contracts/data?draw=1&start=0&length=20')->json('data'))
            ->firstWhere('id', $contract->id);

        return is_array($row) ? $row : null;
    }

    protected function actingAsContractsViewer(): User
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);
        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        return $actor;
    }
}
