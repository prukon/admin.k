<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;

/**
 * Контроль доступа: страница /client-contracts и все endpoint'ы раздела «Договоры»
 * отдают 200 при contracts.view; без права и для гостя — отказ.
 */
final class ContractsPageFullAccessFeatureTest extends ContractsFeatureTestCase
{
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => 'documents/full-access/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
    }

    private function actingAsContractsViewer(bool $withSync = false): User
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);

        if ($withSync) {
            $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);
        }

        return $actor;
    }

    /**
     * @return array<string, string>
     */
    private function validSendPayload(): array
    {
        return [
            'signer_lastname'   => 'Полный',
            'signer_firstname'  => 'Доступ',
            'signer_middlename' => 'Тест',
            'signer_phone'      => '+7 (900) 555-66-77',
        ];
    }

    public function test_guest_is_denied_on_all_client_contracts_endpoints(): void
    {
        Auth::logout();

        foreach ($this->deniedRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                $item['files'] ?? [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_permission_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->deniedRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                $item['files'] ?? [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без contracts.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_contracts_view_all_endpoints_return_200(): void
    {
        Storage::fake();
        Mail::fake();

        $this->actingAsContractsViewer(withSync: true);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Full Access Group',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'name'       => 'FullAccess',
            'lastname'   => 'Student',
            'phone'      => '+79998887766',
        ]);

        $draft = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $team->id,
            'source_pdf_path' => 'documents/full-access/draft.pdf',
            'source_sha256'   => str_repeat('d', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
        Storage::put($draft->source_pdf_path, '%PDF-draft');

        $sent = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $team->id,
            'source_pdf_path' => 'documents/full-access/sent.pdf',
            'source_sha256'   => str_repeat('s', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-full-access',
            'status'          => Contract::STATUS_SENT,
        ]);
        ContractSignRequest::create([
            'contract_id'  => $sent->id,
            'signer_name'  => 'Full Access Student',
            'signer_phone' => '79998887766',
            'ttl_hours'    => 72,
            'status'       => 'sent',
        ]);

        $signedPath = 'documents/full-access/signed.pdf';
        $signed = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $team->id,
            'source_pdf_path' => 'documents/full-access/original-signed.pdf',
            'source_sha256'   => str_repeat('o', 64),
            'signed_pdf_path' => $signedPath,
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-signed-full',
            'status'          => Contract::STATUS_SIGNED,
        ]);
        Storage::put($signedPath, '%PDF-signed');

        $awaitingRevoke = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => null,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);

        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertViewIs('contracts.index')
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('id="createContractModal"', false);

        $this->get(route('contracts.index', ['create' => 1]))->assertOk();
        $this->get(route('contracts.create'))
            ->assertRedirect(route('contracts.index', ['create' => 1]));

        $this->getJson(route('contracts.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('contracts.data', [
            'draw'         => 1,
            'search_value' => 'FullAccess',
            'group_id'     => (string) $team->id,
            'status'       => Contract::STATUS_DRAFT,
        ]))->assertOk();

        $this->getJson(route('contracts.columns-settings.get'))->assertOk();
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'user_name'     => true,
                'user_lastname' => true,
                'team_title'    => true,
                'status_label'  => true,
                'actions'       => true,
            ],
        ])->assertOk();

        $this->getJson(route('logs.data.contract', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson(route('contracts.users.search', ['q' => 'FullAccess']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonStructure(['groups']);

        $this->postJson('/client-contracts/check-balance')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $pdf = UploadedFile::fake()->create('full-access-create.pdf', 20, 'application/pdf');
        $store = $this->post(route('contracts.store'), [
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'user_id'       => $student->id,
            'pdf'           => $pdf,
        ]);
        $store->assertRedirect();
        $this->followRedirects($store)->assertOk();

        $this->get(route('contracts.show', $draft))->assertOk();
        $this->get(route('contracts.downloadOriginal', $draft))->assertOk();
        $this->get(route('contracts.downloadSigned', $signed))->assertOk();

        $this->postJson(route('contracts.sendEmail', $draft), [
            'email'  => 'full-access@example.test',
            'signed' => false,
        ])->assertOk();

        $this->mockSuccessfulSend();
        $this->postJson(route('contracts.send', $draft), $this->validSendPayload())
            ->assertOk();

        $this->mockSuccessfulResend();
        $this->postJson(route('contracts.resend', $sent), [])->assertOk();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn(['status' => 'sent']);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->getJson(route('contracts.status', $sent))
            ->assertOk()
            ->assertJsonStructure(['status']);

        $this->postJson(route('contracts.revoke', $awaitingRevoke), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');
    }

    public function test_admin_all_client_contracts_read_endpoints_return_200(): void
    {
        $this->asAdmin();

        $this->get(route('contracts.create'))
            ->assertRedirect(route('contracts.index', ['create' => 1]));

        foreach ($this->readOnlyRoutesPayload($this->contract) as $item) {
            if ($item['url'] === route('contracts.create')) {
                continue;
            }

            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Админ: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->get(route('contracts.index'))->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, files?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function deniedRoutesPayload(): array
    {
        return array_merge(
            $this->readOnlyRoutesPayload($this->contract),
            [
                [
                    'method' => 'POST',
                    'url'    => route('contracts.columns-settings.save'),
                    'data'   => ['columns' => ['user_name' => true]],
                ],
                [
                    'method' => 'POST',
                    'url'    => '/client-contracts/check-balance',
                    'data'   => [],
                ],
                [
                    'method' => 'POST',
                    'url'    => route('contracts.store'),
                    'data'   => [
                        'creation_mode' => Contract::CREATION_MODE_PDF,
                        'user_id'       => $this->contract->user_id,
                    ],
                    'files'  => [],
                ],
                [
                    'method' => 'GET',
                    'url'    => route('contracts.show', $this->contract),
                    'headers'=> ['HTTP_ACCEPT' => 'text/html'],
                ],
                [
                    'method' => 'POST',
                    'url'    => route('contracts.send', $this->contract),
                    'data'   => $this->validSendPayload(),
                ],
                [
                    'method' => 'POST',
                    'url'    => route('contracts.resend', $this->contract),
                    'data'   => [],
                ],
                [
                    'method' => 'POST',
                    'url'    => route('contracts.revoke', $this->contract),
                    'data'   => [],
                ],
                [
                    'method' => 'GET',
                    'url'    => route('contracts.status', $this->contract),
                ],
                [
                    'method' => 'POST',
                    'url'    => route('contracts.sendEmail', $this->contract),
                    'data'   => ['email' => 'denied@example.test'],
                ],
                [
                    'method'  => 'GET',
                    'url'     => route('contracts.downloadOriginal', $this->contract),
                    'headers' => ['HTTP_ACCEPT' => 'application/octet-stream'],
                ],
                [
                    'method'  => 'GET',
                    'url'     => route('contracts.downloadSigned', $this->contract),
                    'headers' => ['HTTP_ACCEPT' => 'application/octet-stream'],
                ],
            ]
        );
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function readOnlyRoutesPayload(Contract $contract): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('contracts.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('contracts.create'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('contracts.data', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url'    => route('contracts.columns-settings.get'),
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.contract', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url'    => route('contracts.users.search', ['q' => 'test']),
            ],
            [
                'method' => 'GET',
                'url'    => route('contracts.user.group', ['user_id' => $contract->user_id]),
            ],
        ];
    }

    private function mockSuccessfulSend(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'status'      => 15,
                    'status_text' => 'sent',
                ],
            ], 200),
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')
            ->andReturnUsing(function (Contract $c) {
                $c->provider_doc_id = 'pkg-full-access-send';
                $c->save();

                return ['ok' => true];
            });
        $this->app->instance(SignatureProvider::class, $provider);
    }

    private function mockSuccessfulResend(): void
    {
        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            '*'             => Http::response([
                [
                    'status'      => 15,
                    'status_text' => 'sent',
                ],
            ], 200),
        ]);
    }
}
