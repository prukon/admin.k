<?php

namespace Database\Seeders\Support;

use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamScheduleCalendarService;
use App\Services\UserLessonPackageCalendarPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Привязка назначений к календарю школы (логика как в LessonPackageSchoolCalendarAssignmentController).
 */
final class DevSchoolCalendarBinder
{
    public function __construct(
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly UserLessonPackageCalendarPeriodService $calendarPeriodService,
    ) {}

    public function bindFlexible(
        UserLessonPackage $ulp,
        TeamScheduleSlot $slot,
        CarbonImmutable $occurrence,
        ?int $createdBy = null,
    ): void {
        $partnerId = (int) $ulp->user?->partner_id;
        if ($partnerId < 1 || (int) $slot->partner_id !== $partnerId) {
            throw new InvalidArgumentException('Слот и назначение должны относиться к одному партнёру.');
        }

        if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
            throw new InvalidArgumentException('Дата не соответствует дню недели слота.');
        }

        DB::transaction(function () use ($ulp, $partnerId, $slot, $occurrence, $createdBy) {
            $this->calendarPeriodService->applyFirstCalendarAnchor($ulp, $occurrence);
            $ulp->refresh();

            UserTeamScheduleSlot::query()->create([
                'partner_id' => $partnerId,
                'user_id' => (int) $ulp->user_id,
                'user_lesson_package_id' => (int) $ulp->id,
                'team_schedule_slot_id' => (int) $slot->id,
                'starts_at' => $occurrence->toDateString(),
                'ends_at' => $ulp->ends_at?->format('Y-m-d') ?? $occurrence->toDateString(),
                'created_by' => $createdBy,
            ]);
        });
    }

    public function bindSingleLesson(
        UserLessonPackage $ulp,
        TeamScheduleSlot $slot,
        CarbonImmutable $occurrence,
        ?int $createdBy = null,
    ): void {
        $this->bindFlexible($ulp, $slot, $occurrence, $createdBy);
    }

    /**
     * @param  Collection<int, object{weekday: int, time_start: string, time_end: string}>  $patterns
     */
    public function bindFixed(
        User $user,
        UserLessonPackage $ulp,
        TeamScheduleSlot $anchorSlot,
        CarbonImmutable $anchorDate,
        Collection $patterns,
        ?int $createdBy = null,
    ): void {
        $partnerId = (int) $user->partner_id;
        if ($partnerId < 1 || (int) $anchorSlot->partner_id !== $partnerId) {
            throw new InvalidArgumentException('Слот и ученик должны относиться к одному партнёру.');
        }

        if ($ulp->starts_at !== null || $ulp->ends_at !== null) {
            throw new InvalidArgumentException('Фиксированная привязка доступна только без заданного периода.');
        }

        if (UserTeamScheduleSlot::query()->where('user_lesson_package_id', (int) $ulp->id)->exists()) {
            throw new InvalidArgumentException('У назначения уже есть записи в календаре.');
        }

        $locationFilter = $anchorSlot->location_id !== null ? (int) $anchorSlot->location_id : null;

        DB::transaction(function () use (
            $partnerId,
            $user,
            $ulp,
            $anchorDate,
            $anchorSlot,
            $locationFilter,
            $patterns,
            $createdBy,
        ) {
            $this->calendarPeriodService->applyFirstCalendarAnchor($ulp, $anchorDate);
            $ulp->refresh();

            $periodEnd = CarbonImmutable::parse($ulp->ends_at->format('Y-m-d'))->startOfDay();

            $lessonsNeeded = (int) $ulp->lessons_total;
            if ($lessonsNeeded < 1) {
                throw new InvalidArgumentException('Нет занятий для записи в календарь.');
            }

            $this->calendarService->assertEveryFixedPatternOccurrenceResolvableInPeriod(
                $anchorSlot,
                $anchorDate,
                $periodEnd,
                $patterns,
                $partnerId,
                (int) $anchorSlot->team_id,
                $locationFilter,
            );

            $chain = $this->calendarService->buildFixedOccurrenceChain(
                $partnerId,
                $anchorDate,
                $anchorSlot,
                $patterns,
                $lessonsNeeded,
                $periodEnd,
                $locationFilter,
            );

            $this->calendarService->assertFixedChainHasNoTimeOverlapWithExistingUserLessons(
                (int) $user->id,
                $partnerId,
                $chain,
            );

            foreach ($chain as $item) {
                /** @var CarbonImmutable $date */
                $date = $item['date'];
                /** @var TeamScheduleSlot $slot */
                $slot = $item['slot'];

                UserTeamScheduleSlot::query()->create([
                    'partner_id' => $partnerId,
                    'user_id' => (int) $user->id,
                    'user_lesson_package_id' => (int) $ulp->id,
                    'team_schedule_slot_id' => (int) $slot->id,
                    'starts_at' => $date->toDateString(),
                    'ends_at' => $periodEnd->toDateString(),
                    'created_by' => $createdBy,
                ]);
            }
        });
    }

    public static function patternFromSlot(TeamScheduleSlot $slot): object
    {
        return (object) [
            'weekday' => (int) $slot->weekday,
            'time_start' => self::normalizeTime((string) $slot->time_start),
            'time_end' => self::normalizeTime((string) $slot->time_end),
        ];
    }

    public static function occurrenceDateForSlot(TeamScheduleSlot $slot, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $from ??= CarbonImmutable::now()->startOfDay();
        $cursor = $from->startOfWeek(CarbonImmutable::MONDAY);

        for ($i = 0; $i < 14; $i++) {
            if ((int) $cursor->format('N') === (int) $slot->weekday && $cursor->gte($from)) {
                return $cursor;
            }
            $cursor = $cursor->addDay();
        }

        return $from->addDays(7);
    }

    private static function normalizeTime(string $time): string
    {
        if (strlen($time) >= 5) {
            return substr($time, 0, 5);
        }

        return $time;
    }
}
