<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Partner;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

class DevLocationsSeeder extends Seeder
{
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
            $names = collect(self::VENUE_NAMES)->shuffle()->take(5);

            foreach ($names as $name) {
                Location::factory()->create([
                    'partner_id' => $partnerId,
                    'name' => $name,
                    'is_enabled' => true,
                ]);
            }
        }
    }
}
