<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Guardrails: отключение при группах, блок sm-полей после ShopCode.
 */
final class LegalEntitiesGuardrailsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions(['legal_entities.view', 'legal_entities.manage']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_cannot_disable_legal_entity_when_teams_are_linked(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'is_enabled' => true,
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'title' => $entity->title,
            'is_default' => true,
            'is_enabled' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['is_enabled']);

        $this->assertTrue($entity->fresh()->is_enabled);
    }

    public function test_can_disable_legal_entity_when_no_teams_linked(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'is_enabled' => true,
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'title' => $entity->title,
            'is_default' => true,
            'is_enabled' => false,
        ])->assertOk();

        $this->assertFalse($entity->fresh()->is_enabled);
    }

    public function test_registered_entity_rejects_tax_id_change_via_crud(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-GUARD-1')
            ->create(['tax_id' => '7700000001']);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'title' => $entity->title,
            'tax_id' => '7700000099',
            'is_default' => true,
            'is_enabled' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);

        $this->assertSame('7700000001', $entity->fresh()->tax_id);
    }

    public function test_registered_entity_allows_fiscal_fields_via_crud(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-GUARD-2')
            ->create(['vat' => null, 'taxation_system' => null]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'title' => $entity->title,
            'tax_id' => $entity->tax_id,
            'taxation_system' => 1,
            'vat' => 20,
            'is_default' => true,
            'is_enabled' => true,
        ])->assertOk();

        $fresh = $entity->fresh();
        $this->assertSame(1, (int) $fresh->taxation_system);
        $this->assertSame(20, (int) $fresh->vat);
    }

    public function test_unregistered_entity_allows_tax_id_change_via_crud(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tax_id' => '7700000001',
            'tinkoff_shop_code' => null,
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'title' => $entity->title,
            'tax_id' => '7700000099',
            'is_default' => true,
            'is_enabled' => true,
        ])->assertOk();

        $this->assertSame('7700000099', $entity->fresh()->tax_id);
    }
}
