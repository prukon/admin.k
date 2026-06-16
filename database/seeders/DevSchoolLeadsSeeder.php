<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Partner;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

class DevSchoolLeadsSeeder extends Seeder
{
    use GuardsDevSeedData;

    private const TOTAL_LEADS = 100;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $partnerIds = Partner::query()->pluck('id')->all();

        if ($partnerIds === []) {
            return;
        }

        $widgetService = app(PartnerWidgetService::class);
        $remaining = self::TOTAL_LEADS;
        $partnerCount = count($partnerIds);

        foreach ($partnerIds as $index => $partnerId) {
            $count = ($index === $partnerCount - 1)
                ? $remaining
                : intdiv(self::TOTAL_LEADS, $partnerCount);

            $remaining -= $count;

            if ($count < 1) {
                continue;
            }

            $widget = $widgetService->ensureForPartner((int) $partnerId);

            $locationIds = Location::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->pluck('id')
                ->all();

            for ($i = 0; $i < $count; $i++) {
                SchoolLead::factory()
                    ->forPartner((int) $partnerId, $widget)
                    ->create([
                        'location_id' => $locationIds !== []
                            ? $locationIds[array_rand($locationIds)]
                            : null,
                    ]);
            }
        }
    }
}
