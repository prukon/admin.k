<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\FiscalReceipt;
use App\Models\ParentProfile;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\UserCustomPayment;
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
            'phone' => '+79990000002',
            'website' => 'https://school.example',
        ]);

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'organization_name' => 'ООО Школа футбола',
            'tax_id' => '7700000000',
            'vat' => 0,
        ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount_cents' => 350000,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => '2026-03-01',
            'meta' => [
                'month' => '2026-03-01',
                'team_id' => $chain['team']->id,
            ],
            'paid_at' => now(),
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum_cents' => 350000,
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
            'amount_cents' => 350000,
            'invoice_id' => 'pi_' . $intent->id,
            'account_id' => (string) $this->user->id,
            'idempotency_key' => 'income:test:' . $intent->id,
        ]);

        $builder = app(CloudKassirReceiptBuilder::class);
        $payload = $builder->build($receipt);

        $this->assertSame('7708806062', $payload['Inn']);
        $this->assertSame('Income', $payload['Type']);
        $this->assertSame(1, $payload['CustomerReceipt']['TaxationSystem']);
        $this->assertArrayNotHasKey('AgentSign', $payload['CustomerReceipt']);
        $this->assertSame('3500.00', $payload['CustomerReceipt']['Amounts']['Electronic']);
        $this->assertSame('https://school.example', $payload['CustomerReceipt']['CalculationPlace']);
        $this->assertTrue($payload['CustomerReceipt']['IsInternetPayment']);

        $item = $payload['CustomerReceipt']['Items'][0];

        $this->assertSame('6', $item['AgentSign']);

        $this->assertSame('Ежемесячный платеж за март', $item['Label']);
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

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
            'vat' => null,
        ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => '2026-03-01',
            'meta' => [
                'month' => '2026-03-01',
                'team_id' => $chain['team']->id,
            ],
            'paid_at' => now(),
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum_cents' => 10000,
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
            'amount_cents' => 10000,
            'invoice_id' => 'pi_' . $intent->id,
            'idempotency_key' => 'income:test2:' . $intent->id,
        ]);

        $payload = app(CloudKassirReceiptBuilder::class)->build($receipt);

        $this->assertNull($payload['CustomerReceipt']['Items'][0]['Vat']);
    }

    public function test_custom_payment_receipt_label_uses_admin_note(): void
    {
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.default_method', 4);
        Config::set('services.cloudkassir.default_object', 4);
        Config::set('services.cloudkassir.russia_time_zone', 2);
        Config::set('services.cloudkassir.agent.enabled', false);

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);

        $payment = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $chain['team']->id,
            'amount_cents' => 150000,
            'note' => 'Интенсив по выходным',
            'is_paid' => true,
        ]);

        $payload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeCustomPaymentReceipt($payment->id, $chain['team']->id)
        );
        $this->assertSame('Интенсив по выходным', $payload['CustomerReceipt']['Items'][0]['Label']);

        $payment->update(['note' => str_repeat('Я', 140)]);
        $longPayload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeCustomPaymentReceipt($payment->id, $chain['team']->id, 'income:custom:long')
        );
        $longLabel = $longPayload['CustomerReceipt']['Items'][0]['Label'];
        $this->assertSame(128, mb_strlen($longLabel));
        $this->assertSame(str_repeat('Я', 128), $longLabel);

        $payment->update(['note' => '   ']);
        $emptyPayload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeCustomPaymentReceipt($payment->id, $chain['team']->id, 'income:custom:empty')
        );
        $this->assertSame('Дополнительный платеж', $emptyPayload['CustomerReceipt']['Items'][0]['Label']);
    }

    public function test_receipt_email_prefers_parent_then_student_then_school(): void
    {
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.default_method', 4);
        Config::set('services.cloudkassir.default_object', 4);
        Config::set('services.cloudkassir.russia_time_zone', 2);
        Config::set('services.cloudkassir.agent.enabled', false);

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $teamId = (int) $chain['team']->id;
        $schoolEmail = (string) $this->partner->email;

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'Parent@Mail.ru',
        ]);
        $this->user->forceFill([
            'parent_id' => $parent->id,
            'email' => 'other@example.com',
        ])->save();

        $parentPayload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeMonthlyFeeReceipt($teamId, 'income:email:parent')
        );
        $this->assertSame('Parent@Mail.ru', $parentPayload['CustomerReceipt']['Email']);

        $this->user->forceFill(['email' => 'parent@mail.ru'])->save();
        $samePayload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeMonthlyFeeReceipt($teamId, 'income:email:same')
        );
        $this->assertSame('Parent@Mail.ru', $samePayload['CustomerReceipt']['Email']);

        $parent->update(['email' => '   ']);
        $this->user->forceFill(['email' => 'student@example.com'])->save();
        $studentPayload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeMonthlyFeeReceipt($teamId, 'income:email:student')
        );
        $this->assertSame('student@example.com', $studentPayload['CustomerReceipt']['Email']);

        $parent->update(['email' => null]);
        $this->user->forceFill(['email' => null])->save();
        $schoolPayload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeMonthlyFeeReceipt($teamId, 'income:email:school')
        );
        $this->assertSame($schoolEmail, $schoolPayload['CustomerReceipt']['Email']);

        $parent->update(['email' => 'Parent@Mail.ru']);
        $this->partner->forceFill(['email' => ''])->save();
        $custom = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $teamId,
            'amount_cents' => 150000,
            'note' => 'Форма',
            'is_paid' => true,
        ]);
        $customPayload = app(CloudKassirReceiptBuilder::class)->build(
            $this->makeCustomPaymentReceipt($custom->id, $teamId, 'income:email:custom')
        );
        $this->assertSame('Parent@Mail.ru', $customPayload['CustomerReceipt']['Email']);
    }

    private function makeMonthlyFeeReceipt(int $teamId, string $idempotencyKey): FiscalReceipt
    {
        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => '2026-03-01',
            'meta' => [
                'month' => '2026-03-01',
                'team_id' => $teamId,
            ],
            'paid_at' => now(),
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum_cents' => 10000,
            'payment_date' => '2026-03-01',
            'paid_at' => now(),
            'meta' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        return FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_intent_id' => $intent->id,
            'payable_id' => $payable->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount_cents' => 10000,
            'invoice_id' => 'pi_'.$intent->id,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function makeCustomPaymentReceipt(int $customPaymentId, int $teamId, string $idempotencyKey = 'income:custom:note'): FiscalReceipt
    {
        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'custom_payment_fee',
            'amount_cents' => 150000,
            'currency' => 'RUB',
            'status' => 'paid',
            'meta' => [
                'user_period_price_id' => $customPaymentId,
                'team_id' => $teamId,
            ],
            'paid_at' => now(),
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum_cents' => 150000,
            'payment_date' => 'Дополнительный платеж',
            'paid_at' => now(),
            'meta' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        return FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_intent_id' => $intent->id,
            'payable_id' => $payable->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount_cents' => 150000,
            'invoice_id' => 'pi_'.$intent->id,
            'account_id' => (string) $this->user->id,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
