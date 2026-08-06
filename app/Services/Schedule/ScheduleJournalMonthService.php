<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Models\LessonPackage;
use App\Services\Postpay\PostpayMonth;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Данные журнала /schedule из единого контура user_team_schedule_slots + статусы.
 */
final class ScheduleJournalMonthService
{
    /**
     * @param list<int> $userIds
     * @return array<string, list<array<string, mixed>>> key = "{userId}_{Y-m-d}"
     */
    public function occurrencesByUserDate(
        int $partnerId,
        array $userIds,
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        string|int|null $teamFilter = 'all',
    ): array {
        if ($userIds === []) {
            return [];
        }

        $query = UserTeamScheduleSlot::query()
            ->with([
                'slot:id,team_id,weekday,time_start,time_end,location_id',
                'slot.team:id,title',
                'userLessonPackage:id,lesson_package_id,lessons_remaining,lessons_total',
                'userLessonPackage.lessonPackage:id,name,schedule_type',
            ])
            ->where('partner_id', $partnerId)
            ->whereIn('user_id', $userIds)
            ->whereDate('starts_at', '>=', $startOfMonth->format('Y-m-d'))
            ->whereDate('starts_at', '<=', $endOfMonth->format('Y-m-d'));

        if (is_numeric($teamFilter)) {
            $teamId = (int) $teamFilter;
            $query->whereHas('slot', fn ($q) => $q->where('team_id', $teamId));
        }

        /** @var Collection<int, UserTeamScheduleSlot> $rows */
        $rows = $query
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $statusMap = $this->latestStatusMap($partnerId, $rows);
        $postpayNames = $this->postpayPackageNamesByUserTeamMonth($rows);

        $grouped = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->starts_at)->format('Y-m-d');
            $key = $row->user_id.'_'.$date;
            $statusKey = $this->occurrenceKey(
                (int) $row->user_id,
                (int) $row->team_schedule_slot_id,
                $date,
                $row->user_lesson_package_id !== null ? (int) $row->user_lesson_package_id : null,
            );
            $statusEvent = $statusMap[$statusKey] ?? null;
            $status = $statusEvent?->lessonOccurrenceStatus;

            $timeStart = $row->slot?->time_start;
            $timeEnd = $row->slot?->time_end;
            $teamId = $row->slot?->team_id !== null ? (int) $row->slot->team_id : null;
            $isPostpay = $row->user_lesson_package_id === null && ! (bool) $row->is_trial_lesson;
            $postpayName = null;
            $postpayPricePerLesson = null;
            if ($isPostpay && $teamId !== null) {
                $monthKey = (int) $row->user_id.'|'.$teamId.'|'.PostpayMonth::firstDayFromDate($date);
                $postpayInfo = $postpayNames[$monthKey] ?? null;
                if (is_array($postpayInfo)) {
                    $postpayName = $postpayInfo['name'] ?? 'Постоплата';
                    $postpayPricePerLesson = $postpayInfo['price_per_lesson'] ?? null;
                } else {
                    $postpayName = is_string($postpayInfo) && $postpayInfo !== '' ? $postpayInfo : 'Постоплата';
                }
            }

