<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Partner;
use Database\Seeders\Concerns\AssignsTeamDirectoryLinks;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevLocationsSeeder extends Seeder
{
    use AssignsTeamDirectoryLinks;
    use GuardsDevSeedData;

    /** @var list<string> */
    private const VENUE_NAMES = [
        'Стадион Лужники',
        'Стадион Динамо',
        'Арена ДЮСШ',
        'Спорткомплекс «Олимпийский»',
        'Стадион «Локомотив»',
        'Манеж «Зенит»',
        'Футбольный центр «Чемпион»',
        'Арена «Металлист»',
        'Спортбаза «Юность»',
        'Стадион «Торпедо»',
        'Крытый манеж «Восток»',
        'ФОК «Олимп»',
        'Поле «Север»',
        'Тренировочная база «Рассвет»',
        'Стадион «Спартак»',
        'Площадка «Центральная»',
        'Арена «Кристалл»',
        'Стадион «Фили»',
    ];

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $partnerIds = Partner::query()->pluck('id')->all();

        if ($partnerIds === []) {
            return;
        }

        foreach ($partnerIds as $partnerId) {
            $districtIds = DB::table('districts')
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $names = collect(self::VENUE_NAMES)->shuffle()->take(5);

            foreach ($names as $index => $name) {
                Location::factory()->create([
                    'partner_id' => $partnerId,
                    'district_id' => $districtIds !== []
                        ? $districtIds[$index % count($districtIds)]
                        : null,
                    'name' => $name,
                    'is_enabled' => true,
                ]);
            }
        }

        $this->assignLocationsToAllTeams();
    }
}
