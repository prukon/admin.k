<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevDistrictsSeeder extends Seeder
{
    use GuardsDevSeedData;

    /** @var list<int> */
    private const DEV_PARTNER_IDS = [1, 2, 3];

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        if (DB::table('partners')->whereIn('id', self::DEV_PARTNER_IDS)->count() !== count(self::DEV_PARTNER_IDS)) {
            $this->command?->warn('[DevDistrictsSeeder] Ожидаются партнёры с id 1–3 — пропуск.');

            return;
        }

        $now = Carbon::now();

        $rows = [
            $this->row(1, 1, 'Центральный', 10, $now),
            $this->row(2, 1, 'Северный', 20, $now),
            $this->row(3, 1, 'Южный', 30, $now),
            $this->row(4, 2, 'Кудрово', 10, $now),
            $this->row(5, 2, 'Мурино', 20, $now),
            $this->row(6, 2, 'Девяткино', 30, $now),
            $this->row(7, 3, 'Московский', 10, $now),
            $this->row(8, 3, 'Невский', 20, $now),
            $this->row(9, 3, 'Приморский', 30, $now),
        ];

        foreach ($rows as $row) {
            $id = $row['id'];
            unset($row['id']);

            DB::table('districts')->updateOrInsert(['id' => $id], $row);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, int $partnerId, string $name, int $sortOrder, Carbon $now): array
    {
        return [
            'id' => $id,
            'partner_id' => $partnerId,
            'name' => $name,
            'is_enabled' => true,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
