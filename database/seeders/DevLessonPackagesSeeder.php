<?php

namespace Database\Seeders;

use App\Models\LessonPackage;
use App\Models\Partner;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

class DevLessonPackagesSeeder extends Seeder
{
    use GuardsDevSeedData;

    /**
     * @return list<array<string, mixed>>
     */
    private function packageTemplates(): array
    {
        return [
            [
                'name' => 'Гибкий 8 занятий',
                'schedule_type' => 'flexible',
                'duration_days' => 90,
                'lessons_count' => 8,
                'price_cents' => 1200000,
                'freeze_enabled' => true,
                'freeze_days' => 7,
            ],
            [
                'name' => 'Гибкий 12 занятий',
                'schedule_type' => 'flexible',
                'duration_days' => 120,
                'lessons_count' => 12,
                'price_cents' => 1680000,
                'freeze_enabled' => false,
                'freeze_days' => 0,
            ],
            [
                'name' => 'Фиксированный 8 занятий',
                'schedule_type' => 'fixed',
                'duration_days' => 60,
                'lessons_count' => 8,
                'price_cents' => 1400000,
                'freeze_enabled' => true,
                'freeze_days' => 14,
            ],
            [
                'name' => 'Фиксированный 4 занятия',
                'schedule_type' => 'fixed',
                'duration_days' => 45,
                'lessons_count' => 4,
                'price_cents' => 800000,
                'freeze_enabled' => false,
                'freeze_days' => 0,
            ],
            [
                'name' => 'Разовое занятие',
                'schedule_type' => 'no_schedule',
                'duration_days' => 1,
                'lessons_count' => 1,
                'price_cents' => 250000,
                'freeze_enabled' => false,
                'freeze_days' => 0,
            ],
        ];
    }

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
            foreach ($this->packageTemplates() as $template) {
                LessonPackage::query()->create([
                    'partner_id' => $partnerId,
                    'is_active' => true,
                    ...$template,
                ]);
            }
        }
    }
}
