<?php

namespace Tests\Unit\Services\Tinkoff;

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
        $this->assertSame('pending', $steps[2]['state']);
        $this->assertSame('pending', $steps[3]['state']);
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

        $this->assertSame('done', $steps[2]['state']);
        $this->assertSame('Создана выплата', $steps[2]['label']);
        $this->assertSame('done', $steps[3]['state']);
        $this->assertSame('Выплата выполнена', $steps[3]['label']);
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
    }
}
