<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Status;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Статусы посещаемости для журнала /schedule (таблица statuses), только dev-стенд.
 *
 * Системные (Посетил, Не был): одна запись на проект, partner_id = null, is_system = true.
 * Несистемные (Заморозка, Болезнь): по записи на каждого партнёра.
 */
class DevScheduleStatusesSeeder extends Seeder
{
    use GuardsDevSeedData;

    /**
     * @return array<int, array{name: string, color: string, icon: string, sort_order: int}>
     */
    public static function systemDefinitions(): array
    {
        return [
            [
                'name' => 'Посетил',
                'color' => '#198754',
                'icon' => 'fa-solid fa-circle-check',
                'sort_order' => 1,
            ],
            [
                'name' => 'Не был',
                'color' => '#dc3545',
                'icon' => 'fa-solid fa-circle-xmark',
                'sort_order' => 2,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, color: string, icon: string, sort_order: int}>
     */
    public static function partnerCustomDefinitions(): array
    {
        return [
            [
                'name' => 'Заморозка',
                'color' => '#0dcaf0',
                'icon' => 'fa-solid fa-snowflake',
                'sort_order' => 30,
            ],
            [
                'name' => 'Болезнь',
                'color' => '#f5deb3',
                'icon' => 'fa-solid fa-user-injured',
                'sort_order' => 40,
            ],
        ];
    }

    public static function ensureGlobalSystemStatuses(): void
    {
        if (! Schema::hasColumn('statuses', 'sort_order')) {
            return;
        }

        $now = now();
        $hasIsVisible = Schema::hasColumn('statuses', 'is_visible');

        foreach (self::systemDefinitions() as $row) {
            $status = Status::query()
                ->whereNull('partner_id')
                ->where('is_system', true)
                ->where('name', $row['name'])
                ->first();

            if ($status) {
                $update = [
                    'sort_order' => $row['sort_order'],
                    'color' => $row['color'],
                    'icon' => $row['icon'],
                    'updated_at' => $now,
                ];
                if ($hasIsVisible) {
                    $update['is_visible'] = true;
                }
                $status->update($update);

                continue;
            }

            $payload = [
                'partner_id' => null,
                'name' => $row['name'],
                'color' => $row['color'],
                'icon' => $row['icon'],
                'is_system' => true,
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasIsVisible) {
                $payload['is_visible'] = true;
            }

            Status::query()->create($payload);
        }
    }

    public static function ensurePartnerCustomStatuses(int $partnerId): void
    {
        if (! Schema::hasColumn('statuses', 'sort_order')) {
            return;
        }

        $now = now();
        $hasIsVisible = Schema::hasColumn('statuses', 'is_visible');

        foreach (self::partnerCustomDefinitions() as $row) {
            $status = Status::query()
                ->where('partner_id', $partnerId)
                ->where('is_system', false)
                ->where('name', $row['name'])
                ->first();

            if ($status) {
                $update = [
                    'sort_order' => $row['sort_order'],
                    'color' => $row['color'],
                    'icon' => $row['icon'],
                    'updated_at' => $now,
                ];
                if ($hasIsVisible) {
                    $update['is_visible'] = true;
                }
                $status->update($update);

                continue;
            }

            $payload = [
                'partner_id' => $partnerId,
                'name' => $row['name'],
                'color' => $row['color'],
                'icon' => $row['icon'],
                'is_system' => false,
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasIsVisible) {
                $payload['is_visible'] = true;
            }

            Status::query()->create($payload);
        }
    }

    /**
     * Убирает ошибочные копии системных статусов с partner_id (старая модель / старый сидер).
     */
    public static function removeLegacyPerPartnerSystemDuplicates(): void
    {
        $systemNames = array_column(self::systemDefinitions(), 'name');

        if ($systemNames === []) {
            return;
        }

        Status::query()
            ->where('is_system', true)
            ->whereNotNull('partner_id')
            ->whereIn('name', $systemNames)
            ->delete();
    }

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        self::ensureGlobalSystemStatuses();
        self::removeLegacyPerPartnerSystemDuplicates();

        Partner::query()
            ->orderBy('id')
            ->each(function (Partner $partner): void {
                self::ensurePartnerCustomStatuses((int) $partner->id);
            });
    }
}
