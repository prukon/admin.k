<?php

namespace App\Services\Signatures;

use App\Models\Contract;
use App\Models\PartnerLegalEntity;
use App\Services\PartnerLegalEntities\LegalEntityResolver;

class PodpislonCredentialsResolver
{
    public function __construct(
        private readonly LegalEntityResolver $legalEntityResolver,
    ) {
    }

    /**
     * Юр. лицо договора и его API-ключ Подпислона. При первой успешной резолюции
     * фиксирует {@see Contract::$legal_entity_id}, чтобы статус/вебхук/скачивание
     * ходили в тот же кабинет, куда ушёл документ.
     *
     * @throws PodpislonCredentialsException
     */
    public function bindToContract(Contract $contract): PartnerLegalEntity
    {
        $entity = $this->resolveEntity($contract);
        $this->assertHasApiKey($entity);

        if ((int) $contract->legal_entity_id !== (int) $entity->id) {
            $contract->legal_entity_id = $entity->id;
            $contract->save();
        }

        return $entity;
    }

    /**
     * @throws PodpislonCredentialsException
     */
    public function apiKeyForContract(Contract $contract): string
    {
        return trim((string) $this->bindToContract($contract)->podpislon_api_key);
    }

    /**
     * @throws PodpislonCredentialsException
     */
    public function resolveEntity(Contract $contract): PartnerLegalEntity
    {
        if ($contract->legal_entity_id) {
            $entity = PartnerLegalEntity::query()
                ->withTrashed()
                ->whereKey($contract->legal_entity_id)
                ->where('partner_id', (int) $contract->school_id)
                ->first();

            if ($entity === null) {
                throw new PodpislonCredentialsException(
                    'Юр. лицо договора не найдено. Обратитесь к администратору платформы.',
                    'legal_entity_id',
                    'legal_entity_missing',
                );
            }

            return $entity;
        }

        $teamId = $contract->group_id !== null ? (int) $contract->group_id : null;
        $resolution = $this->legalEntityResolver->forTeamId($teamId, (int) $contract->school_id);

        if ($resolution->entity === null || $resolution->usedDefaultFallback) {
            throw new PodpislonCredentialsException(
                'Не удалось определить юр. лицо для подписи в Подпислоне. Привяжите группу договора к юр. лицу.',
                'legal_entity_id',
                'legal_entity_unresolved',
            );
        }

        return $resolution->entity;
    }

    /**
     * @throws PodpislonCredentialsException
     */
    private function assertHasApiKey(PartnerLegalEntity $entity): void
    {
        if ($entity->hasPodpislonApiKey()) {
            return;
        }

        $title = $entity->displayTitle();
        $suffix = $title !== '' ? ' «'.$title.'»' : '';

        throw new PodpislonCredentialsException(
            'Для юр. лица'.$suffix.' не задан API-ключ Подпислона. Обратитесь к администратору платформы.',
            'podpislon_api_key',
            'podpislon_api_key_missing',
        );
    }
}
