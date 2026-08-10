<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ParentProfile;
use App\Models\User;
use App\Services\Contracts\ContractPrefillResolver;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Prefill/плейсхолдер parent_full_name_genitive для договоров.
 *
 * @see /docs/documentation/contract-templates.html §6
 */
final class ContractParentFullNameGenitivePrefillFeatureTest extends CrmTestCase
{
    public function test_recommended_presets_include_optional_parent_genitive(): void
    {
        $preset = collect(ContractTemplateVariablePresets::recommended())
            ->firstWhere('key', ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE);

        $this->assertNotNull($preset);
        $this->assertSame('Родитель: ФИО в родительном падеже', $preset['label']);
        $this->assertSame(
            ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE,
            $preset['prefill_source']
        );
        $this->assertFalse($preset['required_default']);
        $this->assertArrayHasKey(
            ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE,
            ContractTemplatePrefillSources::labels()
        );
    }

    public function test_prefill_resolver_fills_genitive_from_parent_profile(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Иванов',
            'firstname'          => 'Иван',
            'middlename'         => 'Иванович',
            'full_name_genitive' => 'Иванова Ивана Ивановича',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'parent_id'  => $parent->id,
        ]);

        $values = app(ContractPrefillResolver::class)->resolveForContract(
            $this->makeContractForStudent($student),
            [
                [
                    'key'            => ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE,
                ],
                [
                    'key'            => ContractTemplatePrefillSources::PARENT_FULL_NAME,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME,
                ],
            ],
        );

        $this->assertSame(
            'Иванова Ивана Ивановича',
            $values[ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE]
        );
        $this->assertSame('Иванов Иван Иванович', $values[ContractTemplatePrefillSources::PARENT_FULL_NAME]);
    }

    public function test_prefill_resolver_returns_empty_genitive_when_not_set(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Пустой',
            'firstname'          => 'Родитель',
            'full_name_genitive' => null,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'parent_id'  => $parent->id,
        ]);

        $values = app(ContractPrefillResolver::class)->resolveForContract(
            $this->makeContractForStudent($student),
            [
                [
                    'key'            => ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE,
                ],
            ],
        );

        $this->assertSame('', $values[ContractTemplatePrefillSources::PARENT_FULL_NAME_GENITIVE]);
    }

    private function makeContractForStudent(User $student): Contract
    {
        $contract = new Contract([
            'school_id' => $this->partner->id,
            'user_id'   => $student->id,
        ]);
        $contract->setRelation('user', $student);

        return $contract;
    }
}
