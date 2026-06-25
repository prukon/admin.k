<?php

namespace App\Services\Payments;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Services\Tinkoff\TbankTerminalConfig;

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
     * T‑Bank доступен, если:
     * - глобальный терминал настроен и включён (payment_systems partner_id IS NULL)
     * - у партнёра заполнен tinkoff_partner_id (ShopCode)
     */
    public function isTbankAvailable(Partner $partner): bool
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
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
