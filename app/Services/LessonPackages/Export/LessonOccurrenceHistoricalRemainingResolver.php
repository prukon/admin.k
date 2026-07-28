<?php

declare(strict_types=1);

namespace App\Services\LessonPackages\Export;

use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\UserLessonPackageConsumptionAdjuster;
use Illuminate\Support\Collection;

/**
 * Исторический остаток занятий на момент строки выгрузки «Занятия».
 *
 * Правила (согласовано):
 * - есть статус по строке → остаток после последнего события статуса по этой строке;
 * - нет статуса → остаток после всех более ранних событий по абонементу
 *   (дата занятия ASC, затем id события; в пределах дня — раньше слоты с меньшим time_start / slot_id);
 * - старт реконструкции — lessons_total / trial_lessons_total; учитываются все события абонемента, не только период отчёта.
 */
final class LessonOccurrenceHistoricalRemainingResolver
{
    /**
     * @param  Collection<int, UserTeamScheduleSlot>  $exportRows
     * @return array<string, int> ключ user|slot|date|ulpId → исторический остаток
     */
    public function resolveForExportRows(int $partnerId, Collection $exportRows): array
    {
        if ($exportRows->isEmpty()) {
            return [];
        }

        $result = [];

        $packageRows = $exportRows->filter(
            static fn (UserTeamScheduleSlot $row): bool => ! $row->is_trial_lesson && $row->user_lesson_package_id !== null
        );
        $trialRows = $exportRows->filter(
            static fn (UserTeamScheduleSlot $row): bool => (bool) $row->is_trial_lesson && $row->user_lesson_package_id === null
        );

        if ($packageRows->isNotEmpty()) {
            $result += $this->resolvePackageRows($partnerId, $packageRows);
        }

        if ($trialRows->isNotEmpty()) {
            $result += $this->resolveTrialRows($partnerId, $trialRows);
        }

        return $result;
    }

