<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Services\Payments\PaymentService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступность T‑Bank на витрине: глобальный терминал + ShopCode (юр. лицо или legacy tinkoff_partner_id).
 */
final class PaymentServiceTbankAvailabilityFeatureTest extends CrmTestCase
{
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
    }

    public function test_is_tbank_available_false_without_global_terminal(): void
    {
        $this->partner->update(['tinkoff_partner_id' => 'SHOP-1']);

        $this->assertFalse($this->service->isTbankAvailable($this->partner));
    }

    public function test_is_tbank_available_false_without_tinkoff_partner_id(): void
    {
        $this->seedGlobalTbank();
        $this->partner->update(['tinkoff_partner_id' => null]);

        $this->assertFalse($this->service->isTbankAvailable($this->partner->fresh()));
    }

    public function test_is_tbank_available_true_when_global_terminal_and_shop_code_present(): void
    {
        $this->seedGlobalTbank();
        $this->partner->update(['tinkoff_partner_id' => 'SHOP-AVAILABLE']);

        $this->assertTrue($this->service->isTbankAvailable($this->partner->fresh()));
    }

    public function test_is_tbank_available_false_when_global_terminal_disabled(): void
    {
        $this->seedGlobalTbank([], ['is_enabled' => false]);
        $this->partner->update(['tinkoff_partner_id' => 'SHOP-1']);

        $this->assertFalse($this->service->isTbankAvailable($this->partner->fresh()));
    }

    public function test_is_tbank_sbp_available_requires_amount_in_range(): void
    {
        $this->seedGlobalTbank();
        $this->partner->update(['tinkoff_partner_id' => 'SHOP-SBP']);

        $partner = $this->partner->fresh();

        $this->assertFalse($this->service->isTbankSbpAvailable($partner, null));
        $this->assertFalse($this->service->isTbankSbpAvailable($partner, 999));
        $this->assertTrue($this->service->isTbankSbpAvailable($partner, 1000));
        $this->assertTrue($this->service->isTbankSbpAvailable($partner, 100_000_000));
    }

    public function test_is_tbank_sbp_unavailable_for_foreign_partner_without_shop_code(): void
    {
        $this->seedGlobalTbank();

        $foreign = Partner::factory()->create(['tinkoff_partner_id' => null]);

        $this->assertFalse($this->service->isTbankSbpAvailable($foreign, 5000));
    }

    public function test_is_tbank_available_true_when_legal_entity_has_shop_code(): void
    {
        $this->seedGlobalTbank();
        $this->partner->update(['tinkoff_partner_id' => null]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-ENTITY')
            ->create();

        $this->assertTrue($this->service->isTbankAvailable($this->partner->fresh()));
    }

    public function test_is_tbank_available_uses_team_bound_legal_entity(): void
    {
        $this->seedGlobalTbank();
        $this->partner->update(['tinkoff_partner_id' => null]);

        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-TEAM')
            ->create(['is_default' => false]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-DEFAULT')
            ->create(['is_default' => true]);

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $entity->id,
        ]);

        $this->assertTrue($this->service->isTbankAvailable($this->partner->fresh(), $team));
    }

    public function test_is_tbank_available_false_when_team_entity_has_no_shop_code_and_no_legacy(): void
    {
        $this->seedGlobalTbank();
        $this->partner->update(['tinkoff_partner_id' => null]);

        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->create(['tinkoff_shop_code' => null, 'is_default' => false]);

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $entity->id,
        ]);

        $this->assertFalse($this->service->isTbankAvailable($this->partner->fresh(), $team));
    }
}
