<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LessonPackageTimeSlot;
use App\Models\TeamScheduleSlot;
use App\Models\TeamScheduleSlotException;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Разворачивает правила team_schedule_slots в конкретные «окна» на календарной неделе.
 */
final class TeamScheduleCalendarService
{
    /**
     * @return list<array{
     *     id: int,
     *     date: string,
     *     weekday: int,
     *     time_start: string,
     *     time_end: string,
     *     team_id: int,
     *     team_title: string,
     *     location_id: int|null,
     *     location_name: string|null,
     *     date_start: string,
     *     date_end: string,
     *     registrations: list<array{user_label: string, line: string}>,
     * }>
     */
    public function occurrencesForWeek(int $partnerId, CarbonImmutable $weekMonday, ?int $locationId): array
    {
        $weekMonday = $weekMonday->startOfDay();
        $weekSunday = $weekMonday->addDays(6);

        $rangeStart = $weekMonday->toDateString();
        $rangeEnd = $weekSunday->toDateString();

        $query = TeamScheduleSlot::query()
            ->with(['team:id,title', 'location:id,name'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereDate('date_start', '<=', $rangeEnd)
            ->whereDate('date_end', '>=', $rangeStart);

        if ($locationId !== null && $locationId > 0) {
            $query->where('location_id', $locationId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, TeamScheduleSlot> $slots */
        $slots = $query
            ->orderBy('weekday')
            ->orderBy('time_start')
            ->orderBy('id')
            ->get();

        $skipKeys = [];
        if ($slots->isNotEmpty()) {
            $slotIds = $slots->pluck('id')->map(fn ($id) => (int) $id)->all();
            $rows = TeamScheduleSlotException::query()
                ->whereIn('team_schedule_slot_id', $slotIds)
                ->whereDate('occurrence_date', '>=', $rangeStart)
                ->whereDate('occurrence_date', '<=', $rangeEnd)
                ->get(['team_schedule_slot_id', 'occurrence_date']);

            foreach ($rows as $row) {
                $d = $row->occurrence_date instanceof \Carbon\CarbonInterface
                    ? $row->occurrence_date->format('Y-m-d')
                    : (string) $row->occurrence_date;
                $skipKeys[(int) $row->team_schedule_slot_id.'|'.$d] = true;
            }
        }

        $out = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $weekMonday->addDays($i);
            $dow = (int) $day->format('N');
            $ds = $day->toDateString();

            foreach ($slots as $slot) {
                if ((int) $slot->weekday !== $dow) {
                    continue;
                }
                if ($day->lt(Carbon::parse($slot->date_start)->startOfDay())) {
                    continue;
                }
                if ($day->gt(Carbon::parse($slot->date_end)->endOfDay())) {
                    continue;
                }

                if (! empty($skipKeys[(int) $slot->id.'|'.$ds])) {
                    continue;
                }

                $out[] = [
                    'id' => (int) $slot->id,
                    'date' => $ds,
                    'weekday' => $dow,
                    'time_start' => substr((string) $slot->time_start, 0, 5),
                    'time_end' => substr((string) $slot->time_end, 0, 5),
                    'team_id' => (int) $slot->team_id,
                    'team_title' => (string) ($slot->team?->title ?? ''),
                    'location_id' => $slot->location_id !== null ? (int) $slot->location_id : null,
                    'location_name' => $slot->location?->name,
                    'date_start' => $slot->date_start?->format('Y-m-d') ?? '',
                    'date_end' => $slot->date_end?->format('Y-m-d') ?? '',
                ];
            }
        }

        usort($out, static function (array $a, array $b): int {
            return [$a['date'], $a['time_start'], $a['id']] <=> [$b['date'], $b['time_start'], $b['id']];
        });

        $registrationGroups = $this->registrationGroupsForOccurrences($partnerId, $out);
        foreach ($out as $i => $item) {
            $key = $item['id'].'|'.$item['date'];
            $out[$i]['registrations'] = $registrationGroups[$key] ?? [];
        }

        return $out;
    }

    /**
     * @param list<array{id: int, date: string}> $occurrences
     * @return array<string, list<array{user_label: string, line: string}>>
     */
    private function registrationGroupsForOccurrences(int $partnerId, array $occurrences): array
    {
        if ($occurrences === []) {
            return [];
        }

        $slotIds = array_values(array_unique(array_map(static fn (array $o) => (int) $o['id'], $occurrences)));
        $dates = array_column($occurrences, 'date');
        $rangeStart = min($dates);
        $rangeEnd = max($dates);

        $rows = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->whereIn('team_schedule_slot_id', $slotIds)
            ->whereDate('starts_at', '>=', $rangeStart)
            ->whereDate('starts_at', '<=', $rangeEnd)
            ->with([
                'user:id,name,lastname',
                'userLessonPackage.lessonPackage:id,name,schedule_type',
            ])
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $dateStr = $row->starts_at instanceof \Carbon\CarbonInterface
                ? $row->starts_at->format('Y-m-d')
                : (string) $row->starts_at;
            $key = (int) $row->team_schedule_slot_id.'|'.$dateStr;
            $grouped[$key] ??= [];
            $grouped[$key][] = $this->formatSlotRegistrationLine($row);
        }

        foreach ($grouped as $key => $list) {
            usort($list, static fn (array $a, array $b) => strcmp($a['user_label'], $b['user_label']));
            $grouped[$key] = array_values($list);
        }

        return $grouped;
    }

    /**
     * @return array{user_label: string, line: string}
     */
    private function formatSlotRegistrationLine(UserTeamScheduleSlot $row): array
    {
        $user = $row->user;
        $userLabel = $user
            ? trim(($user->lastname ?? '').' '.($user->name ?? ''))
            : '';
        if ($userLabel === '') {
            $userLabel = 'Ученик #'.(int) $row->user_id;
        }

        $ulp = $row->userLessonPackage;
        $lp = $ulp?->lessonPackage;

        if ($lp !== null) {
            $kind = match ((string) $lp->schedule_type) {
                'flexible' => 'гибкий абонемент',
                'fixed' => 'фиксированный абонемент',
                'no_schedule' => 'абонемент без расписания',
                default => (string) ($lp->name !== '' ? $lp->name : 'абонемент'),
            };
        } elseif ($ulp !== null) {
            $kind = 'абонемент №'.(int) $ulp->id;
        } else {
            $kind = 'запись без привязки к абонементу';
        }

        return [
            'user_label' => $userLabel,
            'line' => $userLabel.', '.$kind,
        ];
    }

    public function slotActiveOnDate(TeamScheduleSlot $slot, CarbonImmutable $date): bool
    {
        $d = $date->startOfDay();
        $start = Carbon::parse($slot->date_start)->startOfDay();
        $end = Carbon::parse($slot->date_end)->endOfDay();

        return !$d->lt($start) && !$d->gt($end) && (bool) $slot->is_enabled;
    }

    /**
     * Точечное исключение даты для слота (не показывается в календаре и не участвует в цепочке фикс).
     */
    public function isOccurrenceSkipped(int $teamScheduleSlotId, CarbonImmutable $day): bool
    {
        return TeamScheduleSlotException::query()
            ->where('team_schedule_slot_id', $teamScheduleSlotId)
            ->whereDate('occurrence_date', $day->toDateString())
            ->exists();
    }

    /**
     * Цепочка из K занятий для фиксированного абонемента: от якоря по времени вперёд.
     *
     * @param Collection<int, LessonPackageTimeSlot> $patterns
     * @return list<array{date: CarbonImmutable, slot: TeamScheduleSlot}>
     */
    public function buildFixedOccurrenceChain(
        int $partnerId,
        CarbonImmutable $anchorDate,
        TeamScheduleSlot $anchorSlot,
        Collection $patterns,
        int $lessonsNeeded,
        CarbonImmutable $periodEnd,
        ?int $locationIdFilter
    ): array {
        $anchorDate = $anchorDate->startOfDay();

        if ($lessonsNeeded < 1) {
            throw new \InvalidArgumentException('Число занятий в абонементе должно быть не меньше 1.');
        }

        if ($patterns->isEmpty()) {
            throw new \InvalidArgumentException('У абонемента не заданы слоты расписания.');
        }

        $anchorDow = (int) $anchorDate->format('N');
        if ((int) $anchorSlot->weekday !== $anchorDow) {
            throw new \InvalidArgumentException('Якорный слот не соответствует выбранному дню календаря.');
        }

        if (!$this->slotActiveOnDate($anchorSlot, $anchorDate)) {
            throw new \InvalidArgumentException('Слот недействителен на выбранную дату.');
        }

        if (!$this->patternMatchesSlot($patterns, $anchorSlot, $anchorDow)) {
            throw new \InvalidArgumentException('Выбранный слот не совпадает с расписанием абонемента (день и время).');
        }

        if ($locationIdFilter !== null && $locationIdFilter > 0) {
            if ((int) ($anchorSlot->location_id ?? 0) !== $locationIdFilter) {
                throw new \InvalidArgumentException('Слот не относится к выбранной локации.');
            }
        }

        $candidates = [];
        $end = $periodEnd->startOfDay();

        for ($d = $anchorDate; $d->lte($end); $d = $d->addDay()) {
            foreach ($patterns as $pattern) {
                if ((int) $pattern->weekday !== (int) $d->format('N')) {
                    continue;
                }

                $slot = $this->findMatchingSlot(
                    $partnerId,
                    $d,
                    $pattern,
                    $locationIdFilter
                );

                if ($slot === null) {
                    continue;
                }

                if ($this->isOccurrenceSkipped((int) $slot->id, $d)) {
                    continue;
                }

                $candidates[] = [
                    'date' => $d,
                    'slot' => $slot,
                ];
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            /** @var CarbonImmutable $da */
            $da = $a['date'];
            /** @var CarbonImmutable $db */
            $db = $b['date'];
            $ta = substr((string) $a['slot']->time_start, 0, 5);
            $tb = substr((string) $b['slot']->time_start, 0, 5);

            return [$da->toDateString(), $ta, $a['slot']->id] <=> [$db->toDateString(), $tb, $b['slot']->id];
        });

        $anchorKey = $this->chainKey($anchorDate, $anchorSlot);
        $anchorIndex = null;

        foreach ($candidates as $i => $item) {
            /** @var CarbonImmutable $date */
            $date = $item['date'];
            /** @var TeamScheduleSlot $slot */
            $slot = $item['slot'];
            if ($this->chainKey($date, $slot) === $anchorKey) {
                $anchorIndex = $i;
                break;
            }
        }

        if ($anchorIndex === null) {
            throw new \RuntimeException('Не удалось найти якорное занятие в построенном расписании.');
        }

        $chain = array_slice($candidates, $anchorIndex, $lessonsNeeded);

        if (count($chain) < $lessonsNeeded) {
            throw new \RuntimeException(
                'В периоде абонемента не хватает занятий по расписанию школы. Требуется: '.$lessonsNeeded.', найдено с выбранной даты: '.count($chain).'.'
            );
        }

        return $chain;
    }

    /**
     * @param Collection<int, LessonPackageTimeSlot> $patterns
     */
    private function patternMatchesSlot(Collection $patterns, TeamScheduleSlot $slot, int $dow): bool
    {
        foreach ($patterns as $pattern) {
            if ((int) $pattern->weekday !== $dow) {
                continue;
            }
            if ($this->timesEqual($pattern->time_start, $slot->time_start)
                && $this->timesEqual($pattern->time_end, $slot->time_end)) {
                return true;
            }
        }

        return false;
    }

    private function findMatchingSlot(
        int $partnerId,
        CarbonImmutable $day,
        LessonPackageTimeSlot $pattern,
        ?int $locationIdFilter
    ): ?TeamScheduleSlot {
        $dow = (int) $day->format('N');
        if ((int) $pattern->weekday !== $dow) {
            return null;
        }

        $q = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->where('weekday', $dow)
            ->whereDate('date_start', '<=', $day->toDateString())
            ->whereDate('date_end', '>=', $day->toDateString())
            ->whereRaw('TIME(time_start) = TIME(?)', [(string) $pattern->time_start])
            ->whereRaw('TIME(time_end) = TIME(?)', [(string) $pattern->time_end]);

        if ($locationIdFilter !== null && $locationIdFilter > 0) {
            $q->where('location_id', $locationIdFilter);
        }

        return $q->orderBy('id')->first();
    }

    private function chainKey(CarbonImmutable $date, TeamScheduleSlot $slot): string
    {
        return $date->toDateString().'#'.(int) $slot->id;
    }

    private function timesEqual(mixed $a, mixed $b): bool
    {
        return substr((string) $a, 0, 5) === substr((string) $b, 0, 5);
    }
}
