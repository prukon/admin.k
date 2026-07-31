<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

/**
 * Ранее наполнял schedule_users. Журнал переведён на user_team_schedule_slots —
 * тестовые занятия создаются через календарь школы / раскладку абонемента.
 */
class DevScheduleJournalSeeder extends Seeder
{
    use GuardsDevSeedData;

    public function run(): void
    {
        $this->command?->warn(
            'DevScheduleJournalSeeder: пропуск — schedule_users удалён, журнал читает user_team_schedule_slots.'
        );
    }
}
