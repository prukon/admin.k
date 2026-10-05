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
        $sessions = $this->sessionHeadcounts($partnerId, $yearMonth);
        if ($sessions === null) {
            return DB::table('locations')
                ->selectRaw('id as location_id, CAST(NULL AS SIGNED) as avg_attendance')
                ->whereRaw('1 = 0');
        }

        return DB::query()
            ->fromSub($sessions, 'ltv_location_att_sessions')
            ->selectRaw('location_id, ROUND(SUM(headcount) / COUNT(*), 0) as avg_attendance')
            ->groupBy('location_id');
    }

    /**
     * Средняя посещаемость админа: занятия групп всех его объектов, одна формула с объектом.
     * $yearMonth = YYYY-MM; null — за всё время.
     */
    public function adminAveragesSubquery(int $partnerId, ?string $yearMonth): Builder
    {
        $sessions = $this->sessionHeadcounts($partnerId, $yearMonth);
        if ($sessions === null) {
            return DB::table('users')
                ->selectRaw('id as admin_user_id, CAST(NULL AS SIGNED) as avg_attendance')
                ->whereRaw('1 = 0');
        }

        $livingAdmins = DB::table('location_admin_user as att_location_admins')
            ->join('users as att_admin_users', function ($join): void {
                $join->on('att_admin_users.id', '=', 'att_location_admins.user_id')
                    ->whereNull('att_admin_users.deleted_at');
            })
            ->where('att_location_admins.partner_id', $partnerId)
            ->select([
                'att_location_admins.location_id',
                'att_location_admins.user_id',
            ]);

        return DB::query()
            ->fromSub($sessions, 'ltv_admin_att_sessions')
            ->joinSub($livingAdmins, 'ltv_admin_att_living', function ($join): void {
                $join->on('ltv_admin_att_living.location_id', '=', 'ltv_admin_att_sessions.location_id');
            })
            ->selectRaw('ltv_admin_att_living.user_id as admin_user_id, ROUND(SUM(ltv_admin_att_sessions.headcount) / COUNT(*), 0) as avg_attendance')
            ->groupBy('ltv_admin_att_living.user_id');
    }

    /**
     * Занятие объекта: (слот × дата) с численностью «Посетил».
     * null — у партнёра нет системного статуса «Посетил».
     */
    private function sessionHeadcounts(int $partnerId, ?string $yearMonth): ?Builder
    {
        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        if ($visitedStatusId === null) {
            return null;
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

        return $sessions;
    }
}
