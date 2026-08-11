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
            'address'  => 'старый адрес',
            'full_name_genitive' => 'Старого Имени',
        ])->save();

        app(ContractStudentProfileSyncService::class)->syncFromFilledData($this->user, [
            'child_lastname'  => 'Петров',
            'child_firstname' => 'Пётр',
            'child_address'   => 'г. Казань, ул. Новая, д. 5',
            'child_full_name_genitive' => 'Петрова Петра',
        ]);

        $this->user->refresh();

        $this->assertSame('Петров', $this->user->lastname);
        $this->assertSame('Пётр', $this->user->name);
        $this->assertSame('г. Казань, ул. Новая, д. 5', $this->user->address);
        $this->assertSame('Петрова Петра', $this->user->full_name_genitive);
    }

    public function test_empty_child_genitive_does_not_clear_existing_value(): void
    {
        $this->user->forceFill([
            'full_name_genitive' => 'Сохранить Это',
        ])->save();

        app(ContractStudentProfileSyncService::class)->syncFromFilledData($this->user, [
            'child_full_name_genitive' => '',
        ]);

        $this->user->refresh();
        $this->assertSame('Сохранить Это', $this->user->full_name_genitive);
    }

    public function test_missing_child_genitive_key_does_not_change_existing_value(): void
    {
        $this->user->forceFill([
            'full_name_genitive' => 'БезКлюча',
        ])->save();

        app(ContractStudentProfileSyncService::class)->syncFromFilledData($this->user, [
            'child_lastname' => 'Новый',
        ]);

        $this->user->refresh();
        $this->assertSame('Новый', $this->user->lastname);
        $this->assertSame('БезКлюча', $this->user->full_name_genitive);
    }
}
