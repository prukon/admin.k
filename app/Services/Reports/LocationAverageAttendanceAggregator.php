<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LessonOccurrenceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Средняя посещаемость объекта: сумма численностей занятий / число занятий
 * групп с teams.location_id = объект.
 * Занятие — (слот × дата) с хотя бы одним актуальным системным статусом «Посетил».
 */
final class LocationAverageAttendanceAggregator
{
    /**
     * Подзапрос: location_id → avg_attendance (целое, математическое округление).
     * $yearMonth = YYYY-MM ограничивает occurrence_date; null — за всё время.
     */
    public function locationAveragesSubquery(int $partnerId, ?string $yearMonth): Builder
    {
        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        if ($visitedStatusId === null) {
            return DB::table('locations')
                ->selectRaw('id as location_id, CAST(NULL AS SIGNED) as avg_attendance')
                ->whereRaw('1 = 0');
        }

        $dateFrom = null;
        $dateTo = null;
        if ($yearMonth !== null && preg_match('/^\d{4}-\d{2}$/', $yearMonth) === 1) {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $yearMonth.'-01');
            if ($start instanceof CarbonImmutable) {
                $dateFrom = $start->toDateString();
                $dateTo = $start->endOfMonth()->toDateString();
            }
        }

        $latestEventIds = DB::table('user_lesson_occurrence_status_events as e')
            ->selectRaw('MAX(e.id) as id')
            ->where('e.partner_id', $partnerId)
            ->when($dateFrom !== null && $dateTo !== null, function ($query) use ($dateFrom, $dateTo): void {
                $query->whereBetween('e.occurrence_date', [$dateFrom, $dateTo]);
            })
            ->groupBy(
                'e.partner_id',
                'e.user_id',
                'e.team_schedule_slot_id',
                'e.occurrence_date',
                'e.user_lesson_package_id'
            );

        $sessions = DB::table('user_lesson_occurrence_status_events as e')
            ->joinSub($latestEventIds, 'latest', function ($join): void {
                $join->on('latest.id', '=', 'e.id');
            })
            ->join('team_schedule_slots as tss', 'tss.id', '=', 'e.team_schedule_slot_id')
            ->join('teams as att_team', function ($join): void {
                $join->on('att_team.id', '=', 'tss.team_id')
                    ->whereNull('att_team.deleted_at');
            })
            ->where('e.partner_id', $partnerId)
            ->where('e.lesson_occurrence_status_id', $visitedStatusId)
            ->whereNotNull('att_team.location_id')
            ->where('att_team.location_id', '>', 0)
            ->when($dateFrom !== null && $dateTo !== null, function ($query) use ($dateFrom, $dateTo): void {
                $query->whereBetween('e.occurrence_date', [$dateFrom, $dateTo]);
            })
            ->selectRaw('
                att_team.location_id as location_id,
                e.team_schedule_slot_id as team_schedule_slot_id,
                e.occurrence_date as occurrence_date,
                COUNT(DISTINCT e.user_id) as headcount
            ')
            ->groupByRaw('att_team.location_id, e.team_schedule_slot_id, e.occurrence_date')
            ->havingRaw('COUNT(DISTINCT e.user_id) > 0');

        return DB::query()
            ->fromSub($sessions, 'ltv_location_att_sessions')
            ->selectRaw('location_id, ROUND(SUM(headcount) / COUNT(*), 0) as avg_attendance')
            ->groupBy('location_id');
    }
}
