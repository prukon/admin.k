<?php

namespace Tests\Unit\Services;

use App\Models\ParentProfile;
use App\Models\User;
use App\Services\Contracts\ContractPrefillResolver;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Tests\Feature\Crm\CrmTestCase;

final class ContractPrefillResolverTest extends CrmTestCase
{
    public function test_parent_phone_and_email_prefill_from_parent_profile_not_student(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'phone'      => '79991112233',
            'email'      => 'parent@example.com',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
            'parent_id'  => $parent->id,
            'phone'      => '+79990000000',
            'email'      => 'student@example.com',
        ]);

        $resolver = app(ContractPrefillResolver::class);
        $values = $resolver->resolveForContract(
            $this->makeContractForStudent($student),
            [
                ['key' => 'parent_phone', 'prefill_source' => ContractTemplatePrefillSources::PARENT_PHONE],
                ['key' => 'parent_email', 'prefill_source' => ContractTemplatePrefillSources::PARENT_EMAIL],
            ],
        );

        $this->assertSame('79991112233', $values['parent_phone']);
        $this->assertSame('parent@example.com', $values['parent_email']);
    }

    public function test_parent_passport_and_address_prefill_from_parent_profile(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'       => $this->partner->id,
            'passport'         => '4010 123456',
            'passport_issued'  => 'ОВД Центрального района, 01.01.2010',
            'address'          => 'г. Москва, ул. Примерная, д. 1',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
            'parent_id'  => $parent->id,
        ]);

        $resolver = app(ContractPrefillResolver::class);
        $values = $resolver->resolveForContract(
            $this->makeContractForStudent($student),
            [
                ['key' => 'parent_passport', 'prefill_source' => ContractTemplatePrefillSources::PARENT_PASSPORT],
                ['key' => 'parent_passport_issued', 'prefill_source' => ContractTemplatePrefillSources::PARENT_PASSPORT_ISSUED],
                ['key' => 'parent_address', 'prefill_source' => ContractTemplatePrefillSources::PARENT_ADDRESS],
            ],
        );

        $this->assertSame('4010 123456', $values['parent_passport']);
        $this->assertSame('ОВД Центрального района, 01.01.2010', $values['parent_passport_issued']);
        $this->assertSame('г. Москва, ул. Примерная, д. 1', $values['parent_address']);
    }

    public function test_child_full_name_and_birthday_prefill_from_student_profile(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
            'lastname'   => 'Петров',
            'name'       => 'Пётр',
            'birthday'   => '2018-05-10',
        ]);

        $resolver = app(ContractPrefillResolver::class);
        $values = $resolver->resolveForContract(
            $this->makeContractForStudent($student),
            [
                ['key' => 'child_full_name', 'prefill_source' => ContractTemplatePrefillSources::CHILD_FULL_NAME],
                ['key' => 'child_lastname', 'prefill_source' => ContractTemplatePrefillSources::CHILD_LASTNAME],
                ['key' => 'child_firstname', 'prefill_source' => ContractTemplatePrefillSources::CHILD_FIRSTNAME],
                ['key' => 'child_birthday', 'prefill_source' => ContractTemplatePrefillSources::CHILD_BIRTHDAY],
            ],
        );

        $this->assertSame('Петров Пётр', $values['child_full_name']);
        $this->assertSame('Петров', $values['child_lastname']);
        $this->assertSame('Пётр', $values['child_firstname']);
        $this->assertSame('10.05.2018', $values['child_birthday']);
    }

    private function makeContractForStudent(User $student): \App\Models\Contract
    {
        $contract = new \App\Models\Contract([
            'school_id' => $this->partner->id,
            'user_id'   => $student->id,
        ]);
        $contract->setRelation('user', $student);

        return $contract;
    }
}
