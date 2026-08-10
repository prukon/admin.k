<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Services\Contracts\ContractLegalEntityPlaceholderService;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Crm\CrmTestCase;

final class ContractLegalEntityPlaceholderServiceTest extends CrmTestCase
{
    public function test_values_from_entity_map_requisites(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->individualEntrepreneur()->create([
            'organization_name'   => 'ИП Иванов Иван Иванович',
            'registration_number' => '304500116000157',
            'tax_id'              => '500100732259',
            'address'             => 'г. Москва, ул. Примерная, д. 1',
            'bank_name'           => 'АО «ТБанк»',
            'bank_account'        => '40802810100000000001',
            'bank_bik'            => '044525974',
            'bank_corr_account'   => '30101810145250000974',
        ]);

        $values = app(ContractLegalEntityPlaceholderService::class)->valuesFromEntity($entity);

        $this->assertSame('ИП Иванов Иван Иванович', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_NAME]);
        $this->assertSame('304500116000157', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_OGRNIP]);
        $this->assertSame('500100732259', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_INN]);
        $this->assertSame('г. Москва, ул. Примерная, д. 1', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_ADDRESS]);
        $this->assertSame('АО «ТБанк»', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_BANK_NAME]);
        $this->assertSame('40802810100000000001', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_BANK_ACCOUNT]);
        $this->assertSame('044525974', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_BIK]);
        $this->assertSame('30101810145250000974', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_BANK_CORR_ACCOUNT]);
    }

    public function test_assert_resolvable_passes_when_team_has_legal_entity(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create();
        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $entity->id,
        ]);

        app(ContractLegalEntityPlaceholderService::class)->assertResolvableForPartnerTeam(
            (int) $this->partner->id,
            (int) $team->id,
            [['key' => 'legal_entity_inn']],
        );

        $this->addToAssertionCount(1);
    }

    public function test_assert_resolvable_fails_in_multi_entity_mode_without_team_binding(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => null,
        ]);

        try {
            app(ContractLegalEntityPlaceholderService::class)->assertResolvableForPartnerTeam(
                (int) $this->partner->id,
                (int) $team->id,
                [['key' => 'legal_entity_name']],
            );
            $this->fail('Ожидался ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('group_id', $e->errors());
            $this->assertStringContainsString('не привязано юр. лицо', $e->errors()['group_id'][0]);
        }
    }

    public function test_values_for_contract_resolve_from_team_legal_entity(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Тестов',
            'tax_id'            => '123456789012',
        ]);
        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $entity->id,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
        ]);

        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'With LE',
            'is_archived' => false,
        ]);
        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => 'contract-templates/test.docx',
            'docx_sha256'          => str_repeat('b', 64),
            'fields_schema'        => [
                ['key' => 'legal_entity_name'],
                ['key' => 'legal_entity_inn'],
            ],
        ]);
        $template->current_version_id = $version->id;
        $template->save();

        $contract = new Contract([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => $team->id,
            'contract_template_version_id' => $version->id,
        ]);
        $contract->setRelation('templateVersion', $version);
        $contract->setRelation('team', $team);

        $values = app(ContractLegalEntityPlaceholderService::class)->valuesForContract($contract);

        $this->assertSame('ИП Тестов', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_NAME]);
        $this->assertSame('123456789012', $values[ContractTemplatePrefillSources::LEGAL_ENTITY_INN]);
    }
}
