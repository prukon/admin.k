<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Запись успешной оплаты в журнал payments (отчёт «Платежи»).
 * location_id — снимок в момент оплаты; задаётся только при первом создании, если передан в $attributes.
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

        if (! $payment->exists && array_key_exists('location_id', $attributes)) {
            $rawLocationId = $attributes['location_id'];
            $payment->location_id = ($rawLocationId !== null && $rawLocationId !== '')
                ? (int) $rawLocationId
                : null;
        }

        $fillAttributes = $attributes;
        unset($fillAttributes['location_id']);

        $payment->fill($fillAttributes);
        $payment->save();

        return $payment;
    }
}
