<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\Partner;
use App\Models\PartnerWalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractBillingService
{
    public function createFee(): float
    {
        return (float) (config('billing.contract_create_fee') ?? 70.00);
    }

    public function createFeeCents(): int
    {
        return Money::toCentsOrFail($this->createFee());
    }

    /**
     * Списание за создание договора (PDF или шаблон). Возвращает баланс после списания (в рублях).
     *
     * Баланс меняется как раньше; дополнительно пишется debit в partner_wallet_transactions.
     *
     * @throws ValidationException
     */
    public function chargeCreationFee(Partner $partner, Contract $contract): float
    {
        $feeCents = $this->createFeeCents();
        $actorId = Auth::id();

        DB::transaction(function () use ($partner, $contract, $feeCents, $actorId): void {
            /** @var Partner $locked */
            $locked = Partner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->wallet_balance_cents < $feeCents) {
                throw ValidationException::withMessages([
                    'wallet' => 'Недостаточно средств для создания договора.',
                ]);
            }

            $locked->wallet_balance_cents = (int) $locked->wallet_balance_cents - $feeCents;
            $locked->save();
            Cache::forget("partner_balance_{$locked->id}");

            $tx = PartnerWalletTransaction::query()->create([
                'partner_id' => $locked->id,
                'user_id' => $actorId,
                'type' => 'debit',
                'amount_cents' => $feeCents,
                'currency' => 'RUB',
                'provider' => 'manual',
                'status' => 'succeeded',
                'description' => 'Создание договора',
                'meta' => [
                    'contract_id' => (int) $contract->id,
                    'reason' => 'contract_create',
                ],
            ]);

            $partner->wallet_balance_cents = (int) $locked->wallet_balance_cents;

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => $actorId,
                'type'         => 'balance_charged',
                'payload_json' => json_encode([
                    'amount'        => Money::fromCents($feeCents),
                    'currency'      => 'RUB',
                    'partner_id'    => $locked->id,
                    'balance_after' => Money::fromCents((int) $locked->wallet_balance_cents),
                    'wallet_transaction_id' => (int) $tx->id,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        });

        return ((int) $partner->wallet_balance_cents) / 100;
    }

    /**
     * Возврат 70 ₽ при отзыве договора в статусе awaiting_client_fill.
     * Исходный debit остаётся succeeded (договор успели создать); добавляется credit-возврат.
     */
    public function refundCreationFee(Partner $partner, Contract $contract, ?int $authorId = null): float
    {
        $feeCents = $this->createFeeCents();
        $actorId = $authorId ?? Auth::id();

        DB::transaction(function () use ($partner, $contract, $feeCents, $actorId): void {
            /** @var Partner $locked */
            $locked = Partner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $locked->wallet_balance_cents = (int) $locked->wallet_balance_cents + $feeCents;
            $locked->save();
            Cache::forget("partner_balance_{$locked->id}");

            $originalTxId = PartnerWalletTransaction::query()
                ->where('partner_id', $locked->id)
                ->where('type', 'debit')
                ->where('status', 'succeeded')
                ->where('provider', 'manual')
                ->where('meta->reason', 'contract_create')
                ->where('meta->contract_id', (int) $contract->id)
                ->value('id');

            $creditMeta = [
                'contract_id' => (int) $contract->id,
                'reason' => 'contract_revoke_refund',
            ];
            if ($originalTxId) {
                $creditMeta['original_wallet_transaction_id'] = (int) $originalTxId;
            }

            $tx = PartnerWalletTransaction::query()->create([
                'partner_id' => $locked->id,
                'user_id' => $actorId,
                'type' => 'credit',
                'amount_cents' => $feeCents,
                'currency' => 'RUB',
                'provider' => 'refund',
                'status' => 'succeeded',
                'description' => 'Возврат: отзыв договора',
                'meta' => $creditMeta,
            ]);

            $partner->wallet_balance_cents = (int) $locked->wallet_balance_cents;

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => $actorId,
                'type'         => 'balance_refunded',
                'payload_json' => json_encode([
                    'amount'        => Money::fromCents($feeCents),
                    'currency'      => 'RUB',
                    'partner_id'    => $locked->id,
                    'balance_after' => Money::fromCents((int) $locked->wallet_balance_cents),
                    'reason'        => 'revoke_awaiting_client_fill',
                    'wallet_transaction_id' => (int) $tx->id,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        });

        return ((int) $partner->wallet_balance_cents) / 100;
    }
}
