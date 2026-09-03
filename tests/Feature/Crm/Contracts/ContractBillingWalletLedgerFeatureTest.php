<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\PartnerWalletTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Списание/возврат 70 ₽ за договор пишут partner_wallet_transactions, баланс как раньше.
 *
 * @see /docs/documentation/partner-wallet.html
 * @see /docs/documentation/contracts.html
 */
final class ContractBillingWalletLedgerFeatureTest extends ContractsFeatureTestCase
{
    public function test_store_pdf_writes_debit_wallet_transaction_for_current_partner(): void
    {
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Storage::fake();
        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $this->post('/client-contracts', [
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'user_id' => $student->id,
            'pdf' => $pdf,
        ])->assertStatus(302);

        $contract = Contract::query()->firstOrFail();
        $this->partner->refresh();
        $this->assertSame(3000, (int) $this->partner->wallet_balance_cents);

        $this->assertSame(1, (int) PartnerWalletTransaction::query()->count());
        $this->assertDatabaseHas('partner_wallet_transactions', [
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'debit',
            'amount_cents' => 7000,
            'provider' => 'manual',
            'status' => 'succeeded',
            'description' => 'Создание договора',
        ]);

        $tx = PartnerWalletTransaction::query()->firstOrFail();
        $this->assertSame((int) $contract->id, (int) ($tx->meta['contract_id'] ?? 0));
        $this->assertSame('contract_create', $tx->meta['reason'] ?? null);
        $this->assertSame(0, (int) PartnerWalletTransaction::query()
            ->where('partner_id', $this->foreignPartner->id)
            ->count());
    }

    public function test_insufficient_balance_does_not_create_wallet_transaction(): void
    {
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Storage::fake();
        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $this->from(route('contracts.index', ['create' => 1]))
            ->post('/client-contracts', [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id' => $student->id,
                'pdf' => $pdf,
            ])
            ->assertStatus(302);

        $this->assertSame(0, (int) Contract::query()->count());
        $this->assertSame(0, (int) PartnerWalletTransaction::query()->count());
        $this->partner->refresh();
        $this->assertSame(0, (int) $this->partner->wallet_balance_cents);
    }

    public function test_revoke_awaiting_client_fill_writes_credit_and_keeps_original_debit(): void
    {
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'email' => 'ledger-revoke@example.com',
        ]);

        $template = $this->createContractTemplateWithVersion();

        $this->post('/client-contracts', [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'user_id' => $student->id,
            'contract_template_id' => $template->id,
        ])->assertStatus(302);

        $contract = Contract::query()->firstOrFail();
        $debit = PartnerWalletTransaction::query()->where('type', 'debit')->firstOrFail();

        $this->postJson('/client-contracts/'.$contract->id.'/revoke')
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $this->partner->refresh();
        $this->assertSame(10000, (int) $this->partner->wallet_balance_cents);

        $debit->refresh();
        $this->assertSame('succeeded', $debit->status);

        $this->assertDatabaseHas('partner_wallet_transactions', [
            'partner_id' => $this->partner->id,
            'type' => 'credit',
            'amount_cents' => 7000,
            'provider' => 'refund',
            'status' => 'succeeded',
            'description' => 'Возврат: отзыв договора',
        ]);

        $credit = PartnerWalletTransaction::query()->where('type', 'credit')->firstOrFail();
        $this->assertSame((int) $contract->id, (int) ($credit->meta['contract_id'] ?? 0));
        $this->assertSame('contract_revoke_refund', $credit->meta['reason'] ?? null);
        $this->assertSame((int) $debit->id, (int) ($credit->meta['original_wallet_transaction_id'] ?? 0));
        $this->assertSame(2, (int) PartnerWalletTransaction::query()->count());
    }
}
