<?php

namespace Database\Seeders;

use App\Models\LessonOccurrenceStatus;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Дефолтные системные статусы занятий + идемпотентное создание/синхронизация по партнёрам.
 * Статические методы вызываются из приложения при создании партнёра и из админки — не только из artisan db:seed.
 */
class LessonOccurrenceStatusesSeeder extends Seeder
{
    /**
     * @return array<int, array{code:string,title:string,sort_order:int,color:string,icon:?string,consumes_lesson:bool}>
     */
    public static function systemDefinitions(): array
    {
        return [
            [
                'code' => 'scheduled',
                'title' => 'Запись',
                'sort_order' => 10,
                'color' => '#0d6efd',
                'icon' => 'fa-solid fa-calendar-plus',
                'consumes_lesson' => false,
            ],
            [
                'code' => 'attended',
                'title' => 'Посетил',
                'sort_order' => 20,
                'color' => '#198754',
                'icon' => 'fa-solid fa-circle-check',
                'consumes_lesson' => true,
            ],
            [
                'code' => 'not_attended',
                'title' => 'Не посетил',
                'sort_order' => 30,
                'color' => '#dc3545',
                'icon' => 'fa-solid fa-circle-xmark',
                'consumes_lesson' => true,
            ],
            [
                'code' => 'cancelled',                                                                                                                                                                                              
                'title' => 'Отмена',
                'sort_order' => 40,
                'color' => '#6c757d',
                'icon' => 'fa-solid fa-ban',
                'consumes_lesson' => false,
            ],
            [
                'code' => 'frozen',
                'title' => 'Заморозка',
                'sort_order' => 50,
                'color' => '#0dcaf0',
                'icon' => 'fa-solid fa-snowflake',
                'consumes_lesson' => false,
            ],
        ];
    }

    public static function ensureForPartner(int $partnerId): void
    {
        $now = now();
        $hasConsumesLesson = Schema::hasTable('lesson_occurrence_statuses')
            && Schema::hasColumn('lesson_occurrence_statuses', 'consumes_lesson');

        DB::transaction(function () use ($partnerId, $now, $hasConsumesLesson) {
            foreach (self::systemDefinitions() as $row) {
                $exists = LessonOccurrenceStatus::query()
                    ->where('partner_id', $partnerId)
                    ->where('code', $row['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = [
                    'partner_id' => $partnerId,
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'color' => $row['color'],
                    'icon' => $row['icon'],
                    'sort_order' => $row['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($hasConsumesLesson) {
                    $payload['consumes_lesson'] = $row['consumes_lesson'];
                }

                LessonOccurrenceStatus::query()->create($payload);
            }
        });
    }

    /**
     * Выставляет consumes_lesson по канону для строк с известными системными кодами партнёра.
     */
    public static function syncConsumesLessonFlagsFromDefinitionsForPartner(int $partnerId): void
    {
        if (! Schema::hasTable('lesson_occurrence_statuses')
            || ! Schema::hasColumn('lesson_occurrence_statuses', 'consumes_lesson')) {
            return;
        }

        DB::transaction(function () use ($partnerId) {
            foreach (self::systemDefinitions() as $row) {
                LessonOccurrenceStatus::query()
                    ->where('partner_id', $partnerId)
                    ->where('code', $row['code'])
                    ->update([
                        'consumes_lesson' => $row['consumes_lesson'],
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function run(): void
    {
        Partner::query()
            ->orderBy('id')
            ->each(function (Partner $partner): void {
                $id = (int) $partner->id;
                self::ensureForPartner($id);
                self::syncConsumesLessonFlagsFromDefinitionsForPartner($id);
            });
    }
}
