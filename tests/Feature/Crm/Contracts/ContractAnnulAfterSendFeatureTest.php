<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\PartnerWalletTransaction;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Mockery;

final class ContractAnnulAfterSendFeatureTest extends ContractsFeatureTestCase
{
    private function makeContract(array $overrides = []): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create(array_merge([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'creation_mode'   => Contract::CREATION_MODE_TEMPLATE,
            'source_pdf_path' => 'documents/annul/source.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-annul-' . uniqid('', true),
            'status'          => Contract::STATUS_SENT,
        ], $overrides));
    }

    public function test_guest_cannot_annul(): void
    {
        $contract = $this->makeContract();

        Auth::logout();

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertStatus(401);
    }

    public function test_annul_forbidden_without_contracts_view(): void
    {
        $contract = $this->makeContract();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->postJson(route('contracts.revoke', $contract), [])
            ->assertStatus(403);
    }

    public function test_annul_forbidden_for_foreign_partner_contract(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $foreign = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $foreignStudent->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/annul/foreign.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-foreign',
            'status'          => Contract::STATUS_OPENED,
        ]);

        $this->postJson(route('contracts.revoke', $foreign), [])
            ->assertStatus(403);
    }

    public function test_annul_sent_sets_revoked_without_refund_and_without_provider(): void
    {
        $this->partner->wallet_balance_cents = 12345;
        $this->partner->save();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('revoke')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $contract = $this->makeContract(['status' => Contract::STATUS_SENT]);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('message', 'Договор аннулирован. 70 ₽ не возвращаются.');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);

        $this->partner->refresh();
        $this->assertSame(12345, (int) $this->partner->wallet_balance_cents);
        $this->assertSame(0, (int) PartnerWalletTransaction::query()->count());

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'revoked',
        ]);

        $event = ContractEvent::query()
            ->where('contract_id', $contract->id)
            ->where('type', 'revoked')
            ->latest('id')
            ->first();
        $this->assertNotNull($event);
        $payload = json_decode((string) $event->payload_json, true);
        $this->assertFalse($payload['refunded'] ?? true);
        $this->assertSame('annul_after_send', $payload['reason'] ?? null);
    }

    public function test_annul_opened_sets_revoked_without_refund(): void
    {
        $this->partner->wallet_balance_cents = 8000;
        $this->partner->save();

        $contract = $this->makeContract(['status' => Contract::STATUS_OPENED]);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);

        $this->partner->refresh();
        $this->assertSame(8000, (int) $this->partner->wallet_balance_cents);
        $this->assertSame(0, (int) PartnerWalletTransaction::query()->where('type', 'credit')->count());
    }

    public function test_annul_does_not_apply_to_signed(): void
    {
        $contract = $this->makeContract(['status' => Contract::STATUS_SIGNED]);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertStatus(422);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);
    }

    public function test_second_annul_of_already_revoked_does_not_succeed_as_annul(): void
    {
        $contract = $this->makeContract(['status' => Contract::STATUS_SENT]);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertStatus(422);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }
}
