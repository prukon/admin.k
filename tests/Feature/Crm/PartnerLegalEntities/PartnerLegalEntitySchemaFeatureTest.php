<?php

namespace Tests\Feature\Crm\PartnerLegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;

class PartnerLegalEntitySchemaFeatureTest extends CrmTestCase
{
    public function test_partner_legal_entities_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('partner_legal_entities'));

        foreach ([
            'partner_id',
            'business_type',
            'title',
            'organization_name',
            'tax_id',
            'kpp',
            'registration_number',
            'tinkoff_shop_code',
            'sm_register_status',
            'is_default',
            'is_enabled',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('partner_legal_entities', $column),
                "Missing column partner_legal_entities.{$column}"
            );
        }

        $this->assertFalse(
            Schema::hasColumn('partner_legal_entities', 'taxation_system'),
            'Column partner_legal_entities.taxation_system must be removed'
        );
    }

    public function test_snapshot_and_team_foreign_keys_exist(): void
    {
        foreach (['teams', 'tinkoff_payments', 'tinkoff_payouts', 'fiscal_receipts'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'legal_entity_id'), "Missing {$table}.legal_entity_id");
        }
    }

    public function test_can_create_legal_entity_with_enum_and_relationships(): void
    {
        $partner = $this->partner;

        $entity = PartnerLegalEntity::factory()->for($partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::ANO,
            'title' => 'АНО «Спорт»',
            'tax_id' => '7701234567',
        ]);

        $this->assertSame(PartnerLegalEntityBusinessType::ANO, $entity->business_type);
        $this->assertSame('АНО', $entity->business_type->label());
        $this->assertFalse($entity->is_registered);

        $team = Team::factory()->create([
            'partner_id' => $partner->id,
            'legal_entity_id' => $entity->id,
        ]);

        $this->assertSame($entity->id, $team->legalEntity->id);
        $this->assertCount(1, $partner->fresh()->legalEntities);
    }

    public function test_registered_accessor_when_shop_code_present(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SHOP-ABC')->create();

        $this->assertTrue($entity->is_registered);
        $this->assertTrue(
            PartnerLegalEntity::query()->registered()->whereKey($entity->id)->exists()
        );
    }

    public function test_team_legal_entity_null_on_delete(): void
    {
        $partner = $this->partner;
        $entity = PartnerLegalEntity::factory()->for($partner)->create();
        $team = Team::factory()->create([
            'partner_id' => $partner->id,
            'legal_entity_id' => $entity->id,
        ]);

        $entity->forceDelete();

        $this->assertNull($team->fresh()->legal_entity_id);
    }

    public function test_payment_snapshot_legal_entity_relation(): void
    {
        $partner = $this->partner;
        $entity = PartnerLegalEntity::factory()->for($partner)->create();

        $payment = TinkoffPayment::create([
            'order_id' => 'order-' . uniqid(),
            'partner_id' => $partner->id,
            'legal_entity_id' => $entity->id,
            'amount' => 10000,
            'status' => 'NEW',
        ]);

        $this->assertSame($entity->id, $payment->legalEntity->id);

        $payout = TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $partner->id,
            'legal_entity_id' => $entity->id,
            'deal_id' => 'deal-1',
            'amount' => 9000,
            'is_final' => true,
            'status' => 'INITIATED',
        ]);

        $this->assertSame($entity->id, $payout->legalEntity->id);
    }

    public function test_business_type_enum_values(): void
    {
        $this->assertSame(['OOO', 'IP', 'ANO', 'NKO'], PartnerLegalEntityBusinessType::values());
    }
}
