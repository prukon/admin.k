<?php

namespace Database\Seeders;

use App\Models\TrainerProfile;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Случайные оклад и ставка за тренировку (до сотых) для всех trainer_profiles на dev-стенде.
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
            || ! Schema::hasColumn('trainer_profiles', 'default_base_salary')
            || ! Schema::hasColumn('trainer_profiles', 'default_rate_per_training')) {
            $this->command?->warn('DevTrainerSalaryDefaultsSeeder: колонки ЗП в trainer_profiles отсутствуют, пропуск.');

            return;
        }

        $updated = 0;

        TrainerProfile::query()
            ->orderBy('id')
            ->each(function (TrainerProfile $profile) use (&$updated): void {
                $profile->forceFill([
                    'default_base_salary' => self::randomMoney(
                        self::BASE_SALARY_MIN,
                        self::BASE_SALARY_MAX,
                    ),
                    'default_rate_per_training' => self::randomMoney(
                        self::RATE_PER_TRAINING_MIN,
                        self::RATE_PER_TRAINING_MAX,
                    ),
                ])->save();

                $updated++;
            });

        $this->command?->info("DevTrainerSalaryDefaultsSeeder: обновлено профилей тренеров: {$updated}.");
    }

    public static function randomMoney(float $min, float $max): string
    {
        $minCents = (int) round($min * 100);
        $maxCents = (int) round($max * 100);

        if ($maxCents < $minCents) {
            $maxCents = $minCents;
        }

        return number_format(random_int($minCents, $maxCents) / 100, 2, '.', '');
    }
}
