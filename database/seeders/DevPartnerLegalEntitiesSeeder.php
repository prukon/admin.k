<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevPartnerLegalEntitiesSeeder extends Seeder
{
    use GuardsDevSeedData;

    /** @var list<int> */
    private const DEV_PARTNER_IDS = [1, 2, 3];

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        if (DB::table('partners')->whereIn('id', self::DEV_PARTNER_IDS)->count() !== count(self::DEV_PARTNER_IDS)) {
            $this->command?->warn('[DevPartnerLegalEntitiesSeeder] Ожидаются партнёры с id 1–3 — пропуск.');

            return;
        }

        $now = Carbon::now();

        DB::table('partners')
            ->whereIn('id', self::DEV_PARTNER_IDS)
            ->update([
                'tinkoff_partner_id' => null,
                'updated_at' => $now,
            ]);

        $rows = [
            $this->entityRow(
                id: 1,
                partnerId: 1,
                shopCode: 'SHOP-DEV-1',
                taxId: '860904518893',
                title: 'Dev placeholder partner 1',
                organizationName: 'Dev placeholder partner 1',
                businessType: 'IP',
                isDefault: true,
                now: $now,
            ),
            $this->entityRow(
                id: 2,
                partnerId: 2,
                shopCode: 'SHOP-DEV-2-1',
                taxId: '7700000201',
                title: 'ООО «Dev Partner 2 A»',
                organizationName: 'ООО «Dev Partner 2 A»',
                businessType: 'OOO',
                isDefault: true,
                now: $now,
                kpp: '770201001',
            ),
            $this->entityRow(
                id: 3,
                partnerId: 2,
                shopCode: 'SHOP-DEV-2-2',
                taxId: '7700000202',
                title: 'ООО «Dev Partner 2 B»',
                organizationName: 'ООО «Dev Partner 2 B»',
                businessType: 'OOO',
                isDefault: false,
                now: $now,
                kpp: '770202001',
            ),
            $this->entityRow(
                id: 4,
                partnerId: 3,
                shopCode: 'SHOP-DEV-3-1',
                taxId: '7700000301',
                title: 'ИП Dev Partner 3 A',
                organizationName: 'ИП Dev Partner 3 A',
                businessType: 'IP',
                isDefault: true,
                now: $now,
            ),
            $this->entityRow(
                id: 5,
                partnerId: 3,
                shopCode: 'SHOP-DEV-3-2',
                taxId: '7700000302',
                title: 'ИП Dev Partner 3 B',
                organizationName: 'ИП Dev Partner 3 B',
                businessType: 'IP',
                isDefault: false,
                now: $now,
            ),
        ];

        foreach ($rows as $row) {
            $id = $row['id'];
            unset($row['id']);

            DB::table('partner_legal_entities')->updateOrInsert(
                ['id' => $id],
                $row,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entityRow(
        int $id,
        int $partnerId,
        string $shopCode,
        string $taxId,
        string $title,
        string $organizationName,
        string $businessType,
        bool $isDefault,
        Carbon $now,
        ?string $kpp = null,
    ): array {
        return [
            'id' => $id,
            'partner_id' => $partnerId,
            'business_type' => $businessType,
            'title' => $title,
            'organization_name' => $organizationName,
            'tax_id' => $taxId,
            'kpp' => $kpp,
            'registration_number' => null,
            'city' => 'Москва',
            'zip' => '101000',
            'address' => 'Dev seed address',
            'ceo' => null,
            'bank_name' => null,
            'bank_bik' => null,
            'bank_account' => null,
            'sm_details_template' => null,
            'tinkoff_shop_code' => $shopCode,
            'sm_register_status' => 'REGISTERED',
            'registered_at' => $now,
            'bank_details_version' => null,
            'bank_details_last_updated_at' => null,
            'registration_verified_at' => null,
            'vat' => null,
            'sms_name' => null,
            'is_default' => $isDefault,
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }
}
