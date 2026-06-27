<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerLegalEntities;

use App\Models\FiscalReceipt;
use App\Models\PartnerLegalEntity;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\Team;
use App\Services\CloudKassir\CloudKassirReceiptBuilder;
use App\Services\PartnerLegalEntities\LegalEntityResolver;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Crm\CrmTestCase;

final class LegalEntityResolverGuardrailsTest extends CrmTestCase
{
    private LegalEntityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(LegalEntityResolver::class);
    }

    public function test_for_team_ignores_disabled_bound_entity_and_falls_back_to_default(): void
    {
        $disabled = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-DISABLED-BOUND')
            ->disabled()
            ->create(['is_default' => false, 'title' => 'Отключённое']);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-OTHER-ACTIVE')
            ->create(['is_default' => false, 'title' => 'Второе активное']);

        $default = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-DEFAULT-ACTIVE')
            ->create(['is_default' => true, 'title' => 'Основное']);

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $disabled->id,
        ]);

        $resolution = $this->resolver->forTeam($team);

        $this->assertSame($default->id, $resolution->entity?->id);
        $this->assertTrue($resolution->usedDefaultFallback);
        $this->assertSame(
            'SHOP-DEFAULT-ACTIVE',
            $this->resolver->shopCode($this->partner->fresh(), $resolution),
        );
    }

    public function test_for_fiscal_receipt_ignores_inactive_snapshot_and_resolves_from_payable(): void
    {
        $disabled = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-FISCAL-DISABLED')
            ->disabled()
            ->create(['tax_id' => '1111111111']);

        $active = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->create([
                'tax_id' => '2222222222',
                'is_default' => true,
            ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'custom',
            'amount' => '1000.00',
            'currency' => 'RUB',
            'status' => 'paid',
            'meta' => [],
            'paid_at' => now(),
        ]);

        $receipt = FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payable_id' => $payable->id,
            'legal_entity_id' => $disabled->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount' => '1000.00',
            'invoice_id' => 'pi_resolver_guard',
            'account_id' => (string) $this->user->id,
            'idempotency_key' => 'income:resolver:guard',
        ]);

        $resolution = $this->resolver->forFiscalReceipt($receipt);

        $this->assertSame($active->id, $resolution->entity?->id);
        $this->assertSame(
            '2222222222',
            $this->resolver->fiscalTaxId($this->partner->fresh(), $resolution),
        );
    }

    public function test_cloud_kassir_builder_uses_active_entity_when_snapshot_is_disabled(): void
    {
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.default_method', 4);
        Config::set('services.cloudkassir.default_object', 4);
        Config::set('services.cloudkassir.russia_time_zone', 2);
        Config::set('services.cloudkassir.agent.enabled', true);
        Config::set('services.cloudkassir.agent.use_purveyor_data', true);
        Config::set('services.cloudkassir.agent.payment_agent_phone', '+79110263811');

        $this->partner->update([
            'email' => 'school@example.test',
            'website' => 'https://school.example',
        ]);

        $disabled = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-CK-DISABLED')
            ->disabled()
            ->create(['tax_id' => '3333333333', 'organization_name' => 'Disabled Org']);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->create([
                'tax_id' => '4444444444',
                'organization_name' => 'Active Org',
                'is_default' => true,
            ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '3500.00',
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => '2026-03-01',
            'meta' => ['month' => '2026-03-01'],
            'paid_at' => now(),
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '3500.00',
            'payment_date' => '2026-03-01',
            'paid_at' => now(),
            'meta' => json_encode(['user_name' => $this->user->name], JSON_UNESCAPED_UNICODE),
        ]);

        $receipt = FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_intent_id' => $intent->id,
            'payable_id' => $payable->id,
            'legal_entity_id' => $disabled->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount' => '3500.00',
            'invoice_id' => 'pi_guard_' . $intent->id,
            'account_id' => (string) $this->user->id,
            'idempotency_key' => 'income:guard:' . $intent->id,
        ]);

        $payload = app(CloudKassirReceiptBuilder::class)->build($receipt);
        $item = $payload['CustomerReceipt']['Items'][0];

        $this->assertSame('4444444444', $item['PurveyorData']['Inn']);
        $this->assertSame('Active Org', $item['PurveyorData']['Name']);
    }
}
