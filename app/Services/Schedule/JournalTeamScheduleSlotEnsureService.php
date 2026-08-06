<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\TeamScheduleSlot;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * Для раскладки абонемента / постоплаты из журнала: взять самый ранний слот группы на weekday,
 * который покрывает нужный диапазон дат, или создать служебный часовой слот с 09:00
 * (09:00–10:00, затем 10:00–11:00, …) с открытым периодом 2000-01-01…9999-12-31.
 *
 * Школьные слоты с узким date_start/date_end не трогаем: если они не покрывают раскладку —
 * создаётся отдельный служебный слот (модель «только журнал», без UI расписания школы).
 */
final class JournalTeamScheduleSlotEnsureService
{
    private const FIRST_HOUR = 9;

    private const LAST_HOUR = 22;

    private const OPEN_DATE_START = '2000-01-01';

    private const OPEN_DATE_END = '9999-12-31';

    /**
     * @param  CarbonImmutable|null  $coverFrom  Начало периода, который слот должен покрывать (включительно)
     * @param  CarbonImmutable|null  $coverTo    Конец периода (включительно); при null = coverFrom
     */
    public function resolveOrCreateEarliestForWeekday(
        int $partnerId,
        int $teamId,
        int $weekday,
        ?CarbonImmutable $coverFrom = null,
        ?CarbonImmutable $coverTo = null,
    ): TeamScheduleSlot {
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException('Некорректный день недели.');
        }

        [$from, $to] = $this->normalizeCoverRange($coverFrom, $coverTo);

        $covering = $this->findEarliestCovering($partnerId, $teamId, $weekday, $from, $to);
        if ($covering !== null) {
            return $covering;
        }

