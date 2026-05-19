<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;

/**
 * Запись успешной оплаты в журнал payments (отчёт «Платежи»).
 * location_id фиксируется только при первом создании строки.
 */
final class PaymentLedgerRecorder
{
    /**
     * @param  array<string, mixed>  $attributes  Поля платежа (без payment_number и partner_id)
     */
    public function record(string $paymentNumber, int $partnerId, int $userId, array $attributes): Payment
    {
        $payment = Payment::query()->firstOrNew([
            'payment_number' => $paymentNumber,
            'partner_id' => $partnerId,
        ]);

        if (! $payment->exists) {
            $user = User::query()->find($userId);
            $payment->location_id = $user?->location_id;
        }

        $payment->fill($attributes);
        $payment->save();

        return $payment;
    }
}
