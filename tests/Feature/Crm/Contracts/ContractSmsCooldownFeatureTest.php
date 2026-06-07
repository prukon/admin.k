<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\User;
use App\Services\Contracts\ContractSmsCooldown;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;

class ContractSmsCooldownFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function send_is_blocked_when_cooldown_cache_is_active(): void
    {
        $contract = $this->createDraftContract();
        $payload = $this->validSendPayload();

        Cache::put(
            ContractSmsCooldown::cacheKey($contract->id),
            time() + ContractSmsCooldown::SECONDS,
            ContractSmsCooldown::SECONDS
        );

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'sms_cooldown')
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['cooldown_sec']);
    }

    /** @test */
    public function send_does_not_create_sign_request_when_cooldown_is_active(): void
    {
        $contract = $this->createDraftContract();

        Cache::put(
            ContractSmsCooldown::cacheKey($contract->id),
            time() + ContractSmsCooldown::SECONDS,
            ContractSmsCooldown::SECONDS
        );

        $before = ContractSignRequest::query()->where('contract_id', $contract->id)->count();

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'sms_cooldown');

        $this->assertSame($before, ContractSignRequest::query()->where('contract_id', $contract->id)->count());
    }

    /** @test */
    public function send_second_request_is_blocked_immediately_after_successful_send(): void
    {
        $contract = $this->createDraftContract();
        $payload = $this->validSendPayload();

        $this->mockSuccessfulFirstSend();

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'sms_cooldown')
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function send_blocked_response_includes_remaining_cooldown_seconds(): void
    {
        $contract = $this->createDraftContract();

        Cache::put(
            ContractSmsCooldown::cacheKey($contract->id),
            time() + 25,
            ContractSmsCooldown::SECONDS
        );

        $response = $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'sms_cooldown');

        $remaining = (int) $response->json('cooldown_sec');
        $this->assertGreaterThanOrEqual(1, $remaining);
        $this->assertLessThanOrEqual(ContractSmsCooldown::SECONDS, $remaining);
        $this->assertStringContainsString((string) $remaining, (string) $response->json('message'));
    }

    /** @test */
    public function send_releases_cooldown_cache_after_provider_does_not_confirm(): void
    {
        $contract = $this->createDraftContract();
        $payload = $this->validSendPayload();

        Http::fake([
            '*' => Http::response([
                [
                    'status'      => 10,
                    'status_text' => 'pending',
                ],
            ], 200),
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Contract $c) {
                $c->provider_doc_id = '1001';
                $c->save();

                return ['ok' => true];
            });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'send_not_sent');

        $this->assertFalse(Cache::has(ContractSmsCooldown::cacheKey($contract->id)));
    }

    /** @test */
    public function send_releases_cooldown_when_provider_throws_allowing_retry(): void
    {
        $contract = $this->createDraftContract();
        $payload = $this->validSendPayload();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Provider unavailable'));
        $this->app->instance(SignatureProvider::class, $provider);

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'send_failed');

        $this->assertFalse(Cache::has(ContractSmsCooldown::cacheKey($contract->id)));

        $this->mockSuccessfulFirstSend();

        $this->postJson(route('contracts.send', $contract), $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function resend_is_blocked_when_cooldown_cache_is_active(): void
    {
        $contract = $this->createSentContractWithSignRequest();

        Cache::put(
            ContractSmsCooldown::cacheKey($contract->id),
            time() + ContractSmsCooldown::SECONDS,
            ContractSmsCooldown::SECONDS
        );

        $before = ContractSignRequest::query()->where('contract_id', $contract->id)->count();

        $this->postJson(route('contracts.resend', $contract), [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'sms_cooldown')
            ->assertJsonPath('success', false);

        $this->assertSame($before, ContractSignRequest::query()->where('contract_id', $contract->id)->count());
    }

    /** @test */
    public function resend_second_request_is_blocked_immediately_after_successful_resend(): void
    {
        $contract = $this->createSentContractWithSignRequest();

        $this->mockSuccessfulResend();

        $this->postJson(route('contracts.resend', $contract), [])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->postJson(route('contracts.resend', $contract), [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'sms_cooldown')
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function resend_releases_cooldown_cache_after_provider_does_not_confirm(): void
    {
        $contract = $this->createSentContractWithSignRequest();

        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            '*'             => Http::response([
                [
                    'status'      => 10,
                    'status_text' => 'pending',
                ],
            ], 200),
        ]);

        $this->postJson(route('contracts.resend', $contract), [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'resend_not_sent');

        $this->assertFalse(Cache::has(ContractSmsCooldown::cacheKey($contract->id)));
    }

    /** @test */
    public function successful_send_sets_cooldown_cache_for_contract(): void
    {
        $contract = $this->createDraftContract();
        $this->mockSuccessfulFirstSend();

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Cache::has(ContractSmsCooldown::cacheKey($contract->id)));
    }

    private function createDraftContract(): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/06/cooldown-draft.pdf',
            'source_sha256'   => str_repeat('c', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => null,
            'status'          => Contract::STATUS_DRAFT,
        ]);
    }

    private function createSentContractWithSignRequest(): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/06/cooldown-sent.pdf',
            'source_sha256'   => str_repeat('d', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-cooldown',
            'status'          => Contract::STATUS_SENT,
        ]);

        ContractSignRequest::create([
            'contract_id'  => $contract->id,
            'signer_name'  => 'Иванов Иван',
            'signer_phone' => '79001112233',
            'ttl_hours'    => 72,
            'status'       => 'sent',
        ]);

        return $contract;
    }

    /**
     * @return array<string, string>
     */
    private function validSendPayload(): array
    {
        return [
            'signer_lastname'   => 'Иванов',
            'signer_firstname'  => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone'      => '+7 (900) 111-22-33',
        ];
    }

    private function mockSuccessfulFirstSend(): void
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
                $c->provider_doc_id = 'pkg-cooldown-first';
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
