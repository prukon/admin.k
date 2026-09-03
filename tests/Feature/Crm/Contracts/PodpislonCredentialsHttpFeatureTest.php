<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Services\Signatures\Providers\PodpislonProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * HTTP-отправка в Подпислон: ключ юрлица, блок без ключа, без фоллбэка .env, X-Api-Key.
 *
 * @see /docs/documentation/contracts.html §3.1
 * @see /docs/documentation/admin-legal-entities.html §4.2.3
 */
final class PodpislonCredentialsHttpFeatureTest extends ContractsFeatureTestCase
{
    public function test_send_without_key_returns_422_and_does_not_mark_contract_failed(): void
    {
        Config::set('services.podpislon.key', 'ENV-FALLBACK-MUST-NOT-BE-USED');
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Без ключа HTTP',
            'podpislon_api_key' => null,
            'is_enabled' => true,
        ]);

        $contract = $this->makeUnsignedDraft();

        $this->postJson('/client-contracts/'.$contract->id.'/send', $this->signerPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'podpislon_api_key_missing')
            ->assertJsonValidationErrors(['podpislon_api_key'])
            ->assertJsonPath(
                'errors.podpislon_api_key.0',
                'Для юр. лица «ИП Без ключа HTTP» не задан API-ключ Подпислона. Обратитесь к администратору платформы.'
            );

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNull($contract->legal_entity_id);
        $this->assertSame(0, $contract->signRequests()->count());
        Http::assertNothingSent();
    }

    public function test_second_send_after_missing_key_is_not_blocked_by_sms_cooldown(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Cooldown',
            'is_enabled' => true,
        ]);

        $contract = $this->makeUnsignedDraft();

        $this->postJson('/client-contracts/'.$contract->id.'/send', $this->signerPayload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'podpislon_api_key_missing');

        $again = $this->postJson('/client-contracts/'.$contract->id.'/send', $this->signerPayload());
        $again->assertStatus(422)
            ->assertJsonPath('code', 'podpislon_api_key_missing');
        $this->assertNotSame('sms_cooldown', $again->json('code'));
    }

    public function test_send_with_two_legal_entities_and_unbound_group_returns_422_on_legal_entity_id(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-a',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-b',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $contract = $this->makeUnsignedDraft(['group_id' => null]);

        $this->postJson('/client-contracts/'.$contract->id.'/send', $this->signerPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'legal_entity_unresolved')
            ->assertJsonValidationErrors(['legal_entity_id']);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_guest_cannot_send_contract_for_signature(): void
    {
        $this->seedPodpislonLegalEntity();
        $contract = $this->makeUnsignedDraft();

        Auth::logout();

        $response = $this->postJson('/client-contracts/'.$contract->id.'/send', $this->signerPayload());
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    public function test_status_request_sends_x_api_key_of_snapshotted_legal_entity_not_env(): void
    {
        Config::set('services.podpislon.key', 'ENV-FALLBACK-MUST-NOT-BE-USED');
        Http::fake([
            '*' => Http::response([['status' => 15, 'status_text' => 'sent']], 200),
        ]);

        PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-default-unused',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        $groupEntity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-of-group-company',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $groupEntity->id,
        ]);

        $contract = $this->makeUnsignedDraft([
            'group_id' => $team->id,
            'provider_doc_id' => 'pkg-status-1',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $groupEntity->id,
        ]);

        app(PodpislonProvider::class)->getStatus($contract);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Api-Key', 'key-of-group-company');
        });
        Http::assertNotSent(function ($request) {
            $header = $request->header('X-Api-Key');

            return $header === ['ENV-FALLBACK-MUST-NOT-BE-USED']
                || $header === ['key-default-unused'];
        });
    }

    public function test_status_uses_snapshotted_key_after_legal_entity_is_soft_deleted(): void
    {
        Http::fake([
            '*' => Http::response([['status' => 15, 'status_text' => 'sent']], 200),
        ]);

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-of-deleted-company',
            'is_enabled' => true,
        ]);

        $contract = $this->makeUnsignedDraft([
            'provider_doc_id' => 'pkg-deleted-le',
            'status' => Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        $entity->delete();

        app(PodpislonProvider::class)->getStatus($contract);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Api-Key', 'key-of-deleted-company');
        });
    }

    public function test_resend_without_key_returns_422_and_does_not_fail_contract(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Resend без ключа',
            'is_enabled' => true,
        ]);

        $contract = $this->makeUnsignedDraft([
            'provider_doc_id' => 'pkg-resend-1',
            'status' => Contract::STATUS_SENT,
        ]);

        ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иван Иванов',
            'signer_phone' => '79001112233',
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);

        $this->postJson('/client-contracts/'.$contract->id.'/resend', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'podpislon_api_key_missing')
            ->assertJsonValidationErrors(['podpislon_api_key']);

        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, string>
     */
    private function signerPayload(): array
    {
        return [
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_phone' => '+7 (900) 111-22-33',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUnsignedDraft(array $overrides = []): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256' => str_repeat('e', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => null,
            'status' => Contract::STATUS_DRAFT,
        ], $overrides));
    }
}
