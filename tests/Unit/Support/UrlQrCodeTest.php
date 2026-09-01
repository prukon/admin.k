<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\UrlQrCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UrlQrCodeTest extends TestCase
{
    public function test_png_data_uri_is_valid_png(): void
    {
        $uri = UrlQrCode::pngDataUri('https://example.test/lead/demo');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $raw = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($raw);
        $this->assertNotSame('', $raw);
        $this->assertStringStartsWith("\x89PNG", $raw);
    }

    public function test_modules_grid_is_square_and_has_finders(): void
    {
        $modules = UrlQrCode::modules('https://example.test/lead/demo');
        $n = count($modules);
        $this->assertGreaterThanOrEqual(21, $n);
        $this->assertSame($n, count($modules[0]));
        $this->assertTrue($modules[0][0]);
        $this->assertTrue($modules[$n - 1][0]);
        $this->assertTrue($modules[0][$n - 7]);
    }

    public function test_different_urls_produce_different_png(): void
    {
        $a = UrlQrCode::pngDataUri('https://example.test/lead/one');
        $b = UrlQrCode::pngDataUri('https://example.test/lead/two');

        $this->assertNotSame($a, $b);
    }

    public function test_empty_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlQrCode::modules('   ');
    }
}
