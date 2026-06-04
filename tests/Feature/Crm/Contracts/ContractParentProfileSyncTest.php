<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ParentProfile;
use App\Services\Contracts\ContractParentProfileSyncService;
use Tests\Feature\Crm\CrmTestCase;

final class ContractParentProfileSyncTest extends CrmTestCase
{
    public function test_filled_contract_data_is_saved_to_parent_profile(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
        ]);

        $this->user->forceFill(['parent_id' => $parent->id])->save();

        app(ContractParentProfileSyncService::class)->syncFromFilledData(
            $this->user,
            (int) $this->partner->id,
            [
                'parent_passport'        => '4010 654321',
                'parent_passport_issued' => 'УФМС, 12.03.2015',
                'parent_address'         => 'г. Санкт-Петербург, Невский пр., 10',
                'parent_phone'           => '+7 (999) 555-66-77',
                'parent_email'           => 'Signer@Example.com',
            ],
        );

        $parent->refresh();

        $this->assertSame('4010 654321', $parent->passport);
        $this->assertSame('УФМС, 12.03.2015', $parent->passport_issued);
        $this->assertSame('г. Санкт-Петербург, Невский пр., 10', $parent->address);
        $this->assertSame('79995556677', $parent->phone);
        $this->assertSame('signer@example.com', $parent->email);
    }

    public function test_creates_parent_profile_when_missing(): void
    {
        $this->assertNull($this->user->parent_id);

        app(ContractParentProfileSyncService::class)->syncFromFilledData(
            $this->user,
            (int) $this->partner->id,
            [
                'parent_lastname'  => 'Петров',
                'parent_firstname' => 'Пётр',
                'parent_passport'  => '4500 111111',
            ],
        );

        $this->user->refresh();

        $this->assertNotNull($this->user->parent_id);
        $profile = $this->user->parentProfile;
        $this->assertNotNull($profile);
        $this->assertSame('Петров', $profile->lastname);
        $this->assertSame('Пётр', $profile->firstname);
        $this->assertSame('4500 111111', $profile->passport);
    }

    public function test_student_can_update_parent_passport_fields_in_account(): void
    {
        $this->actingAs($this->user);

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
        ]);

        $this->user->forceFill(['parent_id' => $parent->id])->save();

        $payload = [
            'name'                   => $this->user->name,
            'lastname'               => $this->user->lastname,
            'parent_passport'        => '4010 999888',
            'parent_passport_issued' => 'ОВД, 01.02.2020',
            'parent_address'         => 'г. Казань, ул. Ленина, 5',
            'parent_phone'           => '8 (900) 123-45-67',
            'parent_email'           => 'parent@test.ru',
        ];

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ])
            ->withHeaders([
                'Accept'           => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('account.user.update'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $parent->refresh();

        $this->assertSame('4010 999888', $parent->passport);
        $this->assertSame('ОВД, 01.02.2020', $parent->passport_issued);
        $this->assertSame('г. Казань, ул. Ленина, 5', $parent->address);
        $this->assertSame('79001234567', $parent->phone);
        $this->assertSame('parent@test.ru', $parent->email);
    }

    public function test_sync_service_maps_only_known_parent_keys(): void
    {
        $mapped = app(ContractParentProfileSyncService::class)->mapFilledDataToParentPayload([
            'parent_passport' => '1234 567890',
            'custom_field' => 'Игнор',
            'parent_phone' => '',
        ]);

        $this->assertSame(['parent_passport' => '1234 567890'], $mapped);
    }
}
