<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Services\Tinkoff\TinkoffApiClient;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

final class TinkoffApiClientHttpFeatureTest extends CrmTestCase
{
    public function test_post_http_404_returns_json_without_throw_and_without_retry(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $res = TinkoffApiClient::post('https://securepay.tinkoff.ru', '/e2c/v2/CloseSpDeal', ['DealId' => '1']);

        $this->assertFalse((bool) ($res['Success'] ?? true));
        $this->assertSame(404, (int) ($res['http_status'] ?? 0));
        $this->assertSame('Not Found', (string) ($res['body'] ?? ''));
        Http::assertSentCount(1);
    }

    public function test_post_http_502_retries_then_returns_json_without_throw(): void
    {
        Http::fake(['*' => Http::response('nope', 502)]);

        $res = TinkoffApiClient::post('https://securepay.tinkoff.ru', '/v2/Init', ['Amount' => 1]);

        $this->assertFalse((bool) ($res['Success'] ?? true));
        $this->assertSame(502, (int) ($res['http_status'] ?? 0));
        Http::assertSentCount(2);
    }
}
