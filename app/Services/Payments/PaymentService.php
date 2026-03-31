<?php

namespace App\Services\Payments;

use App\Models\Partner;
use App\Models\PaymentSystem;
 
class PaymentService
{
    /**
     * Робокасса доступна, если:
     * - есть запись в payment_systems для партнёра
     * - is_enabled = 1
     */
    public function isRobokassaAvailable(Partner $partner): bool
    {
        return PaymentSystem::where('partner_id', $partner->id)
            ->where('name', 'robokassa')
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * Т-Банк доступен, если:
     * - есть запись в payment_systems для партнёра
     * - is_enabled = 1
     * - у партнёра заполнен tinkoff_partner_id
     */
    public function isTbankAvailable(Partner $partner): bool
    {
        $exists = PaymentSystem::where('partner_id', $partner->id)
            ->where('name', 'tbank')
            ->where('is_enabled', true)
            ->exists();

        if (!$exists) {
            return false;
        }

        if (empty($partner->tinkoff_partner_id)) {
            return false;
        }

        return true;
    }

        public function isTbankSbpAvailable(Partner $partner, ?int $amountCents): bool
    {
        if (!$this->isTbankAvailable($partner)) {
            return false;
        }

        if ($amountCents === null) {
            return false;
        }

        return $amountCents >= 1000;
    }


    public function amountToCents(?string $outSum): ?int
{
    if ($outSum === null) {
        return null;
    } 

    $norm = str_replace(',', '.', trim($outSum));

    if ($norm === '' || !is_numeric($norm)) {
        return null;
    }

    return (int) round(((float) $norm) * 100);
}

}
