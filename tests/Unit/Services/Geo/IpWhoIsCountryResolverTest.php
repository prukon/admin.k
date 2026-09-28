<?php

namespace Tests\Unit\Services\Geo;

use App\Services\Geo\IpWhoIsCountryResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class IpWhoIsCountryResolverTest extends TestCase
{
    public function test_private_ip_is_not_looked_up(): void
    {
        Http::fake();

        $this->assertNull((new IpWhoIsCountryResolver())->resolve('127.0.0.1'));
        $this->assertNull((new IpWhoIsCountryResolver())->resolve('10.0.0.4'));
        Http::assertNothingSent();
    }

    public function test_public_ip_uses_country_code_and_russian_name(): void
    {
        Cache::flush();
        Http::fake([
            'ipwho.is/*' => Http::response([
                'success' => true,
                'country_code' => 'DE',
                'country' => 'Germany',
            ]),
        ]);

        $country = (new IpWhoIsCountryResolver())->resolve('203.0.113.10');

        $this->assertNotNull($country);
        $this->assertSame('DE', $country->code);
        $this->assertSame('Германия', $country->name);

        (new IpWhoIsCountryResolver())->resolve('203.0.113.10');
        Http::assertSentCount(1);
    }
}
