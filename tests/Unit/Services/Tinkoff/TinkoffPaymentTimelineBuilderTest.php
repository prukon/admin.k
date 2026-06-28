<?php

namespace Tests\Unit\Services\Tinkoff;

use App\Models\FiscalReceipt;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPaymentTimelineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Crm\CrmTestCase;

class TinkoffPaymentTimelineBuilderTest extends CrmTestCase
{
    use RefreshDatabase;

    private TinkoffPaymentTimelineBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(TinkoffPaymentTimelineBuilder::class);
    }

    public function test_build_marks_init_and_confirmed_steps_done(): void
    {
        $confirmedAt = now()->subHour();

        $payment = TinkoffPayment::create([
            'order_id' => 'order-timeline-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-timeline-1',
            'confirmed_at' => $confirmedAt,
        ]);

        $steps = $this->builder->build($payment, collect());

        $this->assertSame('done', $steps[0]['state']);
        $this->assertSame('Платёжный запрос', $steps[0]['label']);
        $this->assertSame('done', $steps[1]['state']);
        $this->assertSame('Оплата подтверждена', $steps[1]['label']);
        $this->assertSame($confirmedAt->format('Y-m-d H:i'), $steps[1]['at']->format('Y-m-d H:i'));
        $this->assertSame('fiscal_income', $steps[2]['key']);
        $this->assertSame('Чек оплаты', $steps[2]['label']);
        $this->assertSame('pending', $steps[3]['state']);
        $this->assertSame('pending', $steps[4]['state']);
        $this->assertCount(5, $steps);
    }

    public function test_build_marks_payout_steps_when_payout_completed(): void
    {
        $payment = TinkoffPayment::create([
            'order_id' => 'order-timeline-2',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-timeline-2',
            'confirmed_at' => now()->subHours(2),
        ]);

        $payout = TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $payment->deal_id,
            'amount' => 9500,
            'is_final' => true,
            'status' => 'COMPLETED',
            'source' => 'auto',
            'completed_at' => now()->subHour(),
        ]);

        $steps = $this->builder->build($payment, collect([$payout]));

        $this->assertSame('done', $steps[3]['state']);
        $this->assertSame('Создана выплата', $steps[3]['label']);
        $this->assertSame('done', $steps[4]['state']);
        $this->assertSame('Выплата выполнена', $steps[4]['label']);
    }

    public function test_build_marks_failed_payment_step(): void
    {
        $payment = TinkoffPayment::create([
            'order_id' => 'order-timeline-3',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CANCELED',
            'canceled_at' => now(),
        ]);

        $steps = $this->builder->build($payment, collect());

        $this->assertSame('failed', $steps[1]['state']);
        $this->assertSame('pending', $steps[2]['state']);
        $this->assertSame('После подтверждения оплаты', $steps[2]['hint']);
    }

    public function test_build_includes_income_receipt_and_refund_steps(): void
    {
        $payment = TinkoffPayment::create([
            'order_id' => 'order-timeline-4',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-timeline-4',
            'tinkoff_payment_id' => 440001,
            'confirmed_at' => now()->subHours(3),
        ]);

        $ledgerPayment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '440001',
            'deal_id' => $payment->deal_id,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => 100.00,
            'receipt_url' => 'https://receipts.ru/timeline-income',
            'receipt_datetime' => now()->subHours(2),
        ]);

        Refund::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => Payable::create([
                'partner_id' => $this->partner->id,
                'user_id' => $this->user->id,
                'type' => 'club_fee',
                'amount' => '100.00',
                'currency' => 'RUB',
                'status' => 'paid',
            ])->id,
            'payment_id' => $ledgerPayment->id,
            'amount' => 100.00,
            'currency' => 'RUB',
            'status' => 'succeeded',
            'provider' => 'tbank',
            'processed_at' => now()->subHour(),
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME_RETURN,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => 100.00,
            'receipt_url' => 'https://receipts.ru/timeline-return',
            'receipt_datetime' => now()->subMinutes(30),
        ]);

        $steps = $this->builder->build($payment, collect());

        $this->assertSame('done', $steps[2]['state']);
        $this->assertSame('https://receipts.ru/timeline-income', $steps[2]['url']);
        $this->assertSame('refund', $steps[5]['key']);
        $this->assertSame('done', $steps[5]['state']);
        $this->assertSame('fiscal_return', $steps[6]['key']);
        $this->assertSame('done', $steps[6]['state']);
        $this->assertSame('https://receipts.ru/timeline-return', $steps[6]['url']);
        $this->assertCount(7, $steps);
    }
}
