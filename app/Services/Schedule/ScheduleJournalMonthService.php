<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Services\Postpay\PostpayMonth;
use App\Support\Money;
use App\Support\ScheduleOccurrenceTrainerIds;
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
                'userLessonPackage:id,lesson_package_id,lessons_remaining,lessons_total,fee_amount_cents',
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
            $postpayPriceCents = null;
            if ($isPostpay && $teamId !== null) {
                $monthKey = (int) $row->user_id.'|'.$teamId.'|'.PostpayMonth::firstDayFromDate($date);
                $postpayInfo = $postpayNames[$monthKey] ?? null;
                if (is_array($postpayInfo)) {
                    $postpayName = $postpayInfo['name'] ?? 'Постоплата';
                    $postpayPricePerLesson = $postpayInfo['price_per_lesson'] ?? null;
                    $postpayPriceCents = isset($postpayInfo['price_cents']) ? (int) $postpayInfo['price_cents'] : null;
                } else {
                    $postpayName = is_string($postpayInfo) && $postpayInfo !== '' ? $postpayInfo : 'Постоплата';
                }
            }

            if ($row->is_trial_lesson) {
                $packageName = 'Пробное';
                $packageHover = 'Пробное';
            } elseif ($isPostpay) {
                $packageName = (string) $postpayName;
                $packageHover = self::postpayPackageHoverLabel($postpayPriceCents);
            } else {
                $packageName = (string) ($row->userLessonPackage?->lessonPackage?->name ?? 'Абонемент');
                $packageHover = self::packageHoverLabel(
                    $packageName,
                    (int) ($row->userLessonPackage?->fee_amount_cents ?? 0),
                );
            }

            $isAttended = (string) ($status?->code ?? '') === LessonOccurrenceStatus::CODE_ATTENDED;
            $trainerIds = [];
            $trainerName = null;
            if ($isAttended && $statusEvent !== null) {
                $profiles = $statusEvent->relationLoaded('trainerProfiles')
                    ? $statusEvent->trainerProfiles
                    : collect();
                if ($profiles->isEmpty() && $statusEvent->trainer_profile_id !== null) {
                    $profiles = collect([$statusEvent->trainerProfile])->filter();
                }
                $trainerIds = $profiles
                    ->map(fn ($p) => (int) $p->id)
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();
                $trainerName = ScheduleOccurrenceTrainerIds::formatNames($profiles);
            }
            $packageHover = self::appendTrainerToHover($packageHover, $isAttended, $trainerName);

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
                'package_name' => $packageName,
                'package_hover' => $packageHover,
                'price_per_lesson' => $isPostpay ? $postpayPricePerLesson : null,
                'lesson_occurrence_status_id' => $status?->id !== null ? (int) $status->id : null,
                'status_title' => $status?->title,
                'status_icon' => $status?->icon,
                'status_color' => $status?->color,
                'status_code' => $status?->code,
                'trainer_profile_id' => $trainerIds[0] ?? null,
                'trainer_profile_ids' => $trainerIds,
                'trainer_name' => $trainerName,
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
     * @param  list<int>  $userIds
     * @return array<int, list<array{
     *     id: int,
     *     name: string,
     *     lessons_total: int,
     *     lessons_remaining: int,
     *     fee_amount_cents: int,
     *     team_id: int|null,
     *     placeable: bool,
     *     disabled_reason: string|null
     * }>>
     */
    public function fixedAssignmentsByUser(int $partnerId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $result = [];
        foreach ($userIds as $userId) {
            $result[$userId] = [];
        }

        if ($userIds === []) {
            return $result;
        }

        $assignments = UserLessonPackage::query()
            ->with([
                'lessonPackage:id,name,schedule_type,partner_id',
                'userTeamScheduleSlots:id,user_lesson_package_id',
            ])
            ->whereIn('user_id', $userIds)
            ->whereHas('lessonPackage', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId)->where('schedule_type', 'fixed');
            })
            ->orderByDesc('id')
            ->get();

        foreach ($assignments as $ulp) {
            $result[(int) $ulp->user_id][] = $this->mapFixedAssignmentRow($ulp);
        }

        return $result;
    }

    /**
     * Fixed-назначения ученика для колонки раскладки в журнале.
     *
     * @return list<array<string, mixed>>
     */
    public function fixedAssignmentsForUser(int $partnerId, int $userId): array
    {
        return $this->fixedAssignmentsByUser($partnerId, [$userId])[$userId] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFixedAssignmentRow(UserLessonPackage $ulp): array
    {
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

        return [
            'id' => (int) $ulp->id,
            'name' => (string) ($ulp->lessonPackage?->name ?? 'Абонемент'),
            'lessons_total' => (int) $ulp->lessons_total,
            'lessons_remaining' => (int) $ulp->lessons_remaining,
            'fee_amount_cents' => (int) ($ulp->fee_amount_cents ?? 0),
            'team_id' => $ulp->team_id !== null ? (int) $ulp->team_id : null,
            'placeable' => $placeable,
            'disabled_reason' => $disabledReason,
            'from_setting_prices' => $ulp->isFromSettingPrices(),
            'billing_month' => $billingMonth,
            'starts_at' => $ulp->starts_at !== null ? $ulp->starts_at->format('Y-m-d') : null,
            'ends_at' => $ulp->ends_at !== null ? $ulp->ends_at->format('Y-m-d') : null,
        ];
    }

    /**
     * Месячные гибкие назначения учеников для журнала (billing_month = месяц экрана, есть остаток слотов).
     *
     * @param  list<int>  $userIds
     * Каунтер колонки «Абонементы» / flexible-context: остаток по consumes_lesson
     * ({@see UserLessonPackage::$lessons_remaining}), не по COUNT(utss).
     * Affordance постановки есть и при remaining = 0 (статусы без списания не лимитируются).
     *
     * @return array<int, list<array{
     *     id: int,
     *     name: string,
     *     team_id: int,
     *     team_title: string,
     *     lessons_total: int,
     *     slots_used: int,
     *     slots_remaining: int,
     *     fee_amount_cents: int,
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
            $teamId = (int) ($ulp->team_id ?? 0);
            if ($teamId < 1) {
                continue;
            }

            $remaining = max(0, (int) $ulp->lessons_remaining);
            $total = (int) $ulp->lessons_total;
            $userId = (int) $ulp->user_id;
            $used = max(0, $total - $remaining);

            $result[$userId][] = [
                'id' => (int) $ulp->id,
                'name' => (string) ($ulp->lessonPackage?->name ?? 'Гибкий абонемент'),
                'team_id' => $teamId,
                'team_title' => (string) ($ulp->team?->title ?? ''),
                'lessons_total' => $total,
                'slots_used' => $used,
                // Ключ исторический: значение = lessons_remaining (списание по consumes_lesson).
                'slots_remaining' => $remaining,
                'fee_amount_cents' => (int) ($ulp->fee_amount_cents ?? 0),
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
            ->with([
                'lessonOccurrenceStatus',
                'trainerProfile.user',
                'trainerProfiles.user',
            ])
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
                'price_cents' => (int) ($package->price_cents ?? 0),
            ];
        }

        return $map;
    }

    /**
     * Видимая подпись в колонке абонементов журнала для одного гибкого (две строки):
     * 10/12
     * Гибкий
     */
    public static function flexibleAbonementColumnLabel(int $slotsRemaining, int $lessonsTotal): string
    {
        return $slotsRemaining.'/'.$lessonsTotal."\nГибкий";
    }

    /**
     * Ховер колонки абонементов для гибкого:
     * «Остаток занятий в текущем месяце по абонементу "X" за Y руб»
     * или с префиксом остатка «10/12 остаток … по абонементу "X" за Y руб» (несколько ULP).
     * Цена 0 → «за 0 руб».
     */
    public static function flexibleAbonementColumnHoverLine(
        string $name,
        int $feeAmountCents,
        bool $withRatio = false,
        int $slotsRemaining = 0,
        int $lessonsTotal = 0,
    ): string {
        $trimmed = trim($name);
        if ($trimmed === '') {
            $trimmed = 'Гибкий абонемент';
        }

        $tail = ' по абонементу "'.$trimmed.'" за '.Money::formatRub($feeAmountCents, ' руб');

        if ($withRatio) {
            return $slotsRemaining.'/'.$lessonsTotal
                .' остаток занятий в текущем месяце'
                .$tail;
        }

        return 'Остаток занятий в текущем месяце'.$tail;
    }

    /**
     * Ховер кнопки «+» (разложить fixed): «Название абона (4/4) за 5 000 руб».
     */
    public static function fixedAbonementPlaceButtonHoverLine(
        string $name,
        int $lessonsRemaining,
        int $lessonsTotal,
        int $feeAmountCents,
    ): string {
        $trimmed = trim($name);
        if ($trimmed === '') {
            $trimmed = 'Абонемент';
        }

        return $trimmed
            .' ('.$lessonsRemaining.'/'.$lessonsTotal.')'
            .' за '.Money::formatRub($feeAmountCents, ' руб');
    }

    /**
     * Подпись для ховера ячейки журнала: «Название - цена».
     */
    public static function packageHoverLabel(string $name, ?int $feeAmountCents): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            $trimmed = 'Абонемент';
        }
        if ($feeAmountCents === null) {
            return $trimmed;
        }

        return $trimmed.' - '.Money::formatRub($feeAmountCents, ' руб');
    }

    /**
     * Ховер постоплаты в журнале: «Постоплата: 1200₽ в день».
     */
    public static function postpayPackageHoverLabel(?int $pricePerLessonCents): string
    {
        if ($pricePerLessonCents === null) {
            return 'Постоплата';
        }

        $amount = str_replace(' ', '', Money::formatRub($pricePerLessonCents));

        return 'Постоплата: '.$amount.'₽ в день';
    }

    /**
     * Строка тренера для ховера ячейки со статусом «Посетил».
     */
    public static function trainerHoverLine(?string $trainerName): string
    {
        $name = trim((string) $trainerName);

        return $name !== '' ? 'Тренер: '.$name : 'Тренер не выбран';
    }

    /**
     * Добавляет строку тренера к package_hover, если статус — «Посетил».
     */
    public static function appendTrainerToHover(string $packageHover, bool $isAttended, ?string $trainerName): string
    {
        if (! $isAttended) {
            return $packageHover;
        }

        $line = self::trainerHoverLine($trainerName);

        return $packageHover !== '' ? $packageHover."\n".$line : $line;
    }

    private function occurrenceKey(int $userId, int $slotId, string $date, ?int $ulpId): string
    {
        return $userId.'|'.$slotId.'|'.$date.'|'.($ulpId ?? 'trial');
    }
}
