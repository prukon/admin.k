<?php

declare(strict_types=1);

namespace App\Services\Postpay;

use App\Models\LessonPackage;
use App\Models\UserPrice;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Расчёт суммы постоплаты: визиты × price_cents шаблона (результат в копейках).
 */
final class PostpayAmountCalculator
{
    /**
     * @return array{visits: int, amount_cents: int, price_per_lesson_cents: int}
     */
    public function forUserPrice(UserPrice $row, ?LessonPackage $package = null): array
    {
        $package = $package ?? $row->lessonPackage;
        $pricePerLessonCents = $package ? (int) $package->price_cents : 0;
        $partnerId = (int) ($package?->partner_id ?? 0);

        if ($partnerId <= 0 && $row->relationLoaded('user') && $row->user) {
            $partnerId = (int) $row->user->partner_id;
        }

        if ($partnerId <= 0) {
            $partnerId = (int) (DB::table('users')->where('id', (int) $row->user_id)->value('partner_id') ?? 0);
        }

        $month = PostpayMonth::firstDayFromDate((string) $row->new_month);
        $visits = $partnerId > 0
            ? $this->countConsumingVisits(
                $partnerId,
                (int) $row->user_id,
                (int) $row->team_id,
                $month
            )
            : 0;

        $grossCents = $visits * $pricePerLessonCents;
        $percent = (int) ($row->discount_percent ?? 0);
        $amountCents = $percent >= 1
            ? Money::payableAfterDiscountCents($grossCents, $percent)
            : $grossCents;

        return [
            'visits' => $visits,
            'amount_cents' => $amountCents,
            'price_per_lesson_cents' => $pricePerLessonCents,
        ];
    }

    /**
     * Число актуальных (последних) статусов с consumes_lesson=true за месяц в группе.
     */
    public function countConsumingVisits(int $partnerId, int $userId, int $teamId, string $monthFirstDayYmd): int
    {
        [$start, $end] = PostpayMonth::bounds($monthFirstDayYmd);
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');

        $latestIds = DB::table('user_lesson_occurrence_status_events as e')
            ->join('team_schedule_slots as tss', 'tss.id', '=', 'e.team_schedule_slot_id')
            ->where('e.partner_id', $partnerId)
            ->where('e.user_id', $userId)
            ->where('tss.team_id', $teamId)
            ->whereDate('e.occurrence_date', '>=', $from)
            ->whereDate('e.occurrence_date', '<=', $to)
            ->groupBy(
                'e.user_id',
                'e.team_schedule_slot_id',
                'e.occurrence_date',
                'e.user_lesson_package_id'
            )
            ->selectRaw('MAX(e.id) as id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return 0;
        }

        return (int) DB::table('user_lesson_occurrence_status_events as e')
            ->join('lesson_occurrence_statuses as s', 's.id', '=', 'e.lesson_occurrence_status_id')
            ->whereIn('e.id', $latestIds->all())
            ->where('s.consumes_lesson', true)
            ->count();
    }
}
