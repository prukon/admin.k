<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\PaymentSystem;
use Illuminate\Database\Seeder;

class DevPaymentSystemsSeeder extends Seeder
{
    public function run(): void
    {
        $partnerIds = Partner::pluck('id')->toArray();

        if (empty($partnerIds)) {
            return;
        }

        $randomPartnerId = $partnerIds[array_rand($partnerIds)];

        PaymentSystem::factory()->robokassa()->create([
            'partner_id' => $randomPartnerId,
        ]);

        PaymentSystem::factory()->tbank()->testMode()->create([
            'partner_id' => $randomPartnerId,
        ]);
    }
}