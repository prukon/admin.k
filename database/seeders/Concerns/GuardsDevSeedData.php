<?php

namespace Database\Seeders\Concerns;

trait GuardsDevSeedData
{
    protected function abortUnlessDevSeedEnabled(): bool
    {
        if (! env('SEED_DEV_DATA', false)) {
            return false;
        }

        return true;
    }
}
