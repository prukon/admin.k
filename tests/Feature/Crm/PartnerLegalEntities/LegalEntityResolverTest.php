<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerLegalEntities;

use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Services\PartnerLegalEntities\LegalEntityResolver;
use Tests\Feature\Crm\CrmTestCase;

final class LegalEntityResolverTest extends CrmTestCase
{
    private LegalEntityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(LegalEntityResolver::class);
    }

    public function test_for_team_uses_explicit_legal_entity_binding(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-TEAM-BOUND')
            ->create(['is_default' => false]);

        $other = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-DEFAULT')
            ->create(['is_default' => true]);

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $entity->id,
        ]);

        $resolution = $this->resolver->forTeam($team);

        $this->assertSame($entity->id, $resolution->entity?->id);
        $this->assertFalse($resolution->usedDefaultFallback);
        $this->assertSame('SHOP-TEAM-BOUND', $this->resolver->shopCode($this->partner, $resolution));
    }

    public function test_for_team_without_binding_returns_null_in_multi_entity_mode(): void
    {
        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-A')
            ->create(['is_default' => true]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-B')
            ->create(['is_default' => false]);

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => null,
        ]);

        $resolution = $this->resolver->forTeam($team);

        $this->assertNull($resolution->entity);
        $this->assertFalse($this->resolver->hasRegisteredShopCode($this->partner->fresh(), $team));
    }

    public function test_for_partner_with_single_active_entity(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-SINGLE')
            ->create();

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertSame($entity->id, $resolution->entity?->id);
        $this->assertFalse($resolution->usedDefaultFallback);
    }

    public function test_for_partner_with_multiple_entities_uses_default_with_fallback_flag(): void
    {
        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-NON-DEFAULT')
            ->create(['is_default' => false, 'title' => 'ЮЛ 1']);

        $default = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-DEFAULT-FALLBACK')
            ->create(['is_default' => true, 'title' => 'ЮЛ 2']);

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertSame($default->id, $resolution->entity?->id);
        $this->assertTrue($resolution->usedDefaultFallback);
    }

    public function test_shop_code_is_null_without_legal_entity(): void
    {
        $this->partner->update(['tinkoff_partner_id' => 'LEGACY-SHOP']);

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertNull($resolution->entity);
        $this->assertNull($this->resolver->shopCode($this->partner->fresh(), $resolution));
        $this->assertFalse($this->resolver->hasRegisteredShopCode($this->partner->fresh()));
    }

    public function test_shop_code_from_registered_legal_entity(): void
    {
        $this->partner->update(['tinkoff_partner_id' => 'LEGACY-SHOP']);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('ENTITY-SHOP')
            ->create();

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertSame('ENTITY-SHOP', $this->resolver->shopCode($this->partner->fresh(), $resolution));
    }

    public function test_resolve_legal_entity_id_from_init_data_team_id(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-INIT')
            ->create();

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $entity->id,
        ]);

        $legalEntityId = $this->resolver->resolveLegalEntityIdFromInitData(
            (int) $this->partner->id,
            ['team_id' => (string) $team->id],
        );

        $this->assertSame($entity->id, $legalEntityId);
    }

    public function test_disabled_entities_are_ignored(): void
    {
        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-DISABLED')
            ->disabled()
            ->create();

        $this->partner->update(['tinkoff_partner_id' => 'LEGACY-ONLY']);

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertNull($resolution->entity);
        $this->assertNull($this->resolver->shopCode($this->partner->fresh(), $resolution));
    }
}
