<?php

namespace Database\Seeders;

use App\Models\TrainerProfile;
use App\Support\Money;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Случайные оклад и ставка за тренировку (в копейках) для всех trainer_profiles на dev-стенде.
 */
class DevTrainerSalaryDefaultsSeeder extends Seeder
{
    use GuardsDevSeedData;

    private const BASE_SALARY_MIN = 8000.0;

    private const BASE_SALARY_MAX = 85000.0;

    private const RATE_PER_TRAINING_MIN = 200.0;

    private const RATE_PER_TRAINING_MAX = 3500.0;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        if (! Schema::hasTable('trainer_profiles')
            || ! Schema::hasColumn('trainer_profiles', 'default_base_salary_cents')
            || ! Schema::hasColumn('trainer_profiles', 'default_rate_per_training_cents')) {
            $this->command?->warn('DevTrainerSalaryDefaultsSeeder: колонки ЗП в trainer_profiles отсутствуют, пропуск.');

            return;
        }

        $updated = 0;

        TrainerProfile::query()
            ->orderBy('id')
            ->each(function (TrainerProfile $profile) use (&$updated): void {
                $profile->forceFill([
                    'default_base_salary_cents' => self::randomMoneyCents(
                        self::BASE_SALARY_MIN,
                        self::BASE_SALARY_MAX,
                    ),
                    'default_rate_per_training_cents' => self::randomMoneyCents(
                        self::RATE_PER_TRAINING_MIN,
                        self::RATE_PER_TRAINING_MAX,
                    ),
                ])->save();

                $updated++;
            });

        $this->command?->info("DevTrainerSalaryDefaultsSeeder: обновлено профилей тренеров: {$updated}.");
    }

    public static function randomMoneyCents(float $minRub, float $maxRub): int
    {
        $minCents = Money::toCentsOrFail($minRub);
        $maxCents = Money::toCentsOrFail($maxRub);

        if ($maxCents < $minCents) {
            $maxCents = $minCents;
        }

        return random_int($minCents, $maxCents);
    }
}
