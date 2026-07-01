<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevTinkoffCommissionRulesSeeder extends Seeder
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
            $this->command?->warn('[DevTinkoffCommissionRulesSeeder] Ожидаются партнёры с id 1–3 — пропуск.');

            return;
        }

        $now = Carbon::now();

        $rows = [
            // Партнёр 1 (Исток): автовыплата по карте — для проверки UI (cron на dev не запущен).
            $this->ruleRow(1, 1, 'card', autoPayout: true, delayHours: 2, now: $now),
            $this->ruleRow(2, 1, 'sbp', now: $now),
            $this->ruleRow(3, 1, null, now: $now),

            $this->ruleRow(4, 2, 'card', now: $now),
            $this->ruleRow(5, 2, 'sbp', now: $now),
            $this->ruleRow(6, 2, null, now: $now),

            $this->ruleRow(7, 3, 'card', now: $now),
            $this->ruleRow(8, 3, 'sbp', now: $now),
            $this->ruleRow(9, 3, null, now: $now),
        ];

        foreach ($rows as $row) {
            $id = $row['id'];
            unset($row['id']);

            DB::table('tinkoff_commission_rules')->updateOrInsert(['id' => $id], $row);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleRow(
        int $id,
        int $partnerId,
        ?string $method,
        bool $autoPayout = false,
        int $delayHours = 0,
        Carbon|null $now = null,
    ): array {
        $now ??= Carbon::now();

        return [
            'id' => $id,
            'partner_id' => $partnerId,
            'method' => $method,
            'acquiring_percent' => 2.49,
            'acquiring_min_fixed' => 3.49,
            'payout_percent' => 0.10,
            'payout_min_fixed' => 0.00,
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0.00,
            'min_fixed' => 0.00,
            'is_enabled' => true,
            'auto_payout_enabled' => $autoPayout,
            'auto_payout_delay_hours' => $delayHours,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
