<?php

declare(strict_types=1);

namespace App\Services\Geo;

final class IpCountry
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
    ) {}
}
