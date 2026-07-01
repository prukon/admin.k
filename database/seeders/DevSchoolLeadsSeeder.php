<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Partner;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

            $districtIds = DB::table('districts')
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $sportTypeIds = DB::table('sport_types')
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $locations = Location::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->get(['id', 'district_id']);

            for ($i = 0; $i < $count; $i++) {
                $districtId = $districtIds !== []
                    ? $districtIds[array_rand($districtIds)]
                    : null;

                SchoolLead::factory()
                    ->forPartner((int) $partnerId, $widget)
                    ->create([
                        'district_id' => $districtId,
                        'location_id' => $this->pickLocationId($locations, $districtId),
                        'sport_type_id' => $sportTypeIds !== []
                            ? $sportTypeIds[array_rand($sportTypeIds)]
                            : null,
                    ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Location>  $locations
     */
    private function pickLocationId($locations, ?int $districtId): ?int
    {
        if ($locations->isEmpty()) {
            return null;
        }

        if ($districtId !== null) {
            $matching = $locations
                ->where('district_id', $districtId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($matching !== []) {
                return $matching[array_rand($matching)];
            }
        }

        $all = $locations->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $all[array_rand($all)];
    }
}