    /**
     * @param  Collection<int, UserTeamScheduleSlot>  $rows
     * @return array<string, int>
     */
    private function resolvePackageRows(int $partnerId, Collection $rows): array
    {
        $ulpIds = $rows
            ->map(static fn (UserTeamScheduleSlot $r): int => (int) $r->user_lesson_package_id)
            ->unique()
            ->values()
            ->all();

        $totals = UserLessonPackage::query()
            ->whereIn('id', $ulpIds)
            ->pluck('lessons_total', 'id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $events = UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', $partnerId)
            ->whereIn('user_lesson_package_id', $ulpIds)
            ->with(['lessonOccurrenceStatus:id,consumes_lesson'])
            ->orderBy('occurrence_date')
            ->orderBy('id')
            ->get([
                'id',
                'user_id',
                'team_schedule_slot_id',
                'occurrence_date',
                'user_lesson_package_id',
                'lesson_occurrence_status_id',
            ]);

        $slotIds = $events->pluck('team_schedule_slot_id')
            ->merge($rows->pluck('team_schedule_slot_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $slotMeta = $this->slotTimeMeta($slotIds);

        /** @var array<int, list<UserLessonOccurrenceStatusEvent>> $eventsByUlp */
        $eventsByUlp = [];
        foreach ($events as $event) {
            $ulpId = (int) $event->user_lesson_package_id;
            $eventsByUlp[$ulpId][] = $event;
        }

        $result = [];

        /** @var Collection<int, Collection<int, UserTeamScheduleSlot>> $rowsByUlp */
        $rowsByUlp = $rows->groupBy(static fn (UserTeamScheduleSlot $r): int => (int) $r->user_lesson_package_id);

        foreach ($rowsByUlp as $ulpId => $ulpRows) {
            $total = (int) ($totals[$ulpId] ?? 0);
            $ulpEvents = $eventsByUlp[(int) $ulpId] ?? [];

            [$remainingAfter, $hasEvents] = $this->replayPackageEvents($ulpEvents, $total);

            foreach ($ulpRows as $row) {
                $key = $this->occurrenceKey($row);
                if (isset($hasEvents[$key])) {
                    $result[$key] = $remainingAfter[$key];
                    continue;
                }

                $result[$key] = $this->remainingBeforeStatuslessOccurrence(
                    $ulpEvents,
                    $total,
                    $row,
                    $slotMeta
                );
            }
        }

        return $result;
    }

    /**
     * @param  list<UserLessonOccurrenceStatusEvent>  $events
     * @return array{0: array<string, int>, 1: array<string, true>}
     */
    private function replayPackageEvents(array $events, int $total): array
    {
        $remaining = $total;
        $prevConsumed = [];
        $remainingAfter = [];
        $hasEvents = [];

        foreach ($events as $event) {
            $key = $this->eventOccurrenceKey($event);
            $newConsumes = (bool) ($event->lessonOccurrenceStatus?->consumes_lesson ?? false);
            $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(
                $prevConsumed[$key] ?? null,
                $newConsumes
            );
            $remaining = $this->clampRemaining($remaining + $delta, $total);
            $prevConsumed[$key] = $newConsumes;
            $remainingAfter[$key] = $remaining;
            $hasEvents[$key] = true;
        }

        return [$remainingAfter, $hasEvents];
    }

    /**
     * @param  list<UserLessonOccurrenceStatusEvent>  $events
     * @param  array<int, array{time: string, sort: string}>  $slotMeta
     */
    private function remainingBeforeStatuslessOccurrence(
        array $events,
        int $total,
        UserTeamScheduleSlot $row,
        array $slotMeta,
    ): int {
        $rowDate = $this->dateYmd($row->starts_at);
        $rowSlotId = (int) $row->team_schedule_slot_id;
        $rowSort = $slotMeta[$rowSlotId]['sort'] ?? ('9999|'.sprintf('%010d', $rowSlotId));

        $remaining = $total;
        $prevConsumed = [];

        foreach ($events as $event) {
            $eventDate = $this->dateYmd($event->occurrence_date);
            if ($eventDate > $rowDate) {
                break;
            }

            $eventSlotId = (int) $event->team_schedule_slot_id;
            if ($eventDate === $rowDate) {
                $eventSort = $slotMeta[$eventSlotId]['sort'] ?? ('9999|'.sprintf('%010d', $eventSlotId));
                // События того же дня по слотам «не раньше» этой строки — ещё не учитываем.
                if ($eventSort >= $rowSort) {
                    continue;
                }
            }

            $key = $this->eventOccurrenceKey($event);
            $newConsumes = (bool) ($event->lessonOccurrenceStatus?->consumes_lesson ?? false);
            $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(
                $prevConsumed[$key] ?? null,
                $newConsumes
            );
            $remaining = $this->clampRemaining($remaining + $delta, $total);
            $prevConsumed[$key] = $newConsumes;
        }

        return $remaining;
    }

    /**
     * @param  Collection<int, UserTeamScheduleSlot>  $rows
     * @return array<string, int>
     */
    private function resolveTrialRows(int $partnerId, Collection $rows): array
    {
        $result = [];

        $userIds = $rows->pluck('user_id')->unique()->values()->all();
        $slotIds = $rows->pluck('team_schedule_slot_id')->unique()->values()->all();
        $dates = $rows->map(fn (UserTeamScheduleSlot $r): string => $this->dateYmd($r->starts_at))->unique()->values()->all();

        $events = UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', $partnerId)
            ->whereNull('user_lesson_package_id')
            ->whereIn('user_id', $userIds)
            ->whereIn('team_schedule_slot_id', $slotIds)
            ->whereIn('occurrence_date', $dates)
            ->with(['lessonOccurrenceStatus:id,consumes_lesson'])
            ->orderBy('occurrence_date')
            ->orderBy('id')
            ->get([
                'id',
                'user_id',
                'team_schedule_slot_id',
                'occurrence_date',
                'user_lesson_package_id',
                'lesson_occurrence_status_id',
            ]);

        /** @var array<string, list<UserLessonOccurrenceStatusEvent>> $eventsByKey */
        $eventsByKey = [];
        foreach ($events as $event) {
            $eventsByKey[$this->eventOccurrenceKey($event)][] = $event;
        }

        foreach ($rows as $row) {
            $key = $this->occurrenceKey($row);
            $total = (int) ($row->trial_lessons_total ?? 1);
            $trialEvents = $eventsByKey[$key] ?? [];

            if ($trialEvents === []) {
                // Нет статуса — остаток «до статуса» = полный пробный объём.
                $result[$key] = $total;
                continue;
            }

            $remaining = $total;
            $prevConsumed = null;
            foreach ($trialEvents as $event) {
                $newConsumes = (bool) ($event->lessonOccurrenceStatus?->consumes_lesson ?? false);
                $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta($prevConsumed, $newConsumes);
                $remaining = $this->clampRemaining($remaining + $delta, $total);
                $prevConsumed = $newConsumes;
            }
            $result[$key] = $remaining;
        }

        return $result;
    }

    /**
     * @param  list<int>  $slotIds
     * @return array<int, array{time: string, sort: string}>
     */
    private function slotTimeMeta(array $slotIds): array
    {
        if ($slotIds === []) {
            return [];
        }

        $rows = \App\Models\TeamScheduleSlot::query()
            ->whereIn('id', $slotIds)
            ->get(['id', 'time_start']);

        $meta = [];
        foreach ($rows as $slot) {
            $time = $this->normalizeTimeSort((string) $slot->time_start);
            $meta[(int) $slot->id] = [
                'time' => $time,
                'sort' => $time.'|'.sprintf('%010d', (int) $slot->id),
            ];
        }

        return $meta;
    }

    private function normalizeTimeSort(string $raw): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '99:99';
    }

    private function occurrenceKey(UserTeamScheduleSlot $row): string
    {
        $ulpId = $row->user_lesson_package_id !== null ? (int) $row->user_lesson_package_id : 0;

        return (int) $row->user_id.'|'
            .(int) $row->team_schedule_slot_id.'|'
            .$this->dateYmd($row->starts_at).'|'
            .$ulpId;
    }

    private function eventOccurrenceKey(UserLessonOccurrenceStatusEvent $event): string
    {
        $ulpId = $event->user_lesson_package_id !== null ? (int) $event->user_lesson_package_id : 0;

        return (int) $event->user_id.'|'
            .(int) $event->team_schedule_slot_id.'|'
            .$this->dateYmd($event->occurrence_date).'|'
            .$ulpId;
    }

    private function dateYmd(mixed $value): string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function clampRemaining(int $value, int $total): int
    {
        if ($value < 0) {
            return 0;
        }
        if ($value > $total) {
            return $total;
        }

        return $value;
    }
}
