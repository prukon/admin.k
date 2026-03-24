<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendCloudKassirReceiptJob;
use App\Jobs\TinkoffProcessRefundJob;
use App\Models\FiscalReceipt;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Partner;
use App\Models\Refund;
use App\Models\UserPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Jobs\JobsTestCase;

class TinkoffProcessRefundJobCloudKassirReturnReceiptTest extends JobsTestCase
{
    public function test_tbank_refund_job_creates_cloudkassir_income_return_receipt_and_dispatches_send_job(): void
    {
        $partner = Partner::factory()->create();

        PaymentSystem::factory()
            ->tbank()
            ->testMode()
            ->create([
                'partner_id' => $partner->id,
            ]);

        $userId = 1001;

        $payable = Payable::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'type' => 'monthly_fee',
            'amount' => 1500,
            'currency' => 'RUB',
            'status' => 'paid',
            'month' => now()->startOfMonth()->format('Y-m-01'),
            'meta' => ['month' => now()->startOfMonth()->format('Y-m-01')],
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'summ' => $payable->amount,
        ]);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'tbank_payment_id' => 777001,
            'provider_inv_id' => 777001,
            'status' => 'paid',
            'out_sum' => $payable->amount,
        ]);

        UserPrice::factory()->paid()->create([
            'user_id' => $userId,
            'new_month' => $payable->month->format('Y-m-d'),
            'price' => (string) (int) $payable->amount,
        ]);

        $refund = Refund::create([
            'partner_id' => $partner->id,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'payment_id' => $payment->id,
            'amount' => 1500,
            'currency' => 'RUB',
            'status' => 'pending',
            'provider' => 'tbank',
            'meta' => [
                'payment_intent_id' => $intent->id,
                'tbank_payment_id' => (int) $intent->tbank_payment_id,
            ],
        ]);

        Queue::fake([SendCloudKassirReceiptJob::class]);

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Cancel' => Http::response([
                'Success' => true,
            ], 200),
        ]);

        (new TinkoffProcessRefundJob($refund->id))->handle();

        $refund->refresh();
        $payable->refresh();

        $this->assertSame('succeeded', (string) $refund->status);
        $this->assertSame('refunded', (string) $payable->status);

        $receipt = FiscalReceipt::query()
            ->where('partner_id', (int) $partner->id)
            ->where('payment_id', (int) $payment->id)
            ->where('payable_id', (int) $payable->id)
            ->where('type', FiscalReceipt::TYPE_INCOME_RETURN)
            ->where('provider', FiscalReceipt::PROVIDER_CLOUDKASSIR)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($receipt, 'Return (income_return) fiscal receipt must be created');
        $this->assertSame(FiscalReceipt::STATUS_PENDING, (string) $receipt->status);

        Queue::assertPushed(SendCloudKassirReceiptJob::class, function (SendCloudKassirReceiptJob $job) use ($receipt) {
            return (int) $job->fiscalReceiptId === (int) $receipt->id;
        });
    }
}

