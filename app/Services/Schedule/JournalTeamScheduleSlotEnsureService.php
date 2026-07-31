<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\TeamScheduleSlot;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * Для раскладки абонемента из журнала: взять самый ранний слот группы на weekday
 * или создать служебный часовой слот с 09:00 (09:00–10:00, затем 10:00–11:00, …).
 */
final class JournalTeamScheduleSlotEnsureService
{
    private const FIRST_HOUR = 9;

    private const LAST_HOUR = 22;

    private const OPEN_DATE_START = '2000-01-01';

    private const OPEN_DATE_END = '9999-12-31';

    public function resolveOrCreateEarliestForWeekday(int $partnerId, int $teamId, int $weekday): TeamScheduleSlot
    {
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException('Некорректный день недели.');
        }

        $existing = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('team_id', $teamId)
            ->where('weekday', $weekday)
            ->where('is_enabled', true)
            ->orderBy('time_start')
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

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
                if ($identical->is_enabled) {
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
                    $retry = TeamScheduleSlot::query()
                        ->where('partner_id', $partnerId)
                        ->where('team_id', $teamId)
                        ->where('weekday', $weekday)
                        ->where('is_enabled', true)
                        ->orderBy('time_start')
                        ->orderBy('id')
                        ->first();

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
