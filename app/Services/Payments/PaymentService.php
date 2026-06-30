<?php

namespace App\Services\Payments;

use App\Models\Partner;
use App\Models\Payable;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerLegalEntities\LegalEntityResolver;
use App\Services\Tinkoff\TbankTerminalConfig;
use App\Support\PartnerLegalEntityMode;

class PaymentService
{
    public function __construct(
        private readonly LegalEntityResolver $legalEntityResolver,
    ) {
    }

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
     * - глобальный терминал настроен и включён
     * - у юр. лица из справочника есть ShopCode (tinkoff_shop_code)
     */
    public function isTbankAvailable(Partner $partner, ?Team $team = null): bool
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
            return false;
        }

        return $this->legalEntityResolver->hasRegisteredShopCode($partner, $team);
    }

    public function isTbankAvailableForPayable(Partner $partner, Payable $payable, ?User $user = null): bool
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
            return false;
        }

        $resolution = $this->legalEntityResolver->forPayable($payable, $user);

        return $this->legalEntityResolver->shopCode($partner, $resolution) !== null;
    }

    public function isTbankSbpAvailable(Partner $partner, ?int $amountCents, ?Team $team = null): bool
    {
        if (! $this->isTbankAvailable($partner, $team)) {
            return false;
        }

        if ($amountCents === null) {
            return false;
        }

        return $amountCents >= 1000;
    }

    /**
     * Сообщение об ошибке доступности T‑Bank для Init (null — оплата доступна).
     */
    public function tbankAvailabilityError(Partner $partner, ?Team $team = null): ?string
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
            return 'Партнёр не зарегистрирован в T‑Bank (нет ShopCode)';
        }

        if ($team !== null && PartnerLegalEntityMode::isMultiEntity((int) $partner->id)) {
            $resolution = $this->legalEntityResolver->forTeam($team);
            if ($resolution->entity === null) {
                return 'Для выбранной группы не настроено юр. лицо';
            }
        }

        if (! $this->isTbankAvailable($partner, $team)) {
            return 'Партнёр не зарегистрирован в T‑Bank (нет ShopCode)';
        }

        return null;
    }

    public function amountToCents(?string $outSum): ?int
    {
        if ($outSum === null) {
            return null;
        }

        $norm = str_replace(',', '.', trim($outSum));

        if ($norm === '' || ! is_numeric($norm)) {
            return null;
        }

        return (int) round(((float) $norm) * 100);
    }
}
