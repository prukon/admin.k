<?php

declare(strict_types=1);

namespace App\Services\Geo;

interface IpCountryResolver
{
    public function resolve(?string $ip): ?IpCountry;
}
