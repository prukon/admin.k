<?php

namespace Tests\Feature\Crm\Contracts;

use App\Services\Contracts\ContractStudentProfileSyncService;
use Tests\Feature\Crm\CrmTestCase;

final class ContractStudentProfileSyncTest extends CrmTestCase
{
    public function test_filled_contract_data_updates_student_name_parts(): void
    {
        $this->user->forceFill([
            'lastname' => 'Старое',
            'name'     => 'Имя',
        ])->save();

        app(ContractStudentProfileSyncService::class)->syncFromFilledData($this->user, [
            'child_lastname'  => 'Петров',
            'child_firstname' => 'Пётр',
        ]);

        $this->user->refresh();

        $this->assertSame('Петров', $this->user->lastname);
        $this->assertSame('Пётр', $this->user->name);
    }
}
