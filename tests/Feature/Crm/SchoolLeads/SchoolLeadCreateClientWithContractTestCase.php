<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Создание клиента из заявки с опциональной отправкой договора.
 */
abstract class SchoolLeadCreateClientWithContractTestCase extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();
    }

    protected function actingAsLeadsUsersAndContractsViewer(): User
    {
        $this->actingAsLeadsAndUsersViewer();
        $this->grantPermission($this->user, 'contracts.view');

        return $this->user;
    }

    /**
     * @param  array<string, mixed>  $templateAttrs
     * @param  array<string, mixed>  $versionAttrs
     */
    protected function makeContractTemplate(array $templateAttrs = [], array $versionAttrs = []): ContractTemplate
    {
        $template = ContractTemplate::create(array_merge([
            'partner_id'  => $this->partner->id,
            'title'       => 'Шаблон с заявки ' . Str::random(6),
            'is_archived' => false,
        ], $templateAttrs));

        $docxPath = $versionAttrs['docx_path'] ?? 'contract-templates/lead-test-' . uniqid('', true) . '.docx';
        Storage::put($docxPath, 'lead-contract-template');

        $version = ContractTemplateVersion::create(array_merge([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => $docxPath,
            'docx_sha256'          => str_repeat('b', 64),
            'fields_schema'        => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'Родитель: ФИО',
                    'required'       => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME,
                ],
            ],
            'email_subject'   => 'Заполните договор',
            'email_body_html' => '<p>Текст письма</p>',
        ], $versionAttrs));

        $template->current_version_id = $version->id;
        $template->save();

        return $template->fresh(['currentVersion']);
    }

    protected function makeLegalEntityTemplate(): ContractTemplate
    {
        return $this->makeContractTemplate(
            ['title' => 'LE шаблон ' . Str::random(6)],
            [
                'fields_schema' => [
                    [
                        'key'            => ContractTemplatePrefillSources::LEGAL_ENTITY_INN,
                        'label'          => 'Юр. лицо: ИНН',
                        'required'       => false,
                        'prefill_source' => null,
                    ],
                    [
                        'key'            => 'parent_full_name',
                        'label'          => 'Родитель: ФИО',
                        'required'       => true,
                        'prefill_source' => null,
                    ],
                ],
            ],
        );
    }

    protected function makeTeamWithoutLegalEntity(): Team
    {
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);

        return Team::factory()->for($this->partner)->create([
            'title'           => 'Группа без юрлица',
            'legal_entity_id' => null,
        ]);
    }
}
