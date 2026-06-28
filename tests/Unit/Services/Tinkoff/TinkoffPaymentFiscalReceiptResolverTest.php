<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tinkoff;

use App\Models\FiscalReceipt;
use App\Models\Payment;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffPaymentFiscalReceiptResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Crm\CrmTestCase;

final class TinkoffPaymentFiscalReceiptResolverTest extends CrmTestCase
{
    use RefreshDatabase;

    private TinkoffPaymentFiscalReceiptResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(TinkoffPaymentFiscalReceiptResolver::class);
    }

    public function test_resolves_income_receipt_by_ledger_payment_number(): void
    {
        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => 'order-fiscal-' . uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 50000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => 912345678,
        ]);

        $ledgerPayment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '912345678',
            'deal_id' => $tinkoffPayment->deal_id,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => 500.00,
            'receipt_url' => 'https://receipts.ru/show-card-receipt',
        ]);

        $resolved = $this->resolver->resolve($tinkoffPayment);

        $this->assertTrue($resolved['income']['has_url']);
        $this->assertSame('https://receipts.ru/show-card-receipt', $resolved['income']['url']);
        $this->assertFalse($resolved['return']['has_url']);
    }

    public function test_ignores_non_receipts_ru_urls(): void
    {
        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => 'order-fiscal-invalid-' . uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 50000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => 912345679,
        ]);

        $ledgerPayment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '912345679',
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount' => 500.00,
            'receipt_url' => 'https://example.com/not-a-receipt',
        ]);

        $resolved = $this->resolver->resolve($tinkoffPayment);

        $this->assertFalse($resolved['income']['has_url']);
        $this->assertSame('Чек не сформирован', $resolved['income']['hint']);
    }
}
