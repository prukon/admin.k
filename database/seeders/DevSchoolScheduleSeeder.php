<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Partner;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DevSchoolScheduleSeeder extends Seeder
{
    use GuardsDevSeedData;

    /** @var list<array{weekday: int, time_start: string, time_end: string}> */
    private const SLOT_BLUEPRINTS = [
        ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
        ['weekday' => 2, 'time_start' => '12:00', 'time_end' => '13:00'],
        ['weekday' => 3, 'time_start' => '15:00', 'time_end' => '16:00'],
        ['weekday' => 4, 'time_start' => '17:00', 'time_end' => '18:00'],
        ['weekday' => 5, 'time_start' => '11:00', 'time_end' => '12:00'],
        ['weekday' => 6, 'time_start' => '14:00', 'time_end' => '15:00'],
    ];

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $partnerIds = Partner::query()->pluck('id')->all();

        foreach ($partnerIds as $partnerId) {
            LessonOccurrenceStatusesSeeder::ensureForPartner((int) $partnerId);

            $this->seedSlotsForPartner((int) $partnerId);
            $this->seedStandaloneTrialOccurrences((int) $partnerId);
        }
    }

    private function seedSlotsForPartner(int $partnerId): void
    {
        $teams = Team::query()
            ->where('partner_id', $partnerId)
            ->get();

        if ($teams->isEmpty()) {
            return;
        }

        $locationIds = Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->pluck('id')
            ->all();

        foreach ($teams as $team) {
            $blueprints = collect(self::SLOT_BLUEPRINTS)
                ->shuffle()
                ->take(random_int(2, 4));

            foreach ($blueprints as $blueprint) {
                TeamScheduleSlot::query()->create([
                    'partner_id' => $partnerId,
                    'team_id' => $team->id,
                    'location_id' => $locationIds !== []
                        ? $locationIds[array_rand($locationIds)]
                        : null,
                    'weekday' => $blueprint['weekday'],
                    'time_start' => $blueprint['time_start'],
                    'time_end' => $blueprint['time_end'],
                    'date_start' => now()->subMonths(2)->toDateString(),
                    'date_end' => '9999-12-31',
                    'is_enabled' => true,
                ]);
            }
        }
    }

    /**
     * Несколько пробных записей в календаре без абонемента (для наполнения сетки).
     */
    private function seedStandaloneTrialOccurrences(int $partnerId): void
    {
        $slots = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        if ($slots->isEmpty()) {
            return;
        }

        $students = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $createdBy = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'superadmin']))
            ->value('id');

        foreach ($slots as $index => $slot) {
            $student = $students->get($index % $students->count());
            if (! $student) {
                continue;
            }

            if (UserTeamScheduleSlot::query()
                ->where('user_id', $student->id)
                ->where('is_trial_lesson', true)
                ->whereNull('user_lesson_package_id')
                ->exists()) {
                continue;
            }

            $occurrence = CarbonImmutable::now()
                ->startOfWeek(CarbonImmutable::MONDAY)
                ->addDays(max(0, (int) $slot->weekday - 1));

            if ($occurrence->isPast()) {
                $occurrence = $occurrence->addWeek();
            }

            if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
                continue;
            }

            $exists = UserTeamScheduleSlot::query()
                ->where('user_id', $student->id)
                ->where('team_schedule_slot_id', $slot->id)
                ->whereDate('starts_at', $occurrence->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                UserTeamScheduleSlot::query()->create([
                    'partner_id' => $partnerId,
                    'user_id' => (int) $student->id,
                    'user_lesson_package_id' => null,
                    'team_schedule_slot_id' => (int) $slot->id,
                    'starts_at' => $occurrence->toDateString(),
                    'ends_at' => $occurrence->toDateString(),
                    'is_trial_lesson' => true,
                    'trial_lessons_remaining' => 1,
                    'trial_lessons_total' => 1,
                    'created_by' => $createdBy,
                ]);

                $student->forceFill(['has_used_school_schedule_trial' => true])->save();
            } catch (\Throwable $e) {
                Log::debug('DevSchoolScheduleSeeder: trial skip', [
                    'partner_id' => $partnerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
