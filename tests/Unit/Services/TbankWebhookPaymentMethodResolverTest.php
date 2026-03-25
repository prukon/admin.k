<?php

namespace Tests\Unit\Services;

use App\Services\Tinkoff\TbankWebhookPaymentMethodResolver;
use PHPUnit\Framework\TestCase;

class TbankWebhookPaymentMethodResolverTest extends TestCase
{
    private TbankWebhookPaymentMethodResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TbankWebhookPaymentMethodResolver();
    }

    public function test_data_source_tpay_maps_to_tpay(): void
    {
        $r = $this->resolver->resolve([
            'Data' => json_encode(['source' => 'TinkoffPay'], JSON_UNESCAPED_UNICODE),
        ], 'card');

        $this->assertSame('tpay', $r['intent']);
        $this->assertSame('tpay', $r['tinkoff']);
    }

    public function test_pan_falls_back_to_card(): void
    {
        $r = $this->resolver->resolve([
            'Pan' => '411111******1111',
        ], 'card');

        $this->assertSame('card', $r['intent']);
        $this->assertSame('card', $r['tinkoff']);
    }

    public function test_init_sbp_without_source_maps_to_sbp_qr(): void
    {
        $r = $this->resolver->resolve([], 'sbp');

        $this->assertSame('sbp_qr', $r['intent']);
        $this->assertSame('sbp', $r['tinkoff']);
    }
}
