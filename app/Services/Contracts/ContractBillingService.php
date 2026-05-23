<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ContractBillingService
{
    public function createFee(): float
    {
        return (float) (config('billing.contract_create_fee') ?? 70.00);
    }

    /**
     * Списание за создание договора (PDF или шаблон). Возвращает баланс после списания.
     *
     * @throws ValidationException
     */
    public function chargeCreationFee(Partner $partner, Contract $contract): float
    {
        $fee = $this->createFee();

        if ((float) $partner->wallet_balance < $fee) {
            throw ValidationException::withMessages([
                'wallet' => 'Недостаточно средств для создания договора.',
            ]);
        }

        $partner->wallet_balance = (float) $partner->wallet_balance - $fee;
        $partner->save();
        Cache::forget("partner_balance_{$partner->id}");

        ContractEvent::create([
            'contract_id'  => $contract->id,
            'author_id'    => Auth::id(),
            'type'         => 'balance_charged',
            'payload_json' => json_encode([
                'amount'        => number_format($fee, 2, '.', ''),
                'currency'      => 'RUB',
                'partner_id'    => $partner->id,
                'balance_after' => number_format($partner->wallet_balance, 2, '.', ''),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return (float) $partner->wallet_balance;
    }

    /**
     * Возврат 70 ₽ при отзыве договора в статусе awaiting_client_fill.
     */
    public function refundCreationFee(Partner $partner, Contract $contract, ?int $authorId = null): float
    {
        $fee = $this->createFee();

        $partner->wallet_balance = (float) $partner->wallet_balance + $fee;
        $partner->save();
        Cache::forget("partner_balance_{$partner->id}");

        ContractEvent::create([
            'contract_id'  => $contract->id,
            'author_id'    => $authorId ?? Auth::id(),
            'type'         => 'balance_refunded',
            'payload_json' => json_encode([
                'amount'        => number_format($fee, 2, '.', ''),
                'currency'      => 'RUB',
                'partner_id'    => $partner->id,
                'balance_after' => number_format($partner->wallet_balance, 2, '.', ''),
                'reason'        => 'revoke_awaiting_client_fill',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return (float) $partner->wallet_balance;
    }
}
