<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\Partner;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
     * @throws ValidationException
     */
    public function chargeCreationFee(Partner $partner, Contract $contract): float
    {
        $feeCents = $this->createFeeCents();

        if ((int) $partner->wallet_balance_cents < $feeCents) {
            throw ValidationException::withMessages([
                'wallet' => 'Недостаточно средств для создания договора.',
            ]);
        }

        $partner->wallet_balance_cents = (int) $partner->wallet_balance_cents - $feeCents;
        $partner->save();
        Cache::forget("partner_balance_{$partner->id}");

        ContractEvent::create([
            'contract_id'  => $contract->id,
            'author_id'    => Auth::id(),
            'type'         => 'balance_charged',
            'payload_json' => json_encode([
                'amount'        => Money::fromCents($feeCents),
                'currency'      => 'RUB',
                'partner_id'    => $partner->id,
                'balance_after' => Money::fromCents((int) $partner->wallet_balance_cents),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return ((int) $partner->wallet_balance_cents) / 100;
    }

    /**
     * Возврат 70 ₽ при отзыве договора в статусе awaiting_client_fill.
     */
    public function refundCreationFee(Partner $partner, Contract $contract, ?int $authorId = null): float
    {
        $feeCents = $this->createFeeCents();

        $partner->wallet_balance_cents = (int) $partner->wallet_balance_cents + $feeCents;
        $partner->save();
        Cache::forget("partner_balance_{$partner->id}");

        ContractEvent::create([
            'contract_id'  => $contract->id,
            'author_id'    => $authorId ?? Auth::id(),
            'type'         => 'balance_refunded',
            'payload_json' => json_encode([
                'amount'        => Money::fromCents($feeCents),
                'currency'      => 'RUB',
                'partner_id'    => $partner->id,
                'balance_after' => Money::fromCents((int) $partner->wallet_balance_cents),
                'reason'        => 'revoke_awaiting_client_fill',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return ((int) $partner->wallet_balance_cents) / 100;
    }
}
