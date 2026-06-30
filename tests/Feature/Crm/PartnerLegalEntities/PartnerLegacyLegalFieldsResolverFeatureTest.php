<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerLegalEntities;

use App\Models\PartnerLegalEntity;
use App\Services\PartnerLegalEntities\LegalEntityResolver;
use App\Services\Payments\PaymentService;
use App\Services\Tinkoff\TbankTerminalConfig;
use Tests\Feature\Crm\CrmTestCase;

/**
 * LegalEntityResolver и PaymentService: только справочник юр. лиц, без legacy partners.
 */
final class PartnerLegacyLegalFieldsResolverFeatureTest extends CrmTestCase
{
    private LegalEntityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(LegalEntityResolver::class);
    }

    public function test_fiscal_tax_id_ignores_partner_legacy_tax_id(): void
    {
        $this->partner->update(['tax_id' => '1111111111']);

        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->create(['tax_id' => '2222222222']);

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertSame('2222222222', $this->resolver->fiscalTaxId($this->partner, $resolution));
    }

    public function test_fiscal_tax_id_is_null_without_legal_entity_even_if_partner_has_tax_id(): void
    {
        $this->partner->update(['tax_id' => '1111111111', 'tinkoff_partner_id' => 'LEGACY-SHOP']);

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertNull($resolution->entity);
        $this->assertNull($this->resolver->fiscalTaxId($this->partner, $resolution));
    }

    public function test_fiscal_vat_ignores_partner_legacy_vat(): void
    {
        $this->partner->update(['vat' => 20]);

        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->create(['vat' => 10]);

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertSame(10, $this->resolver->fiscalVat($this->partner, $resolution));
    }

    public function test_shop_code_ignores_partner_tinkoff_partner_id_without_legal_entity(): void
    {
        $this->partner->update(['tinkoff_partner_id' => 'LEGACY-SHOP']);

        $resolution = $this->resolver->forPartner((int) $this->partner->id);

        $this->assertNull($this->resolver->shopCode($this->partner, $resolution));
        $this->assertFalse($this->resolver->hasRegisteredShopCode($this->partner));
    }

    public function test_payment_service_tbank_unavailable_without_registered_legal_entity(): void
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
            $this->markTestSkipped('T-Bank terminal is not globally active in this environment.');
        }

        $this->partner->update([
            'tax_id' => '1111111111',
            'tinkoff_partner_id' => 'LEGACY-SHOP',
        ]);

        $service = app(PaymentService::class);

        $this->assertFalse($service->isTbankAvailable($this->partner));
    }

    public function test_payment_service_tbank_available_with_registered_legal_entity(): void
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
            $this->markTestSkipped('T-Bank terminal is not globally active in this environment.');
        }

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-PAYMENT-TEST')
            ->create();

        $service = app(PaymentService::class);

        $this->assertTrue($service->isTbankAvailable($this->partner));
    }
}
