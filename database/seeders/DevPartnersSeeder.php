<?php

namespace Database\Seeders;

use App\Models\Partner;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

class DevPartnersSeeder extends Seeder
{
    use GuardsDevSeedData;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        Partner::factory()->count(3)->create();
    }
}