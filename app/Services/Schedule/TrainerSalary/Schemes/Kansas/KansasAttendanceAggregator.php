<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Kansas;

use App\Models\LessonOccurrenceStatus;
use Illuminate\Support\Facades\DB;

/**
 * Тренировка Канзаса: (тренер, группа, слот, дата) при ≥1 «Посетил» с этим тренером.
 * Численность занятия — DISTINCT user_id на эту же четвёрку.
 */
final class KansasAttendanceAggregator
{
    private const TEAM_TITLE_WITHOUT_GROUP = 'Без группы';

    /**
     * @return array<int, array<int, array{
     *     team_id: int,
     *     team_title: string,
     *     trainings_count: int,
     *     students_visited_sum: int
     * }>> trainer_profile_id => team_id => stats
     */
    public function trainerGroupStats(int $partnerId, string $dateFrom, string $dateTo): array
    {
        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        if ($visitedStatusId === null) {
            return [];
        }

        $withoutGroup = self::TEAM_TITLE_WITHOUT_GROUP;

        $latestEventIds = DB::table('user_lesson_occurrence_status_events as e')
            ->selectRaw('MAX(e.id) as id')
            ->where('e.partner_id', $partnerId)
            ->whereBetween('e.occurrence_date', [$dateFrom, $dateTo])
            ->groupBy(
                'e.partner_id',
                'e.user_id',
                'e.team_schedule_slot_id',
                'e.occurrence_date',
                'e.user_lesson_package_id'
            );

        $sessionRows = DB::table('user_lesson_occurrence_status_events as e')
            ->joinSub($latestEventIds, 'latest', function ($join): void {
                $join->on('latest.id', '=', 'e.id');
            })
            ->join('user_lesson_occurrence_status_event_trainers as et', 'et.user_lesson_occurrence_status_event_id', '=', 'e.id')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->join('trainer_profiles as tp', function ($join) use ($partnerId): void {
                $join->on('tp.id', '=', 'et.trainer_profile_id')
                    ->where('tp.partner_id', '=', $partnerId);
            })
            ->join('team_schedule_slots as tss', 'tss.id', '=', 'e.team_schedule_slot_id')
            ->leftJoin('teams as t', 't.id', '=', 'tss.team_id')
            ->where('u.partner_id', $partnerId)
            ->where('u.is_enabled', 1)
            ->where('e.lesson_occurrence_status_id', $visitedStatusId)
            ->whereBetween('e.occurrence_date', [$dateFrom, $dateTo])
            ->selectRaw(
                'et.trainer_profile_id as trainer_profile_id,
                COALESCE(tss.team_id, 0) as team_id,
                COALESCE(MAX(t.title), ?) as team_title,
                e.team_schedule_slot_id as team_schedule_slot_id,
                e.occurrence_date as occurrence_date,
                COUNT(DISTINCT e.user_id) as headcount',
                [$withoutGroup],
            )
            ->groupByRaw('et.trainer_profile_id, COALESCE(tss.team_id, 0), e.team_schedule_slot_id, e.occurrence_date')
            ->get();

        $result = [];

        foreach ($sessionRows as $row) {
            $trainerId = (int) $row->trainer_profile_id;
            $teamId = (int) $row->team_id;
            $headcount = (int) $row->headcount;
            if ($headcount <= 0) {
                continue;
            }

            if (! isset($result[$trainerId][$teamId])) {
                $result[$trainerId][$teamId] = [
                    'team_id' => $teamId,
                    'team_title' => (string) $row->team_title,
                    'trainings_count' => 0,
                    'students_visited_sum' => 0,
                ];
            }

            $result[$trainerId][$teamId]['trainings_count']++;
            $result[$trainerId][$teamId]['students_visited_sum'] += $headcount;
            if ($result[$trainerId][$teamId]['team_title'] === '') {
                $result[$trainerId][$teamId]['team_title'] = (string) $row->team_title;
            }
        }

        return $result;
    }
}