            $grouped[$key][] = [
                'utss_id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'occurrence_date' => $date,
                'team_schedule_slot_id' => (int) $row->team_schedule_slot_id,
                'user_lesson_package_id' => $row->user_lesson_package_id !== null ? (int) $row->user_lesson_package_id : null,
                'is_trial_lesson' => (bool) $row->is_trial_lesson,
                'is_postpay' => $isPostpay,
                'team_id' => $teamId,
                'team_title' => (string) ($row->slot?->team?->title ?? ''),
                // Для постоплаты время слота техническое (служебный слот дня недели) — в UI не показываем.
                'time_start' => $isPostpay ? null : ($timeStart ? substr((string) $timeStart, 0, 5) : null),
                'time_end' => $isPostpay ? null : ($timeEnd ? substr((string) $timeEnd, 0, 5) : null),
                'package_name' => $row->is_trial_lesson
                    ? 'Пробное'
                    : ($isPostpay
                        ? (string) $postpayName
                        : (string) ($row->userLessonPackage?->lessonPackage?->name ?? 'Абонемент')),
                'price_per_lesson' => $isPostpay ? $postpayPricePerLesson : null,
                'lesson_occurrence_status_id' => $status?->id !== null ? (int) $status->id : null,
                'status_title' => $status?->title,
                'status_icon' => $status?->icon,
                'status_color' => $status?->color,
                'status_code' => $status?->code,
                'trainer_profile_id' => $statusEvent?->trainer_profile_id !== null
                    ? (int) $statusEvent->trainer_profile_id
                    : null,
                'comment' => $statusEvent?->comment,
            ];
        }

        foreach ($grouped as $key => $items) {
            usort($items, static function (array $a, array $b): int {
                $timeCmp = strcmp((string) ($a['time_start'] ?? ''), (string) ($b['time_start'] ?? ''));
                if ($timeCmp !== 0) {
                    return $timeCmp;
                }

                return $a['utss_id'] <=> $b['utss_id'];
            });
            $grouped[$key] = $items;
        }

        return $grouped;
    }

    /**
     * Fixed-назначения ученика для колонки раскладки в журнале.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     lessons_total: int,
     *     lessons_remaining: int,
     *     placeable: bool,
     *     disabled_reason: string|null
     * }>
     */
    public function fixedAssignmentsForUser(int $partnerId, int $userId): array
    {
        $assignments = UserLessonPackage::query()
            ->with(['lessonPackage:id,name,schedule_type,partner_id'])
            ->where('user_id', $userId)
            ->whereHas('lessonPackage', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId)->where('schedule_type', 'fixed');
            })
            ->orderByDesc('id')
            ->get();

        $result = [];
        foreach ($assignments as $ulp) {
            $placeable = $ulp->isJournalPlaceable();

            $disabledReason = null;
            if ($ulp->isLaidOutInSchedule()) {
                $disabledReason = 'Абонемент уже разложен в расписание';
            } elseif ((int) $ulp->lessons_total < 1) {
                $disabledReason = 'Не задан объём занятий';
            }

            $billingMonth = $ulp->billing_month !== null
                ? $ulp->billing_month->format('Y-m-d')
                : null;

            $result[] = [
                'id' => (int) $ulp->id,
                'name' => (string) ($ulp->lessonPackage?->name ?? 'Абонемент'),
                'lessons_total' => (int) $ulp->lessons_total,
                'lessons_remaining' => (int) $ulp->lessons_remaining,
                'placeable' => $placeable,
                'disabled_reason' => $disabledReason,
                'from_setting_prices' => $ulp->isFromSettingPrices(),
                'billing_month' => $billingMonth,
                'starts_at' => $ulp->starts_at !== null ? $ulp->starts_at->format('Y-m-d') : null,
                'ends_at' => $ulp->ends_at !== null ? $ulp->ends_at->format('Y-m-d') : null,
            ];
        }

        return $result;
    }

    /**
     * Месячные гибкие назначения учеников для журнала (billing_month = месяц экрана, есть остаток слотов).
     *
     * @param  list<int>  $userIds
     * @return array<int, list<array{
     *     id: int,
     *     name: string,
     *     team_id: int,
     *     team_title: string,
     *     lessons_total: int,
     *     slots_used: int,
     *     slots_remaining: int,
     *     billing_month: string,
     *     starts_at: string|null,
     *     ends_at: string|null
     * }>>
     */
    public function flexibleAssignableByUserForBillingMonth(
        int $partnerId,
        array $userIds,
        string $billingMonthYmd,
        string|int|null $teamFilter = 'all',
    ): array {
        if ($userIds === []) {
            return [];
        }

        $billingMonth = Carbon::parse($billingMonthYmd)->startOfMonth()->format('Y-m-d');

        $query = UserLessonPackage::query()
            ->with([
                'lessonPackage:id,name,schedule_type,partner_id',
                'team:id,title',
                'userTeamScheduleSlots:id,user_lesson_package_id',
            ])
            ->whereIn('user_id', $userIds)
            ->whereDate('billing_month', $billingMonth)
            ->whereHas('lessonPackage', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId)
                    ->where('schedule_type', LessonPackage::SCHEDULE_TYPE_FLEXIBLE);
            })
            ->where('lessons_total', '>', 0)
            ->orderBy('id');

        if (is_numeric($teamFilter)) {
            $query->where('team_id', (int) $teamFilter);
        }

        $rows = $query->get();
        $result = [];

        foreach ($rows as $ulp) {
            $remaining = $ulp->calendarSlotsRemaining();
            if ($remaining < 1) {
                continue;
            }

            $teamId = (int) ($ulp->team_id ?? 0);
            if ($teamId < 1) {
                continue;
            }

            $userId = (int) $ulp->user_id;
            $used = (int) $ulp->lessons_total - $remaining;

            $result[$userId][] = [
                'id' => (int) $ulp->id,
                'name' => (string) ($ulp->lessonPackage?->name ?? 'Гибкий абонемент'),
                'team_id' => $teamId,
                'team_title' => (string) ($ulp->team?->title ?? ''),
                'lessons_total' => (int) $ulp->lessons_total,
                'slots_used' => $used,
                'slots_remaining' => $remaining,
                'billing_month' => $billingMonth,
                'starts_at' => $ulp->starts_at !== null ? $ulp->starts_at->format('Y-m-d') : null,
                'ends_at' => $ulp->ends_at !== null ? $ulp->ends_at->format('Y-m-d') : null,
            ];
        }

        return $result;
    }

    /**
     * @param Collection<int, UserTeamScheduleSlot> $rows
     * @return array<string, UserLessonOccurrenceStatusEvent>
     */
    private function latestStatusMap(int $partnerId, Collection $rows): array
    {
        $userIds = $rows->pluck('user_id')->unique()->values()->all();
        $dates = $rows->map(fn (UserTeamScheduleSlot $r) => Carbon::parse($r->starts_at)->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        if ($userIds === [] || $dates === []) {
            return [];
        }

        $events = UserLessonOccurrenceStatusEvent::query()
            ->with(['lessonOccurrenceStatus'])
            ->where('partner_id', $partnerId)
            ->whereIn('user_id', $userIds)
            ->whereIn('occurrence_date', $dates)
            ->orderByDesc('id')
            ->get();

        $map = [];
        foreach ($events as $event) {
            $key = $this->occurrenceKey(
                (int) $event->user_id,
                (int) $event->team_schedule_slot_id,
                Carbon::parse($event->occurrence_date)->format('Y-m-d'),
                $event->user_lesson_package_id !== null ? (int) $event->user_lesson_package_id : null,
            );
            if (! isset($map[$key])) {
                $map[$key] = $event;
            }
        }

        return $map;
    }

    /**
     * Шаблоны постоплаты для UTSS без ULP: ключ "{userId}|{teamId}|{YYYY-MM-01}".
     *
     * @param  Collection<int, UserTeamScheduleSlot>  $rows
     * @return array<string, array{name: string, price_per_lesson: float}>
     */
    private function postpayPackageNamesByUserTeamMonth(Collection $rows): array
    {
        $pairs = [];
        foreach ($rows as $row) {
            if ($row->user_lesson_package_id !== null || (bool) $row->is_trial_lesson) {
                continue;
            }
            $teamId = $row->slot?->team_id !== null ? (int) $row->slot->team_id : 0;
            if ($teamId <= 0) {
                continue;
            }
            $month = PostpayMonth::firstDayFromDate(Carbon::parse($row->starts_at)->format('Y-m-d'));
            $pairs[(int) $row->user_id.'|'.$teamId.'|'.$month] = [
                'user_id' => (int) $row->user_id,
                'team_id' => $teamId,
                'month' => $month,
            ];
        }

        if ($pairs === []) {
            return [];
        }

        $userIds = array_values(array_unique(array_column($pairs, 'user_id')));
        $teamIds = array_values(array_unique(array_column($pairs, 'team_id')));
        $months = array_values(array_unique(array_column($pairs, 'month')));

        $priceRows = UserPrice::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('team_id', $teamIds)
            ->whereIn('new_month', $months)
            ->whereNotNull('lesson_package_id')
            ->with(['lessonPackage:id,name,schedule_type,price_cents'])
            ->get();

        $map = [];
        foreach ($priceRows as $priceRow) {
            $package = $priceRow->lessonPackage;
            if (! $package || ! $package->isPostpay()) {
                continue;
            }
            $key = (int) $priceRow->user_id.'|'.(int) $priceRow->team_id.'|'
                .PostpayMonth::firstDayFromDate((string) $priceRow->new_month);
            $name = trim((string) $package->name);
            $map[$key] = [
                'name' => $name !== '' ? $name : 'Постоплата',
                'price_per_lesson' => $package->priceRub(),
            ];
        }

        return $map;
    }

    private function occurrenceKey(int $userId, int $slotId, string $date, ?int $ulpId): string
    {
        return $userId.'|'.$slotId.'|'.$date.'|'.($ulpId ?? 'trial');
    }
}