        return $this->createOpenRangeSlot($partnerId, $teamId, $weekday, $from, $to);
    }

    /**
     * Слот на weekday для дополнительного занятия ученика в тот же день:
     * не занятый парой (user_id, team_schedule_slot_id, starts_at) — иначе unique violation.
     */
    public function resolveOrCreateNextFreeForUserDate(
        int $partnerId,
        int $teamId,
        int $weekday,
        int $userId,
        string $occurrenceDateYmd,
    ): TeamScheduleSlot {
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException('Некорректный день недели.');
        }

        $coverDay = CarbonImmutable::parse($occurrenceDateYmd)->startOfDay();
        $usedSlotIds = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $userId)
            ->whereDate('starts_at', $occurrenceDateYmd)
            ->pluck('team_schedule_slot_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $usedSet = array_fill_keys($usedSlotIds, true);

        $candidates = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('team_id', $teamId)
            ->where('weekday', $weekday)
            ->where('is_enabled', true)
            ->orderBy('time_start')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $slot) {
            if (isset($usedSet[(int) $slot->id])) {
                continue;
            }
            if ($this->slotCoversRange($slot, $coverDay, $coverDay)) {
                return $slot;
            }
        }

        return $this->createOpenRangeSlotAvoidingUsed(
            $partnerId,
            $teamId,
            $weekday,
            $coverDay,
            $usedSet,
        );
    }

    /**
     * @param  array<int, true>  $usedSlotIds
     */
    private function createOpenRangeSlotAvoidingUsed(
        int $partnerId,
        int $teamId,
        int $weekday,
        CarbonImmutable $coverDay,
        array $usedSlotIds,
    ): TeamScheduleSlot {
        for ($hour = self::FIRST_HOUR; $hour <= self::LAST_HOUR; $hour++) {
            $timeStart = sprintf('%02d:00:00', $hour);
            $timeEnd = sprintf('%02d:00:00', $hour + 1);

            $identical = TeamScheduleSlot::query()
                ->where('partner_id', $partnerId)
                ->where('team_id', $teamId)
                ->where('weekday', $weekday)
                ->whereNull('location_id')
                ->whereRaw('TIME(time_start) = TIME(?)', [$timeStart])
                ->whereRaw('TIME(time_end) = TIME(?)', [$timeEnd])
                ->whereDate('date_start', self::OPEN_DATE_START)
                ->whereDate('date_end', self::OPEN_DATE_END)
                ->first();

            if ($identical) {
                if (
                    $identical->is_enabled
                    && ! isset($usedSlotIds[(int) $identical->id])
                    && $this->slotCoversRange($identical, $coverDay, $coverDay)
                ) {
                    return $identical;
                }

                continue;
            }

            try {
                return TeamScheduleSlot::query()->create([
                    'partner_id' => $partnerId,
                    'team_id' => $teamId,
                    'location_id' => null,
                    'weekday' => $weekday,
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                    'date_start' => self::OPEN_DATE_START,
                    'date_end' => self::OPEN_DATE_END,
                    'is_enabled' => true,
                ]);
            } catch (QueryException $e) {
                $code = $e->errorInfo[1] ?? null;
                if ((int) $code === 1062) {
                    continue;
                }

                throw $e;
            }
        }

        throw new InvalidArgumentException(
            'Не удалось создать слот для дополнительного занятия в этот день (нет свободного часа с 09:00).'
        );
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function normalizeCoverRange(?CarbonImmutable $coverFrom, ?CarbonImmutable $coverTo): array
    {
        if ($coverFrom === null && $coverTo === null) {
            return [null, null];
        }

        $from = ($coverFrom ?? $coverTo)?->startOfDay();
        $to = ($coverTo ?? $coverFrom)?->startOfDay();
        if ($from !== null && $to !== null && $to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function findEarliestCovering(
        int $partnerId,
        int $teamId,
        int $weekday,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
    ): ?TeamScheduleSlot {
        $candidates = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('team_id', $teamId)
            ->where('weekday', $weekday)
            ->where('is_enabled', true)
            ->orderBy('time_start')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $slot) {
            if ($this->slotCoversRange($slot, $from, $to)) {
                return $slot;
            }
        }

        return null;
    }

    private function slotCoversRange(
        TeamScheduleSlot $slot,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
    ): bool {
        // Без требований к датам — любой enabled (обратная совместимость вызовов без cover*).
        if ($from === null || $to === null) {
            return true;
        }

        $start = CarbonImmutable::parse(
            $slot->date_start instanceof \DateTimeInterface
                ? $slot->date_start->format('Y-m-d')
                : (string) $slot->date_start
        )->startOfDay();
        $end = CarbonImmutable::parse(
            $slot->date_end instanceof \DateTimeInterface
                ? $slot->date_end->format('Y-m-d')
                : (string) $slot->date_end
        )->startOfDay();

        return $start->lte($from) && $end->gte($to);
    }

    private function createOpenRangeSlot(
        int $partnerId,
        int $teamId,
        int $weekday,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
    ): TeamScheduleSlot {
        for ($hour = self::FIRST_HOUR; $hour <= self::LAST_HOUR; $hour++) {
            $timeStart = sprintf('%02d:00:00', $hour);
            $timeEnd = sprintf('%02d:00:00', $hour + 1);

            $identical = TeamScheduleSlot::query()
                ->where('partner_id', $partnerId)
                ->where('team_id', $teamId)
                ->where('weekday', $weekday)
                ->whereNull('location_id')
                ->whereRaw('TIME(time_start) = TIME(?)', [$timeStart])
                ->whereRaw('TIME(time_end) = TIME(?)', [$timeEnd])
                ->whereDate('date_start', self::OPEN_DATE_START)
                ->whereDate('date_end', self::OPEN_DATE_END)
                ->first();

            if ($identical) {
                if ($identical->is_enabled && $this->slotCoversRange($identical, $from, $to)) {
                    return $identical;
                }

                continue;
            }

            try {
                return TeamScheduleSlot::query()->create([
                    'partner_id' => $partnerId,
                    'team_id' => $teamId,
                    'location_id' => null,
                    'weekday' => $weekday,
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                    'date_start' => self::OPEN_DATE_START,
                    'date_end' => self::OPEN_DATE_END,
                    'is_enabled' => true,
                ]);
            } catch (QueryException $e) {
                $code = $e->errorInfo[1] ?? null;
                if ((int) $code === 1062) {
                    $retry = $this->findEarliestCovering($partnerId, $teamId, $weekday, $from, $to);
                    if ($retry) {
                        return $retry;
                    }

                    continue;
                }

                throw $e;
            }
        }

        throw new InvalidArgumentException(
            'Не удалось создать слот расписания школы для выбранного дня недели (нет свободного часа с 09:00).'
        );
    }
}
