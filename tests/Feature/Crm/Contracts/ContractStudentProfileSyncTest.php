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

    public function test_filled_passport_fields_update_student_card(): void
    {
        $this->user->forceFill([
            'passport'           => 'старый',
            'passport_issued_at' => '2010-01-01',
        ])->save();

        app(ContractStudentProfileSyncService::class)->syncFromFilledData($this->user, [
            'child_passport'           => 'II-АБ 654321',
            'child_passport_issued_at' => '15.03.2020',
        ]);

        $this->user->refresh();

        $this->assertSame('II-АБ 654321', $this->user->passport);
        $this->assertSame('2020-03-15', $this->user->passport_issued_at?->format('Y-m-d'));
    }

    public function test_empty_passport_fields_clear_student_card(): void
    {
        $this->user->forceFill([
            'passport'           => 'стереть',
            'passport_issued_at' => '2010-01-01',
        ])->save();

        app(ContractStudentProfileSyncService::class)->syncFromFilledData($this->user, [
            'child_passport'           => '   ',
            'child_passport_issued_at' => '',
        ]);

        $this->user->refresh();

        $this->assertNull($this->user->passport);
        $this->assertNull($this->user->passport_issued_at);
    }

    public function test_future_passport_issued_at_does_not_overwrite_student_card(): void
    {
        $this->user->forceFill([
            'passport_issued_at' => '2010-01-01',
        ])->save();

        app(ContractStudentProfileSyncService::class)->syncFromFilledData($this->user, [
            'child_passport_issued_at' => now()->addDay()->format('d.m.Y'),
        ]);

        $this->user->refresh();

        $this->assertSame('2010-01-01', $this->user->passport_issued_at?->format('Y-m-d'));
    }
}
