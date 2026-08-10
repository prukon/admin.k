<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Services\PartnerLegalEntities\LegalEntityResolver;
use Illuminate\Validation\ValidationException;

/**
 * Реквизиты юр. лица для плейсхолдеров DOCX и проверка перед отправкой шаблона.
 */
class ContractLegalEntityPlaceholderService
{
    public function __construct(
        private readonly LegalEntityResolver $legalEntityResolver,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     *
     * @throws ValidationException
     */
    public function assertResolvableForPartnerTeam(
        int $partnerId,
        ?int $teamId,
        array $schema,
        string $errorKey = 'group_id',
    ): void {
        if (!ContractTemplateVariablePresets::schemaUsesLegalEntityFields($schema)) {
            return;
        }

        $entity = $this->resolveEntity($partnerId, $teamId);
        if ($entity !== null) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => [$this->unresolvableMessage($partnerId, $teamId)],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function assertResolvableForContract(Contract $contract): void
    {
        $contract->loadMissing(['templateVersion', 'team']);
        $schema = $contract->templateVersion?->fields_schema ?? [];
        if (!is_array($schema) || $schema === []) {
            return;
        }

        $this->assertResolvableForPartnerTeam(
            (int) $contract->school_id,
            $contract->group_id !== null ? (int) $contract->group_id : null,
            $schema,
            'contract',
        );
    }

    /**
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function valuesForContract(Contract $contract): array
    {
        $contract->loadMissing(['templateVersion', 'team']);
        $schema = $contract->templateVersion?->fields_schema ?? [];
        if (!is_array($schema) || !ContractTemplateVariablePresets::schemaUsesLegalEntityFields($schema)) {
            return [];
        }

        $this->assertResolvableForContract($contract);

        $entity = $this->resolveEntity(
            (int) $contract->school_id,
            $contract->group_id !== null ? (int) $contract->group_id : null,
        );

        return $this->valuesFromEntity($entity);
    }

    /**
     * @return array<string, string>
     */
    public function valuesFromEntity(?PartnerLegalEntity $entity): array
    {
        return [
            ContractTemplatePrefillSources::LEGAL_ENTITY_NAME => $entity
                ? $entity->displayTitle()
                : '',
            ContractTemplatePrefillSources::LEGAL_ENTITY_OGRNIP => trim((string) ($entity?->registration_number ?? '')),
            ContractTemplatePrefillSources::LEGAL_ENTITY_INN => trim((string) ($entity?->tax_id ?? '')),
            ContractTemplatePrefillSources::LEGAL_ENTITY_ADDRESS => trim((string) ($entity?->address ?? '')),
            ContractTemplatePrefillSources::LEGAL_ENTITY_BANK_NAME => trim((string) ($entity?->bank_name ?? '')),
            ContractTemplatePrefillSources::LEGAL_ENTITY_BANK_ACCOUNT => trim((string) ($entity?->bank_account ?? '')),
            ContractTemplatePrefillSources::LEGAL_ENTITY_BIK => trim((string) ($entity?->bank_bik ?? '')),
            ContractTemplatePrefillSources::LEGAL_ENTITY_BANK_CORR_ACCOUNT => trim((string) ($entity?->bank_corr_account ?? '')),
        ];
    }

    private function resolveEntity(int $partnerId, ?int $teamId): ?PartnerLegalEntity
    {
        return $this->legalEntityResolver->forTeamId($teamId, $partnerId)->entity;
    }

    private function unresolvableMessage(int $partnerId, ?int $teamId): string
    {
        if ($teamId !== null && $teamId > 0) {
            $team = Team::query()
                ->whereKey($teamId)
                ->where('partner_id', $partnerId)
                ->whereNull('deleted_at')
                ->first();

            if ($team !== null && !$team->legal_entity_id) {
                return 'У выбранной группы не привязано юр. лицо. Укажите юр. лицо в настройках группы — шаблон договора содержит реквизиты исполнителя.';
            }

            return 'Не удалось определить юр. лицо для выбранной группы. Проверьте привязку группы к активному юр. лицу — шаблон договора содержит реквизиты исполнителя.';
        }

        $hasAnyActive = PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->active()
            ->exists();

        if (!$hasAnyActive) {
            return 'Не найдено активное юр. лицо. Добавьте юр. лицо в справочнике — шаблон договора содержит реквизиты исполнителя.';
        }

        return 'Не удалось определить юр. лицо для договора. Выберите группу с привязанным юр. лицом — шаблон содержит реквизиты исполнителя.';
    }
}
