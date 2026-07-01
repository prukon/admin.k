<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\PaymentSystem;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

class DevPaymentSystemsSeeder extends Seeder
{
    use GuardsDevSeedData;

    /** @var list<int> */
    private const ROBOKASSA_PARTNER_IDS = [1, 2];

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        foreach (self::ROBOKASSA_PARTNER_IDS as $partnerId) {
            $this->seedRobokassaForPartner($partnerId);
        }

        $this->seedGlobalTbank();
    }

    private function seedRobokassaForPartner(int $partnerId): void
    {
        if (! Partner::query()->whereKey($partnerId)->exists()) {
            return;
        }

        $exists = PaymentSystem::query()
            ->where('partner_id', $partnerId)
            ->where('name', 'robokassa')
            ->exists();

        if ($exists) {
            return;
        }

        PaymentSystem::factory()->robokassa()->create([
            'partner_id' => $partnerId,
            'is_enabled' => true,
        ]);
    }

    private function seedGlobalTbank(): void
    {
        $exists = PaymentSystem::query()
            ->whereNull('partner_id')
            ->where('name', 'tbank')
            ->exists();

        if ($exists) {
            return;
        }

        PaymentSystem::factory()->tbank()->testMode()->create([
            'is_enabled' => true,
        ]);
    }
}
