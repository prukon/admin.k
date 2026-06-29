<?php

namespace App\Services\PartnerLegalEntities;

use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\User;
use App\Services\Payments\PayableTeamResolver;

final class LegalEntityResolver
{
    public function forTeam(Team $team): LegalEntityResolution
    {
        return $this->forTeamId(
            (int) $team->id,
            (int) $team->partner_id,
        );
    }

    public function forTeamId(?int $teamId, int $partnerId): LegalEntityResolution
    {
        if ($teamId !== null && $teamId > 0) {
            $team = Team::query()
                ->whereKey($teamId)
                ->where('partner_id', $partnerId)
                ->whereNull('deleted_at')
                ->first();

            if ($team !== null && $team->legal_entity_id) {
                $entity = $this->findActiveEntity((int) $team->legal_entity_id, $partnerId);
                if ($entity !== null) {
                    return new LegalEntityResolution($entity, false);
                }
            }
        }

        return $this->forPartner($partnerId);
    }

    public function forPartner(int $partnerId): LegalEntityResolution
    {
        $entities = PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        if ($entities->isEmpty()) {
            return new LegalEntityResolution(null, false);
        }

        if ($entities->count() === 1) {
            return new LegalEntityResolution($entities->first(), false);
        }

        $default = $entities->firstWhere('is_default', true);
        if ($default !== null) {
            return new LegalEntityResolution($default, true);
        }

        return new LegalEntityResolution($entities->first(), true);
    }

    public function forPayable(Payable $payable, ?User $user = null): LegalEntityResolution
    {
        $partnerId = (int) $payable->partner_id;
        $teamId = $this->teamIdFromPayable($payable, $user);

        if ($teamId !== null && $teamId > 0) {
            return $this->forTeamId($teamId, $partnerId);
        }

        return new LegalEntityResolution(null, false);
    }

    public function forTinkoffPayment(TinkoffPayment $payment): LegalEntityResolution
    {
        if ($payment->legal_entity_id) {
            $entity = $this->findActiveEntity((int) $payment->legal_entity_id, (int) $payment->partner_id);
            if ($entity !== null) {
                return new LegalEntityResolution($entity, false);
            }
        }

        $intent = PaymentIntent::query()
            ->where('provider', 'tbank')
            ->where('partner_id', (int) $payment->partner_id)
            ->where('tbank_order_id', (string) $payment->order_id)
            ->with('payable')
            ->first();

        if ($intent?->payable) {
            $user = $intent->user_id
                ? User::query()->find((int) $intent->user_id)
                : null;

            return $this->forPayable($intent->payable, $user);
        }

        return $this->forPartner((int) $payment->partner_id);
    }

    public function forFiscalReceipt(\App\Models\FiscalReceipt $fiscalReceipt, ?User $user = null): LegalEntityResolution
    {
        if ($fiscalReceipt->legal_entity_id) {
            $entity = $this->findActiveEntity(
                (int) $fiscalReceipt->legal_entity_id,
                (int) $fiscalReceipt->partner_id,
            );
            if ($entity !== null) {
                return new LegalEntityResolution($entity, false);
            }
        }

        if ($fiscalReceipt->payable) {
            return $this->forPayable($fiscalReceipt->payable, $user);
        }

        return $this->forPartner((int) $fiscalReceipt->partner_id);
    }

    /**
     * ShopCode для выплат T‑Bank: сначала юр. лицо, затем legacy partners.tinkoff_partner_id.
     */
    public function shopCode(Partner $partner, LegalEntityResolution $resolution): ?string
    {
        $fromEntity = trim((string) ($resolution->entity?->tinkoff_shop_code ?? ''));
        if ($fromEntity !== '') {
            return $fromEntity;
        }

        $legacy = trim((string) ($partner->tinkoff_partner_id ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    public function shopCodeForPartner(Partner $partner, ?Team $team = null): ?string
    {
        $resolution = $team !== null
            ? $this->forTeam($team)
            : $this->forPartner((int) $partner->id);

        return $this->shopCode($partner, $resolution);
    }

    public function hasRegisteredShopCode(Partner $partner, ?Team $team = null): bool
    {
        return $this->shopCodeForPartner($partner, $team) !== null;
    }

    /**
     * ИНН принципала для CloudKassir: юр. лицо → legacy partner.
     */
    public function fiscalTaxId(Partner $partner, LegalEntityResolution $resolution): ?string
    {
        $fromEntity = trim((string) ($resolution->entity?->tax_id ?? ''));
        if ($fromEntity !== '') {
            return $fromEntity;
        }

        $legacy = trim((string) ($partner->tax_id ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    public function fiscalOrganizationName(Partner $partner, LegalEntityResolution $resolution): string
    {
        $fromEntity = trim((string) ($resolution->entity?->organization_name ?? ''));
        if ($fromEntity !== '') {
            return $fromEntity;
        }

        $fromEntityTitle = trim((string) ($resolution->entity?->title ?? ''));
        if ($fromEntityTitle !== '') {
            return $fromEntityTitle;
        }

        $legacyOrg = trim((string) ($partner->organization_name ?? ''));
        if ($legacyOrg !== '') {
            return $legacyOrg;
        }

        return trim((string) ($partner->title ?? ''));
    }

    public function fiscalVat(Partner $partner, LegalEntityResolution $resolution): ?int
    {
        if ($resolution->entity !== null && $resolution->entity->vat !== null) {
            return (int) $resolution->entity->vat;
        }

        if ($partner->vat === null) {
            return null;
        }

        return (int) $partner->vat;
    }

    public function resolveLegalEntityId(LegalEntityResolution $resolution): ?int
    {
        return $resolution->entity?->id;
    }

    public function resolveLegalEntityIdFromInitData(int $partnerId, array $data): ?int
    {
        $teamId = isset($data['team_id']) && ctype_digit((string) $data['team_id'])
            ? (int) $data['team_id']
            : null;

        if ($teamId !== null && $teamId > 0) {
            return $this->resolveLegalEntityId($this->forTeamId($teamId, $partnerId));
        }

        return null;
    }

    private function teamIdFromPayable(Payable $payable, ?User $user): ?int
    {
        if ($user === null) {
            $metaTeamId = $payable->meta['team_id'] ?? null;

            return is_numeric($metaTeamId) && (int) $metaTeamId > 0 ? (int) $metaTeamId : null;
        }

        return app(PayableTeamResolver::class)->resolveFromPayable($payable, $user);
    }

    private function findActiveEntity(int $entityId, int $partnerId): ?PartnerLegalEntity
    {
        return PartnerLegalEntity::query()
            ->whereKey($entityId)
            ->where('partner_id', $partnerId)
            ->active()
            ->first();
    }
}
