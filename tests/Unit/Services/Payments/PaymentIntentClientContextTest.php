<?php

namespace Tests\Unit\Services\Payments;

use App\Services\Payments\PaymentIntentClientContext;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentIntentClientContextTest extends TestCase
{
    #[Test]
    public function it_parses_typical_iphone_safari_user_agent(): void
    {
        $request = Request::create('/pay', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'HTTP_REFERER' => 'https://school.example/payments',
            'REMOTE_ADDR' => '203.0.113.5',
        ]);

        $row = PaymentIntentClientContext::fromRequest($request);

        $this->assertSame('mobile', $row['client_device_type']);
        $this->assertNotEmpty($row['client_os_family']);
        $this->assertNotEmpty($row['client_browser_family']);
        $this->assertStringContainsString('iPhone', (string) $row['client_user_agent']);
        $this->assertSame('203.0.113.5', $row['client_ip']);
        $this->assertSame('https://school.example/payments', $row['client_referrer']);
    }

    #[Test]
    public function it_stores_ip_and_referrer_when_user_agent_missing(): void
    {
        $request = Request::create('/pay', 'POST', [], [], [], [
            'HTTP_REFERER' => 'https://origin.example/',
            'REMOTE_ADDR' => '198.51.100.2',
        ]);
        // В CLI PHPUnit у Request часто есть дефолтный User-Agent — сбрасываем явно.
        $request->headers->set('User-Agent', '');

        $row = PaymentIntentClientContext::fromRequest($request);

        $this->assertNull($row['client_user_agent']);
        $this->assertNull($row['client_device_type']);
        $this->assertSame('198.51.100.2', $row['client_ip']);
        $this->assertSame('https://origin.example/', $row['client_referrer']);
    }

    #[Test]
    public function null_request_yields_all_nulls(): void
    {
        $row = PaymentIntentClientContext::fromRequest(null);

        foreach ($row as $v) {
            $this->assertNull($v);
        }
    }
}
