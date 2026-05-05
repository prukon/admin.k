<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TeamScheduleSlot;
use App\Models\TeamScheduleSlotException;
use App\Models\UserLessonOccurrenceStatusEvent;
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
     *     registrations: list<array{
     *         user_label: string,
     *         line: string,
     *         lesson_package_name: string|null,
     *         registration_kind: 'trial'|'package',
     *         is_trial_lesson: bool,
     *         user_team_schedule_slot_id: int,
     *         user_id: int,
     *         user_lesson_package_id: int|null,
     *         occurrence_status_history_count: int,
     *         current_status: array{id:int,code:string,title:string,color:string,icon:?string}|null,
     *         lessons_remaining: int|null,
     *         lessons_total: int|null
     *     }>,
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
        $this->attachLatestOccurrenceStatuses($partnerId, $registrationGroups);
        foreach ($out as $i => $item) {
            $key = $item['id'].'|'.$item['date'];
            $out[$i]['registrations'] = $registrationGroups[$key] ?? [];
        }

        return $out;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     */
    private function attachLatestOccurrenceStatuses(int $partnerId, array &$grouped): void
    {
        if ($grouped === []) {
            return;
        }

        $slotIds = [];
        $dateMin = null;
        $dateMax = null;

        foreach (array_keys($grouped) as $key) {
            $parts = explode('|', (string) $key, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $slotIds[] = (int) $parts[0];
            $d = $parts[1];
            if ($dateMin === null || $d < $dateMin) {
                $dateMin = $d;
            }
            if ($dateMax === null || $d > $dateMax) {
                $dateMax = $d;
            }
        }

        $slotIds = array_values(array_unique(array_filter($slotIds)));
        if ($slotIds === [] || $dateMin === null || $dateMax === null) {
            return;
        }

        $events = UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', $partnerId)
            ->whereIn('team_schedule_slot_id', $slotIds)
            ->whereDate('occurrence_date', '>=', $dateMin)
            ->whereDate('occurrence_date', '<=', $dateMax)
            ->with(['lessonOccurrenceStatus:id,code,title,color,icon'])
            ->orderBy('id')
            ->get(['id', 'user_id', 'team_schedule_slot_id', 'occurrence_date', 'user_lesson_package_id', 'lesson_occurrence_status_id']);

        $latest = [];
        foreach ($events as $ev) {
            $dateStr = $ev->occurrence_date instanceof \Carbon\CarbonInterface
                ? $ev->occurrence_date->format('Y-m-d')
                : (string) $ev->occurrence_date;
            $ulpKey = (int) ($ev->user_lesson_package_id ?? 0);
            $k = (int) $ev->user_id.'|'.(int) $ev->team_schedule_slot_id.'|'.$dateStr.'|'.$ulpKey;
            $latest[$k] = $ev;
        }

        foreach ($grouped as &$list) {
            foreach ($list as &$row) {
                $ulpKey = (int) ($row['user_lesson_package_id'] ?? 0);
                $uid = (int) ($row['user_id'] ?? 0);
                $sid = (int) ($row['team_schedule_slot_id'] ?? 0);
                $d = (string) ($row['occurrence_date'] ?? '');
                $lookup = $uid.'|'.$sid.'|'.$d.'|'.$ulpKey;
                /** @var UserLessonOccurrenceStatusEvent|null $hit */
                $hit = $latest[$lookup] ?? null;
                if ($hit === null || ! $hit->lessonOccurrenceStatus) {
                    $row['current_status'] = null;
                } else {
                    $st = $hit->lessonOccurrenceStatus;
                    $row['current_status'] = [
                        'id' => (int) $st->id,
                        'code' => (string) $st->code,
                        'title' => (string) $st->title,
                        'color' => (string) $st->color,
                        'icon' => $st->icon,
                    ];
                }
            }
            unset($row);
        }
        unset($list);
    }

    /**
     * @param list<array{id: int, date: string}> $occurrences
     * @return array<string, list<array<string, mixed>>>
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
            $grouped[$key][] = $this->formatSlotRegistrationLine($row, $dateStr);
        }

        foreach ($grouped as $key => $list) {
            usort($list, static fn (array $a, array $b) => strcmp($a['user_label'], $b['user_label']));
            $grouped[$key] = array_values($list);
        }

        $eventCountByKey = [];
        $allEvents = UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', $partnerId)
            ->whereIn('team_schedule_slot_id', $slotIds)
            ->whereDate('occurrence_date', '>=', $rangeStart)
            ->whereDate('occurrence_date', '<=', $rangeEnd)
            ->get(['team_schedule_slot_id', 'occurrence_date', 'user_id', 'user_lesson_package_id']);
        foreach ($allEvents as $e) {
            $ds = $e->occurrence_date instanceof \Carbon\CarbonInterface
                ? $e->occurrence_date->format('Y-m-d')
                : (string) $e->occurrence_date;
            $lk = (int) $e->team_schedule_slot_id.'|'.$ds.'|'.(int) $e->user_id.'|'.(int) ($e->user_lesson_package_id ?? 0);
            $eventCountByKey[$lk] = ($eventCountByKey[$lk] ?? 0) + 1;
        }

        foreach ($grouped as &$list) {
            foreach ($list as &$item) {
                $ulpKey = (int) ($item['user_lesson_package_id'] ?? 0);
                $lk = (int) $item['team_schedule_slot_id'].'|'.(string) ($item['occurrence_date'] ?? '').'|'.(int) $item['user_id'].'|'.$ulpKey;
                $item['occurrence_status_history_count'] = (int) ($eventCountByKey[$lk] ?? 0);
            }
            unset($item);
        }
        unset($list);

        return $grouped;
    }

    /**
     * @return array{
     *     user_label: string,
     *     line: string,
     *     lesson_package_name: string|null,
     *     registration_kind: string,
     *     is_trial_lesson: bool,
     *     user_team_schedule_slot_id: int,
     *     user_id: int,
     *     team_schedule_slot_id: int,
     *     occurrence_date: string,
     *     user_lesson_package_id: int|null,
     *     lessons_remaining: int|null,
     *     lessons_total: int|null
     * }
     */
    private function formatSlotRegistrationLine(UserTeamScheduleSlot $row, string $occurrenceDate): array
    {
        $user = $row->user;
        $userLabel = $user
            ? trim(($user->lastname ?? '').' '.($user->name ?? ''))
            : '';
        if ($userLabel === '') {
            $userLabel = 'Ученик #'.(int) $row->user_id;
        }

        $isTrial = (bool) $row->is_trial_lesson;

        $ulp = $row->userLessonPackage;
        $lp = $ulp?->lessonPackage;

        $lessonPackageName = null;
        if ($isTrial) {
            $kind = 'пробное занятие';
        } elseif ($lp !== null) {
            $lessonPackageName = trim((string) ($lp->name ?? ''));
            $kind = $lessonPackageName !== '' ? $lessonPackageName : 'Абонемент';
        } elseif ($ulp !== null) {
            $kind = 'абонемент №'.(int) $ulp->id;
        } else {
            $kind = 'запись без привязки к абонементу';
        }

        $registrationKind = $isTrial ? 'trial' : 'package';

        $hasPackageBalance = ! $isTrial && $row->user_lesson_package_id !== null && $ulp !== null;
        $hasTrialBalance = $isTrial && $row->user_lesson_package_id === null;

        $lessonsRemaining = null;
        $lessonsTotal = null;
        if ($hasTrialBalance) {
            $lessonsRemaining = (int) ($row->trial_lessons_remaining ?? 1);
            $lessonsTotal = (int) ($row->trial_lessons_total ?? 1);
        } elseif ($hasPackageBalance) {
            $lessonsRemaining = (int) $ulp->lessons_remaining;
            $lessonsTotal = (int) $ulp->lessons_total;
        }

        return [
            'user_label' => $userLabel,
            'line' => $userLabel.', '.$kind,
            'lesson_package_name' => $lessonPackageName,
            'registration_kind' => $registrationKind,
            'is_trial_lesson' => $isTrial,
            'user_team_schedule_slot_id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'team_schedule_slot_id' => (int) $row->team_schedule_slot_id,
            'occurrence_date' => $occurrenceDate,
            'user_lesson_package_id' => $row->user_lesson_package_id !== null ? (int) $row->user_lesson_package_id : null,
            'lessons_remaining' => $lessonsRemaining,
            'lessons_total' => $lessonsTotal,
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
     * Шаблон (день недели + время) задаётся при привязке из календаря; все слоты цепочки — та же группа (team_id), что у якоря.
     *
     * @param Collection<int, object{weekday: int, time_start: mixed, time_end: mixed}> $patterns
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
            throw new \InvalidArgumentException('Не задан шаблон привязки (день недели и время).');
        }

        $teamId = (int) $anchorSlot->team_id;
        if ($teamId < 1) {
            throw new \InvalidArgumentException('У слота расписания не указана группа.');
        }

        $anchorDow = (int) $anchorDate->format('N');
        if ((int) $anchorSlot->weekday !== $anchorDow) {
            throw new \InvalidArgumentException('Якорный слот не соответствует выбранному дню календаря.');
        }

        if (!$this->slotActiveOnDate($anchorSlot, $anchorDate)) {
            throw new \InvalidArgumentException('Слот недействителен на выбранную дату.');
        }

        if (!$this->patternMatchesSlot($patterns, $anchorSlot, $anchorDow)) {
            throw new \InvalidArgumentException('Якорный слот не совпадает с выбранным днём и временем привязки.');
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

                $slot = $this->resolveFixedPatternSlotOnDay(
                    $anchorSlot,
                    $anchorDate,
                    $partnerId,
                    $d,
                    $pattern,
                    $teamId,
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
     * @param Collection<int, object{weekday: int, time_start: mixed, time_end: mixed}> $patterns
     */
    public function patternMatchesSlot(Collection $patterns, TeamScheduleSlot $slot, int $dow): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->patternEqualsSlot($pattern, $slot, $dow)) {
                return true;
            }
        }

        return false;
    }

    public function patternEqualsSlot(object $pattern, TeamScheduleSlot $slot, int $dow): bool
    {
        if ((int) $pattern->weekday !== $dow) {
            return false;
        }

        return $this->timesEqual($pattern->time_start, $slot->time_start)
            && $this->timesEqual($pattern->time_end, $slot->time_end);
    }

    /**
     * Слот цепочки на конкретную дату по шаблону: якорный день использует переданный слот клика, остальные — поиск в расписании группы.
     */
    public function resolveFixedPatternSlotOnDay(
        TeamScheduleSlot $anchorSlot,
        CarbonImmutable $anchorDate,
        int $partnerId,
        CarbonImmutable $day,
        object $pattern,
        int $teamId,
        ?int $locationIdFilter
    ): ?TeamScheduleSlot {
        if ($day->toDateString() === $anchorDate->toDateString()
            && $this->patternEqualsSlot($pattern, $anchorSlot, (int) $day->format('N'))) {
            return $anchorSlot;
        }

        return $this->findMatchingTeamSlotForPatternOnDay($partnerId, $day, $pattern, $teamId, $locationIdFilter);
    }

    /**
     * На каждую дату в периоде [anchor, periodEnd], попадающую под один из шаблонов (день недели), должно быть активное неисключённое занятие группы.
     *
     * @param Collection<int, object{weekday: int, time_start: mixed, time_end: mixed}> $patterns
     *
     * @throws \InvalidArgumentException
     */
    public function assertEveryFixedPatternOccurrenceResolvableInPeriod(
        TeamScheduleSlot $anchorSlot,
        CarbonImmutable $anchorDate,
        CarbonImmutable $periodEnd,
        Collection $patterns,
        int $partnerId,
        int $teamId,
        ?int $locationIdFilter
    ): void {
        $anchorDate = $anchorDate->startOfDay();
        $end = $periodEnd->startOfDay();

        foreach ($patterns as $pattern) {
            for ($d = $anchorDate; $d->lte($end); $d = $d->addDay()) {
                if ((int) $pattern->weekday !== (int) $d->format('N')) {
                    continue;
                }

                $slot = $this->resolveFixedPatternSlotOnDay(
                    $anchorSlot,
                    $anchorDate,
                    $partnerId,
                    $d,
                    $pattern,
                    $teamId,
                    $locationIdFilter
                );

                if ($slot === null) {
                    throw new \InvalidArgumentException(
                        'В периоде абонемента нет занятия группы по шаблону '
                        .$this->fixedPatternHumanLabel($pattern).' на дату '.$d->toDateString().'.'
                    );
                }

                if (! $this->slotActiveOnDate($slot, $d)) {
                    throw new \InvalidArgumentException(
                        'Слот недействителен на '.$d->toDateString().' ('.$this->fixedPatternHumanLabel($pattern).').'
                    );
                }

                if ($this->isOccurrenceSkipped((int) $slot->id, $d)) {
                    throw new \InvalidArgumentException(
                        'На '.$d->toDateString().' занятие исключено из расписания школы ('.$this->fixedPatternHumanLabel($pattern).').'
                    );
                }
            }
        }
    }

    private function fixedPatternHumanLabel(object $pattern): string
    {
        $ts = substr((string) $pattern->time_start, 0, 5);
        $te = substr((string) $pattern->time_end, 0, 5);

        return 'день '.$pattern->weekday.', '.$ts.'–'.$te;
    }

    /**
     * Слот расписания группы на конкретную дату по шаблону (день недели + время).
     */
    public function findMatchingTeamSlotForPatternOnDay(
        int $partnerId,
        CarbonImmutable $day,
        object $pattern,
        int $teamId,
        ?int $locationIdFilter
    ): ?TeamScheduleSlot {
        return $this->findMatchingSlot($partnerId, $day, $pattern, $teamId, $locationIdFilter);
    }

    private function findMatchingSlot(
        int $partnerId,
        CarbonImmutable $day,
        object $pattern,
        int $teamId,
        ?int $locationIdFilter
    ): ?TeamScheduleSlot {
        $dow = (int) $day->format('N');
        if ((int) $pattern->weekday !== $dow) {
            return null;
        }

        $q = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('team_id', $teamId)
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

    /**
     * Две занятости пересекаются по времени на одну календарную дату (полуоткрытый интервал в минутах).
     */
    public function clockIntervalsOverlap(string $aStartHm, string $aEndHm, string $bStartHm, string $bEndHm): bool
    {
        $a1 = $this->minutesFromClock($aStartHm);
        $a2 = $this->minutesFromClock($aEndHm);
        $b1 = $this->minutesFromClock($bStartHm);
        $b2 = $this->minutesFromClock($bEndHm);

        return max($a1, $b1) < min($a2, $b2);
    }

    private function minutesFromClock(string $hm): int
    {
        $parts = explode(':', substr($hm, 0, 5));

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    /**
     * Нельзя ставить новую серию занятий, если у ученика уже есть любое занятие в календаре с пересечением по времени в ту же дату.
     *
     * @param list<array{date: CarbonImmutable, slot: TeamScheduleSlot}> $chain
     */
    public function assertFixedChainHasNoTimeOverlapWithExistingUserLessons(
        int $userId,
        int $partnerId,
        array $chain
    ): void {
        foreach ($chain as $item) {
            /** @var CarbonImmutable $date */
            $date = $item['date'];
            /** @var TeamScheduleSlot $candidateSlot */
            $candidateSlot = $item['slot'];

            $this->assertOccurrenceDoesNotOverlapExistingUserLessons(
                $userId,
                $partnerId,
                $date,
                $candidateSlot
            );
        }
    }

    private function assertOccurrenceDoesNotOverlapExistingUserLessons(
        int $userId,
        int $partnerId,
        CarbonImmutable $date,
        TeamScheduleSlot $candidateSlot
    ): void {
        $dateStr = $date->toDateString();

        $rows = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $userId)
            ->whereDate('starts_at', '<=', $dateStr)
            ->whereDate('ends_at', '>=', $dateStr)
            ->get(['team_schedule_slot_id']);

        $cStart = substr((string) $candidateSlot->time_start, 0, 5);
        $cEnd = substr((string) $candidateSlot->time_end, 0, 5);

        foreach ($rows as $row) {
            $other = TeamScheduleSlot::query()->whereKey((int) $row->team_schedule_slot_id)->first();
            if (! $other) {
                continue;
            }

            if ((int) $other->weekday !== (int) $date->format('N')) {
                continue;
            }

            if (! $this->slotActiveOnDate($other, $date)) {
                continue;
            }

            if ($this->isOccurrenceSkipped((int) $other->id, $date)) {
                continue;
            }

            $oStart = substr((string) $other->time_start, 0, 5);
            $oEnd = substr((string) $other->time_end, 0, 5);

            if ($this->clockIntervalsOverlap($cStart, $cEnd, $oStart, $oEnd)) {
                throw new \InvalidArgumentException(
                    'Конфликт расписания на '.$dateStr.': время '.$cStart.'–'.$cEnd
                    .' пересекается с уже существующей записью ученика ('.$oStart.'–'.$oEnd.').'
                );
            }
        }
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
