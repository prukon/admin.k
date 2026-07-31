<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use App\Services\TeamScheduleCalendarService;
use App\Services\UserLessonPackageCalendarPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Раскладка фиксированного абонемента из журнала /schedule в user_team_schedule_slots.
 * Переиспользует цепочку/конфликты календаря школы; время слотов — служебное для «дневного» UI.
 */
final class JournalFixedAbonementPlacementService
{
    public function __construct(
        private readonly JournalTeamScheduleSlotEnsureService $slotEnsure,
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly UserLessonPackageCalendarPeriodService $calendarPeriodService,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
    ) {
    }

    /**
     * @param list<int> $weekdays ISO 1..7
     * @return array{linked_count: int, starts_at: string, ends_at: string, preview_dates?: list<string>}
     */
    public function place(
        int $partnerId,
        User $user,
        UserLessonPackage $ulp,
        Team $team,
        CarbonImmutable $startDate,
        array $weekdays,
        ?int $createdByUserId,
        bool $previewOnly = false,
    ): array {
        $weekdays = $this->normalizeWeekdays($weekdays);
        if ($weekdays === []) {
            throw new InvalidArgumentException('Выберите хотя бы один день недели.');
        }

        $this->assertUserAndTeam($partnerId, $user, $team);
        $this->assertFixedAssignable($partnerId, $user, $ulp);

        $anchorDate = $this->firstMatchingDateOnOrAfter($startDate, $weekdays);
        if ($anchorDate === null) {
            throw new InvalidArgumentException('Не удалось найти дату начала по выбранным дням недели.');
        }

        /** @var array<int, TeamScheduleSlot> $slotsByWeekday */
        $slotsByWeekday = [];
        foreach ($weekdays as $weekday) {
            $slotsByWeekday[$weekday] = $this->slotEnsure->resolveOrCreateEarliestForWeekday(
                $partnerId,
                (int) $team->id,
                $weekday
            );
        }

        $anchorWeekday = (int) $anchorDate->format('N');
        $anchorSlot = $slotsByWeekday[$anchorWeekday] ?? null;
        if ($anchorSlot === null) {
            throw new InvalidArgumentException('Для даты начала нет выбранного дня недели.');
        }

        $patterns = $this->patternsFromSlots($slotsByWeekday);
        if ($patterns->isEmpty()) {
            throw new InvalidArgumentException('Не удалось построить шаблон дней недели.');
        }

        if ($previewOnly) {
            return $this->previewChain(
                $partnerId,
                $ulp,
                $anchorDate,
                $anchorSlot,
                $patterns,
            );
        }

        $scheduledStatus = $this->scheduledStatusForPartner($partnerId);
        $linkedCount = 0;
        $periodStart = '';
        $periodEnd = '';

        DB::transaction(function () use (
            $partnerId,
            $user,
            $ulp,
            $anchorDate,
            $anchorSlot,
            $patterns,
            $createdByUserId,
            $scheduledStatus,
            &$linkedCount,
            &$periodStart,
            &$periodEnd,
        ): void {
            $this->calendarPeriodService->applyFirstCalendarAnchor($ulp, $anchorDate);
            $ulp->refresh();

            $periodEndDate = CarbonImmutable::parse($ulp->ends_at->format('Y-m-d'))->startOfDay();
            $periodStart = $ulp->starts_at->format('Y-m-d');
            $periodEnd = $periodEndDate->toDateString();

            $scheduledExisting = UserTeamScheduleSlot::query()
                ->where('user_lesson_package_id', (int) $ulp->id)
                ->count();
            $lessonsNeeded = (int) $ulp->lessons_total - $scheduledExisting;
            if ($lessonsNeeded < 1) {
                throw new InvalidArgumentException('Нет свободных занятий для записи по этому абонементу.');
            }

            $this->calendarService->assertEveryFixedPatternOccurrenceResolvableInPeriod(
                $anchorSlot,
                $anchorDate,
                $periodEndDate,
                $patterns,
                $partnerId,
                (int) $anchorSlot->team_id,
                null
            );

            $chain = $this->calendarService->buildFixedOccurrenceChain(
                $partnerId,
                $anchorDate,
                $anchorSlot,
                $patterns,
                $lessonsNeeded,
                $periodEndDate,
                null
            );

            $linkedCount = count($chain);

            $this->calendarService->assertFixedChainHasNoTimeOverlapWithExistingUserLessons(
                (int) $user->id,
                $partnerId,
                $chain
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
                    'ends_at' => $periodEndDate->toDateString(),
                    'created_by' => $createdByUserId,
                    'is_trial_lesson' => false,
                ]);

                $this->occurrenceStatusService->apply(
                    $partnerId,
                    (int) $user->id,
                    (int) $slot->id,
                    $date->toDateString(),
                    (int) $ulp->id,
                    $scheduledStatus,
                    $createdByUserId,
                    null,
                    null,
                );
            }
        });

        return [
            'linked_count' => $linkedCount,
            'starts_at' => $periodStart,
            'ends_at' => $periodEnd,
        ];
    }

    /**
     * @param Collection<int, object{weekday: int, time_start: string, time_end: string}> $patterns
     * @return array{linked_count: int, starts_at: string, ends_at: string, preview_dates: list<string>}
     */
    private function previewChain(
        int $partnerId,
        UserLessonPackage $ulp,
        CarbonImmutable $anchorDate,
        TeamScheduleSlot $anchorSlot,
        Collection $patterns,
    ): array {
        $ulp->loadMissing('lessonPackage:id,duration_days');
        $days = (int) ($ulp->lessonPackage?->duration_days ?? 0);
        if ($days < 1) {
            throw new InvalidArgumentException('У шаблона абонемента не задан срок действия (duration_days).');
        }

        $periodEnd = $anchorDate->addDays($days)->startOfDay();
        $lessonsNeeded = (int) $ulp->lessons_total;
        if ($lessonsNeeded < 1) {
            throw new InvalidArgumentException('У абонемента не задан объём занятий.');
        }

        $this->calendarService->assertEveryFixedPatternOccurrenceResolvableInPeriod(
            $anchorSlot,
            $anchorDate,
            $periodEnd,
            $patterns,
            $partnerId,
            (int) $anchorSlot->team_id,
            null
        );

        $chain = $this->calendarService->buildFixedOccurrenceChain(
            $partnerId,
            $anchorDate,
            $anchorSlot,
            $patterns,
            $lessonsNeeded,
            $periodEnd,
            null
        );

        return [
            'linked_count' => count($chain),
            'starts_at' => $anchorDate->toDateString(),
            'ends_at' => $periodEnd->toDateString(),
            'preview_dates' => array_map(
                static fn (array $item): string => $item['date']->toDateString(),
                $chain
            ),
        ];
    }

    private function assertUserAndTeam(int $partnerId, User $user, Team $team): void
    {
        if ((int) $user->partner_id !== $partnerId) {
            throw new InvalidArgumentException('Ученик не найден.');
        }
        if ((int) $team->partner_id !== $partnerId || $team->deleted_at !== null) {
            throw new InvalidArgumentException('Группа не найдена.');
        }

        $belongs = $user->teams()
            ->where('teams.id', (int) $team->id)
            ->where('teams.partner_id', $partnerId)
            ->exists();

        if (! $belongs) {
            throw new InvalidArgumentException('Ученик не состоит в выбранной группе.');
        }
    }

    private function assertFixedAssignable(int $partnerId, User $user, UserLessonPackage $ulp): void
    {
        if ((int) $ulp->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Назначение не принадлежит ученику.');
        }

        /** @var LessonPackage|null $package */
        $package = $ulp->lessonPackage;
        if (! $package || (int) $package->partner_id !== $partnerId || (string) $package->schedule_type !== 'fixed') {
            throw new InvalidArgumentException('Доступна раскладка только для фиксированного абонемента.');
        }

        if ($ulp->starts_at !== null || $ulp->ends_at !== null) {
            throw new InvalidArgumentException('У этого назначения уже задан период действия.');
        }

        if (UserTeamScheduleSlot::query()->where('user_lesson_package_id', (int) $ulp->id)->exists()) {
            throw new InvalidArgumentException('У назначения уже есть записи в расписании.');
        }

        if ((int) $ulp->lessons_total < 1) {
            throw new InvalidArgumentException('У абонемента не задан объём занятий.');
        }
    }

    /**
     * @param list<int> $weekdays
     * @return list<int>
     */
    private function normalizeWeekdays(array $weekdays): array
    {
        $normalized = [];
        foreach ($weekdays as $day) {
            $day = (int) $day;
            if ($day >= 1 && $day <= 7) {
                $normalized[$day] = $day;
            }
        }

        $list = array_values($normalized);
        sort($list);

        return $list;
    }

    /**
     * @param list<int> $weekdays
     */
    private function firstMatchingDateOnOrAfter(CarbonImmutable $startDate, array $weekdays): ?CarbonImmutable
    {
        $day = $startDate->startOfDay();
        for ($i = 0; $i < 14; $i++) {
            if (in_array((int) $day->format('N'), $weekdays, true)) {
                return $day;
            }
            $day = $day->addDay();
        }

        return null;
    }

    /**
     * @param array<int, TeamScheduleSlot> $slotsByWeekday
     * @return Collection<int, object{weekday: int, time_start: string, time_end: string}>
     */
    private function patternsFromSlots(array $slotsByWeekday): Collection
    {
        $patterns = collect();
        foreach ($slotsByWeekday as $weekday => $slot) {
            $patterns->push((object) [
                'weekday' => (int) $weekday,
                'time_start' => substr((string) $slot->time_start, 0, 5),
                'time_end' => substr((string) $slot->time_end, 0, 5),
            ]);
        }

        return $patterns->values();
    }

    private function scheduledStatusForPartner(int $partnerId): LessonOccurrenceStatus
    {
        $status = LessonOccurrenceStatus::query()
            ->forPartner($partnerId)
            ->where('code', LessonOccurrenceStatus::CODE_SCHEDULED)
            ->active()
            ->first();

        if (! $status) {
            throw new RuntimeException('Системный статус «Запись» не найден для партнёра.');
        }

        return $status;
    }
}
