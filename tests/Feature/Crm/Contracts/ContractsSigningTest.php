<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ContractsSigningTest extends ContractsFeatureTestCase
{
    /** @test */
    public function send_first_time_uses_signature_provider_and_sets_status_sent_when_provider_confirms(): void
    {
        Http::fake([
            // PodpislonProvider::list (pollForSent)
            '*' => Http::response([
                [
                    'status' => 15,
                    'status_text' => 'sent',
                ],
            ], 200),
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => null,
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Contract $c, ContractSignRequest $sr) {
                // Важно: send должен записать provider_doc_id, иначе pollForSent не сможет подтвердить отправку.
                $c->provider_doc_id = 'pkg-1';
                $c->save();
                return ['ok' => true];
            });
        $this->app->instance(SignatureProvider::class, $provider);

        $payload = [
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone' => '+7 (900) 111-22-33',
        ];

        $this->postJson('/client-contracts/' . $contract->id . '/send', $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);

        $this->assertDatabaseHas('contract_sign_requests', [
            'contract_id' => $contract->id,
            'status'      => 'sent',
        ]);
    }

    /** @test */
    public function resend_returns_422_when_no_previous_sign_requests(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('g', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-1',
            'status'          => Contract::STATUS_SENT,
        ]);

        $this->postJson('/client-contracts/' . $contract->id . '/resend', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'no_sign_request');
    }

    /** @test */
    public function resend_uses_repeat_send_and_creates_event_when_confirmed(): void
    {
        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            // list() polling
            '*' => Http::response([
                [
                    'status' => 15,
                    'status_text' => 'sent',
                ],
            ], 200),
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('h', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-1',
            'status'          => Contract::STATUS_SENT,
        ]);

        // предыдущий запрос подписи
        ContractSignRequest::create([
            'contract_id'   => $contract->id,
            'signer_name'   => 'Test',
            'signer_phone'  => '79001112233',
            'ttl_hours'     => 72,
            'status'        => 'sent',
        ]);

        $this->postJson('/client-contracts/' . $contract->id . '/resend', [])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'resend',
        ]);
    }

    /** @test */
    public function status_signed_downloads_and_attaches_signed_pdf_without_real_network(): void
    {
        Storage::fake();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('i', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-1',
            'status'          => Contract::STATUS_SENT,
            'signed_pdf_path' => null,
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn(['status' => 'signed']);
        $provider->shouldReceive('downloadSigned')->once()->andReturn([
            'filename' => 'signed.pdf',
            'content'  => 'PDF-SIGNED',
        ]);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, 'contracts.sync');

        $this->getJson('/client-contracts/' . $contract->id . '/status')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'raw']);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);
        $this->assertNotNull($contract->signed_pdf_path);

        Storage::assertExists($contract->signed_pdf_path);
        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'signed_pdf_saved',
        ]);
    }

    /** @test */
    public function send_email_uses_mail_fake_and_creates_event(): void
    {
        Mail::fake();
        Storage::fake();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('j', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        Storage::put($contract->source_pdf_path, 'PDF');

        $this->postJson('/client-contracts/' . $contract->id . '/send-email', [
            'email'  => 'to@example.test',
            'signed' => false,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'email_sent',
        ]);
    }
}

