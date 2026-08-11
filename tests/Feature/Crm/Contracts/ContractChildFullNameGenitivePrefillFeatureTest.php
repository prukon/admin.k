<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractPrefillResolver;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Prefill/плейсхолдер child_full_name_genitive для договоров.
 *
 * @see /docs/documentation/contract-templates.html §6
 */
final class ContractChildFullNameGenitivePrefillFeatureTest extends CrmTestCase
{
    public function test_recommended_presets_include_optional_child_genitive(): void
    {
        $preset = collect(ContractTemplateVariablePresets::recommended())
            ->firstWhere('key', ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE);

        $this->assertNotNull($preset);
        $this->assertSame('Ребёнок: ФИО в родительном падеже', $preset['label']);
        $this->assertSame(
            ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE,
            $preset['prefill_source']
        );
        $this->assertFalse($preset['required_default']);
        $this->assertArrayHasKey(
            ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE,
            ContractTemplatePrefillSources::labels()
        );
    }

    public function test_prefill_resolver_fills_genitive_from_student(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->roleId('user'),
            'lastname'           => 'Иванов',
            'name'               => 'Иван',
            'full_name_genitive' => 'Иванова Ивана',
        ]);

        $values = app(ContractPrefillResolver::class)->resolveForContract(
            $this->makeContractForStudent($student),
            [
                [
                    'key'            => ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE,
                    'prefill_source' => ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE,
                ],
                [
                    'key'            => ContractTemplatePrefillSources::CHILD_FULL_NAME,
                    'prefill_source' => ContractTemplatePrefillSources::CHILD_FULL_NAME,
                ],
            ],
        );

        $this->assertSame(
            'Иванова Ивана',
            $values[ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE]
        );
        $this->assertSame('Иванов Иван', $values[ContractTemplatePrefillSources::CHILD_FULL_NAME]);
    }

    public function test_prefill_resolver_returns_empty_genitive_when_not_set(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->roleId('user'),
            'full_name_genitive' => null,
        ]);

        $values = app(ContractPrefillResolver::class)->resolveForContract(
            $this->makeContractForStudent($student),
            [
                [
                    'key'            => ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE,
                    'prefill_source' => ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE,
                ],
            ],
        );

        $this->assertSame('', $values[ContractTemplatePrefillSources::CHILD_FULL_NAME_GENITIVE]);
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
