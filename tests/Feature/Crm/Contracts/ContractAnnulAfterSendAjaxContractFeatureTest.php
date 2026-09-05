<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\PartnerWalletTransaction;

/**
 * P1: AJAX-контракт POST /client-contracts/{id}/revoke — JSON 200/422, message, без пустого 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractAnnulAfterSendAjaxContractFeatureTest extends ContractAnnulAfterSendTestCase
{
    public function test_ajax_annul_opened_returns_json_message_and_revokes_row(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('message', 'Договор аннулирован. 70 ₽ не возвращаются.')
            ->assertJsonStructure(['status', 'message']);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }

    public function test_ajax_annul_pdf_mode_sent_works_like_template(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract([
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'status' => Contract::STATUS_SENT,
        ]);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }

    public function test_ajax_awaiting_fill_still_refunds_and_does_not_use_annul_message(): void
    {
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 3000;
        $this->partner->save();
        $this->actingAsContractsViewer();

        $contract = $this->makeAnnulContract([
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'source_pdf_path' => null,
            'source_sha256' => null,
            'provider_doc_id' => null,
            'fill_expires_at' => now()->addDays(7),
        ]);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('message', 'Договор отозван. Средства возвращены на баланс партнёра.');

        $this->partner->refresh();
        $this->assertSame(10000, (int) $this->partner->wallet_balance_cents);
        $this->assertSame(1, (int) PartnerWalletTransaction::query()->where('type', 'credit')->count());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function statusesThatCannotBeAnnulled(): array
    {
        return [
            'draft' => [Contract::STATUS_DRAFT],
            'generating_pdf' => [Contract::STATUS_GENERATING_PDF],
            'signed' => [Contract::STATUS_SIGNED],
            'expired' => [Contract::STATUS_EXPIRED],
            'failed' => [Contract::STATUS_FAILED],
            'revoked' => [Contract::STATUS_REVOKED],
        ];
    }

    /**
     * @dataProvider statusesThatCannotBeAnnulled
     */
    public function test_ajax_annul_of_wrong_status_returns_422_with_message_and_keeps_status(string $status): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract(['status' => $status]);

        $response = $this->postJson(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
        $this->assertNotSame('', trim((string) $response->json('message')));

        $contract->refresh();
        $this->assertSame($status, $contract->status);
    }
}
