<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Enums\AuditEvent;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\MyLog;
use App\Models\PartnerWalletTransaction;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Mockery;

final class ContractRevokeDraftFeatureTest extends ContractsFeatureTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDraft(array $overrides = []): Contract
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
            'source_pdf_path' => 'documents/draft-revoke/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('d', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
            'created_at'      => now(),
        ], $overrides));
    }

    public function test_template_draft_is_revoked_without_refund_and_without_provider(): void
    {
        $this->partner->wallet_balance_cents = 15000;
        $this->partner->save();
        $creditsBefore = (int) PartnerWalletTransaction::query()->where('type', 'credit')->count();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('revoke')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $contract = $this->makeDraft();
        $this->assertTrue($contract->canClientSign());
        $this->assertTrue($contract->canClientEditFilledData());

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('message', 'Договор отозван. 70 ₽ не возвращаются.');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
        $this->assertFalse($contract->canClientSign());
        $this->assertFalse($contract->canClientEditFilledData());

        $this->partner->refresh();
        $this->assertSame(15000, (int) $this->partner->wallet_balance_cents);
        $this->assertSame($creditsBefore, (int) PartnerWalletTransaction::query()->where('type', 'credit')->count());

        $event = ContractEvent::query()
            ->where('contract_id', $contract->id)
            ->where('type', 'revoked')
            ->latest('id')
            ->first();
        $this->assertNotNull($event);
        $payload = json_decode((string) $event->payload_json, true);
        $this->assertFalse($payload['refunded'] ?? true);
        $this->assertSame('revoke_draft', $payload['reason'] ?? null);

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::ContractRevoked->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Договор отозван.', (string) $log->description);
        $this->assertStringContainsString('Возврат 70 ₽: Нет', (string) $log->description);
    }

    public function test_pdf_draft_is_revoked_the_same_way(): void
    {
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('revoke')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $contract = $this->makeDraft([
            'creation_mode' => Contract::CREATION_MODE_PDF,
        ]);
        $this->assertTrue($contract->canAdminSendSms());

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('message', 'Договор отозван. 70 ₽ не возвращаются.');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
        $this->assertFalse($contract->canAdminSendSms());
    }

    public function test_second_revoke_of_draft_does_not_succeed(): void
    {
        $contract = $this->makeDraft();

        $this->postJson(route('contracts.revoke', $contract), [])->assertOk();

        $this->postJson(route('contracts.revoke', $contract), [])->assertStatus(422);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }

    public function test_generating_pdf_is_not_revoked_as_draft(): void
    {
        $contract = $this->makeDraft([
            'status' => Contract::STATUS_GENERATING_PDF,
            'source_pdf_path' => null,
            'source_sha256' => null,
        ]);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_GENERATING_PDF, $contract->status);
    }

    public function test_guest_cannot_revoke_draft(): void
    {
        $contract = $this->makeDraft();
        Auth::logout();

        $this->postJson(route('contracts.revoke', $contract), [])->assertStatus(401);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
    }

    public function test_revoke_draft_forbidden_without_contracts_view(): void
    {
        $contract = $this->makeDraft();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->postJson(route('contracts.revoke', $contract), [])
            ->assertStatus(403);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
    }

    public function test_revoke_draft_forbidden_for_foreign_partner(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);
        $foreign = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $foreignStudent->id,
            'group_id'        => null,
            'creation_mode'   => Contract::CREATION_MODE_TEMPLATE,
            'source_pdf_path' => 'documents/draft-revoke/foreign.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $this->postJson(route('contracts.revoke', $foreign), [])->assertStatus(403);

        $foreign->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $foreign->status);
    }

    public function test_native_post_revokes_draft_and_is_not_empty(): void
    {
        $contract = $this->makeDraft();

        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }

    public function test_template_draft_card_shows_revoke_button(): void
    {
        $contract = $this->makeDraft();

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();

        $this->assertStringContainsString('id="revokeDraftBtn"', $html);
        $this->assertStringContainsString('Отозвать', $html);
        $this->assertStringContainsString('70 ₽ не возвращаются', $html);
        $this->assertStringNotContainsString('id="annulAfterSendBtn"', $html);
        $this->assertStringNotContainsString('id="revokeAwaitingBtn"', $html);
        $this->assertStringNotContainsString('id="openSendModal"', $html);
    }

    public function test_pdf_draft_card_shows_revoke_and_send_sms(): void
    {
        $contract = $this->makeDraft([
            'creation_mode' => Contract::CREATION_MODE_PDF,
        ]);

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();

        $this->assertStringContainsString('id="revokeDraftBtn"', $html);
        $this->assertStringContainsString('id="openSendModal"', $html);
        $this->assertStringNotContainsString('id="annulAfterSendBtn"', $html);
    }

    public function test_awaiting_fill_card_does_not_show_draft_revoke_button(): void
    {
        $contract = $this->makeDraft([
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'source_pdf_path' => null,
            'source_sha256' => null,
            'fill_expires_at' => now()->addDays(7),
        ]);

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();

        $this->assertStringContainsString('id="revokeAwaitingBtn"', $html);
        $this->assertStringNotContainsString('id="revokeDraftBtn"', $html);
    }

    public function test_after_revoke_card_hides_button_and_shows_revoked_badge(): void
    {
        $contract = $this->makeDraft([
            'creation_mode' => Contract::CREATION_MODE_PDF,
        ]);

        $this->postJson(route('contracts.revoke', $contract), [])->assertOk();

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="revokeDraftBtn"', $html);
        $this->assertStringNotContainsString('id="openSendModal"', $html);
        $this->assertStringContainsString('Отозвано', $html);
    }
}
