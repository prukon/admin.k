<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;

/**
 * Доступ к странице договора /client-contracts/{id} и связанным AJAX-эндпоинтам.
 */
class ContractShowFullAccessFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    /** @test */
    public function guest_cannot_access_contract_show_and_signing_endpoints(): void
    {
        $contract = $this->createDraftContractForShow();

        Auth::logout();

        $this->get(route('contracts.show', $contract))->assertStatus(302);
        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())->assertStatus(401);
        $this->postJson(route('contracts.resend', $contract), [])->assertStatus(401);
        $this->postJson(route('contracts.sendEmail', $contract), ['email' => 'a@test.test'])->assertStatus(401);
        $this->postJson(route('contracts.revoke', $contract), [])->assertStatus(401);
        $this->getJson(route('contracts.status', $contract))->assertStatus(401);
        $this->get(route('contracts.downloadOriginal', $contract))->assertStatus(302);
        $this->get(route('contracts.downloadSigned', $contract))->assertStatus(302);
    }

    /** @test */
    public function show_and_signing_endpoints_forbidden_without_contracts_view(): void
    {
        Storage::fake();
        $contract = $this->createDraftContractForShow();
        Storage::put($contract->source_pdf_path, '%PDF-test');

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.show', $contract))->assertStatus(403);
        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())->assertStatus(403);
        $this->postJson(route('contracts.resend', $contract), [])->assertStatus(403);
        $this->postJson(route('contracts.sendEmail', $contract), ['email' => 'a@test.test'])->assertStatus(403);
        $this->postJson(route('contracts.revoke', $contract), [])->assertStatus(403);
        $this->getJson(route('contracts.status', $contract))->assertStatus(403);
        $this->get(route('contracts.downloadOriginal', $contract))->assertStatus(403);
        $this->get(route('contracts.downloadSigned', $contract))->assertStatus(403);
    }

    /** @test */
    public function show_page_forbidden_for_contract_of_foreign_partner(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $foreignContract = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $foreignStudent->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/foreign/show.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $this->get(route('contracts.show', $foreignContract))->assertStatus(403);
        $this->postJson(route('contracts.send', $foreignContract), $this->validSendPayload())->assertStatus(403);
    }

    /** @test */
    public function show_page_returns_200_with_contracts_view(): void
    {
        $contract = $this->createDraftContractForShow();

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertViewIs('contracts.show')
            ->assertViewHas('contract');
    }

    /** @test */
    public function show_page_contains_sms_modal_with_cooldown_ui(): void
    {
        $contract = $this->createDraftContractForShow();

        $html = $this->get(route('contracts.show', $contract))->getContent();

        $this->assertStringContainsString('id="sendModal"', $html);
        $this->assertStringContainsString('id="sendSubmit"', $html);
        $this->assertStringContainsString('id="sendCooldownTimer"', $html);
        $this->assertStringContainsString('id="openSendModal"', $html);
    }

    /** @test */
    public function user_with_contracts_view_can_call_all_show_page_endpoints(): void
    {
        Storage::fake();
        Mail::fake();

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $draft = $this->createDraftContractForShow($student);
        Storage::put($draft->source_pdf_path, '%PDF-draft');

        $sent = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/show/sent.pdf',
            'source_sha256'   => str_repeat('s', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-show',
            'status'          => Contract::STATUS_SENT,
        ]);
        ContractSignRequest::create([
            'contract_id'  => $sent->id,
            'signer_name'  => 'Тест Тест',
            'signer_phone' => '79001112233',
            'ttl_hours'    => 72,
            'status'       => 'sent',
        ]);

        $signedPath = 'documents/show/signed.pdf';
        $signed = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/show/original-signed.pdf',
            'source_sha256'   => str_repeat('o', 64),
            'signed_pdf_path' => $signedPath,
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-signed',
            'status'          => Contract::STATUS_SIGNED,
        ]);
        Storage::put($signedPath, '%PDF-signed');

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();

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

        $this->get(route('contracts.show', $draft))->assertOk();
        $this->get(route('contracts.show', $sent))->assertOk();
        $this->get(route('contracts.show', $signed))->assertOk();

        $this->get(route('contracts.downloadOriginal', $draft))->assertOk();
        $this->get(route('contracts.downloadSigned', $signed))->assertOk();

        $this->postJson(route('contracts.sendEmail', $draft), [
            'email'  => 'show-access@example.test',
            'signed' => false,
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson(route('contracts.sendEmail', $signed), [
            'email'  => 'show-access@example.test',
            'signed' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $this->mockSuccessfulSend();
        $this->postJson(route('contracts.send', $draft), $this->validSendPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->mockSuccessfulResend();
        $this->postJson(route('contracts.resend', $sent), [])
            ->assertOk()
            ->assertJsonPath('success', true);

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

    private function createDraftContractForShow(?User $student = null): Contract
    {
        $student ??= User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => 'documents/show/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validSendPayload(): array
    {
        return [
            'signer_lastname'   => 'Смирнов',
            'signer_firstname'  => 'Сергей',
            'signer_middlename' => 'Сергеевич',
            'signer_phone'      => '+7 (900) 333-44-55',
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
                $c->provider_doc_id = 'pkg-show-send';
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
