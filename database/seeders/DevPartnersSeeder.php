<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class DevPartnersSeeder extends Seeder
{
    public function run(): void
    {
        Partner::factory()->count(3)->create();
    }
}