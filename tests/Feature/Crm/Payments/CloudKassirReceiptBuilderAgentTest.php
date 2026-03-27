<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\FiscalReceipt;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Services\CloudKassir\CloudKassirReceiptBuilder;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Crm\CrmTestCase;

class CloudKassirReceiptBuilderAgentTest extends CrmTestCase
{
    public function test_builder_adds_agent_and_purveyor_data_for_agent_scheme(): void
    {
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.default_method', 4);
        Config::set('services.cloudkassir.default_object', 4);
        Config::set('services.cloudkassir.russia_time_zone', 2);

        Config::set('services.cloudkassir.agent.enabled', true);
        Config::set('services.cloudkassir.agent.agent_sign', 6);
        Config::set('services.cloudkassir.agent.use_purveyor_data', true);
        Config::set('services.cloudkassir.agent.use_agent_data', true);
        Config::set('services.cloudkassir.agent.payment_agent_phone', '+79110263811');

        $this->partner->update([
            'organization_name' => 'ООО Школа футбола',
            'tax_id' => '7700000000',
            'phone' => '+79990000002',
            'website' => 'https://school.example',
            'taxation_system' => 0,
            'vat' => 0,
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
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount' => '3500.00',
            'invoice_id' => 'pi_' . $intent->id,
            'account_id' => (string) $this->user->id,
            'idempotency_key' => 'income:test:' . $intent->id,
        ]);

        $builder = app(CloudKassirReceiptBuilder::class);
        $payload = $builder->build($receipt);

        $this->assertSame('7708806062', $payload['Inn']);
        $this->assertSame('Income', $payload['Type']);
        $this->assertSame(1, $payload['CustomerReceipt']['TaxationSystem']);
        $this->assertSame('6', $payload['CustomerReceipt']['AgentSign']);
        $this->assertSame('3500.00', $payload['CustomerReceipt']['Amounts']['Electronic']);
        $this->assertSame('https://school.example', $payload['CustomerReceipt']['CalculationPlace']);
        $this->assertTrue($payload['CustomerReceipt']['IsInternetPayment']);

        $item = $payload['CustomerReceipt']['Items'][0];

        $this->assertArrayNotHasKey('AgentSign', $item);

        $this->assertSame('Абонемент за март', $item['Label']);
        $this->assertSame('3500.00', $item['Price']);
        $this->assertSame('3500.00', $item['Amount']);
        $this->assertSame(1, $item['Quantity']);
        $this->assertSame(0, $item['Vat']);
        $this->assertSame(4, $item['Method']);
        $this->assertSame(4, $item['Object']);

        $this->assertSame('+79110263811', $item['AgentData']['PaymentAgentPhone']);

        $this->assertSame('ООО Школа футбола', $item['PurveyorData']['Name']);
        $this->assertSame('7700000000', $item['PurveyorData']['Inn']);
        $this->assertSame('+79990000002', $item['PurveyorData']['Phone']);
    }

    public function test_builder_sends_null_vat_when_partner_vat_not_set(): void
    {
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.default_method', 4);
        Config::set('services.cloudkassir.default_object', 4);
        Config::set('services.cloudkassir.russia_time_zone', 2);
        Config::set('services.cloudkassir.agent.enabled', false);

        $this->partner->update([
            'tax_id' => '7700000000',
            'vat' => null,
        ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '100.00',
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
            'out_sum' => '100.00',
            'payment_date' => '2026-03-01',
            'paid_at' => now(),
            'meta' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        $receipt = FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_intent_id' => $intent->id,
            'payable_id' => $payable->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount' => '100.00',
            'invoice_id' => 'pi_' . $intent->id,
            'idempotency_key' => 'income:test2:' . $intent->id,
        ]);

        $payload = app(CloudKassirReceiptBuilder::class)->build($receipt);

        $this->assertNull($payload['CustomerReceipt']['Items'][0]['Vat']);
    }
}
