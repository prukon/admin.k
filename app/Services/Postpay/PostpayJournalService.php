<?php

declare(strict_types=1);

namespace App\Services\Postpay;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\JournalTeamScheduleSlotEnsureService;
use App\Support\UserPriceTeamMembership;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Журнал: активная постоплата по users_prices, создание UTSS без ULP, лок после оплаты.
 */
final class PostpayJournalService
{
    public const LOCKED_MESSAGE = 'Изменить данные нельзя, поскольку уже была произведена оплата';

    public function __construct(
        private readonly JournalTeamScheduleSlotEnsureService $slotEnsure,
    ) {
    }

    public function findPostpayUserPrice(int $userId, int $teamId, string $dateYmd): ?UserPrice
    {
        $month = PostpayMonth::firstDayFromDate($dateYmd);

        /** @var UserPrice|null $row */
        $row = UserPrice::query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereDate('new_month', $month)
            ->whereNotNull('lesson_package_id')
            ->with('lessonPackage')
            ->first();

        if (! $row || ! $row->lessonPackage || ! $row->lessonPackage->isPostpay()) {
            return null;
        }

        return $row;
    }

    /**
     * Выбрать group team_id для отметки постоплаты (приоритет: preferred, иначе первая postpay-строка месяца).
     */
    public function resolvePostpayTeamId(int $userId, string $dateYmd, ?int $preferredTeamId = null): ?int
    {
        $month = PostpayMonth::firstDayFromDate($dateYmd);

        if ($preferredTeamId !== null && $preferredTeamId > 0) {
            if ($this->findPostpayUserPrice($userId, $preferredTeamId, $dateYmd)) {
                return $preferredTeamId;
            }
        }

        $row = UserPrice::query()
            ->where('user_id', $userId)
            ->whereDate('new_month', $month)
            ->whereNotNull('lesson_package_id')
            ->whereHas('lessonPackage', static function ($q) {
                $q->where('schedule_type', LessonPackage::SCHEDULE_TYPE_POSTPAY);
            })
            ->orderBy('id')
            ->first();

        return $row ? (int) $row->team_id : null;
    }

    /**
     * Есть ли у ученика postpay на месяц даты хотя бы в одной из групп (или в конкретной).
     *
     * @param  list<int>|null  $teamIds
     */
    /**
     * Группы ученика с активной постоплатой на месяц даты.
     *
     * @return list<array{id: int, title: string}>
     */
    public function postpayTeamsForDate(int $userId, string $dateYmd): array
    {
        $month = PostpayMonth::firstDayFromDate($dateYmd);

        $rows = UserPrice::query()
            ->where('user_id', $userId)
            ->whereDate('new_month', $month)
            ->whereNotNull('lesson_package_id')
            ->whereHas('lessonPackage', static function ($q) {
                $q->where('schedule_type', LessonPackage::SCHEDULE_TYPE_POSTPAY);
            })
            ->with(['team:id,title'])
            ->orderBy('id')
            ->get();

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $teamId = (int) $row->team_id;
            if ($teamId <= 0 || isset($seen[$teamId])) {
                continue;
            }
            $seen[$teamId] = true;
            $out[] = [
                'id' => $teamId,
                'title' => (string) ($row->team?->title ?: ('Группа #'.$teamId)),
            ];
        }

        return $out;
    }

    public function hasPostpayForDate(int $userId, string $dateYmd, ?array $teamIds = null): bool
    {
        $month = PostpayMonth::firstDayFromDate($dateYmd);

        $query = UserPrice::query()
            ->where('user_id', $userId)
            ->whereDate('new_month', $month)
            ->whereNotNull('lesson_package_id')
            ->whereHas('lessonPackage', static function ($q) {
                $q->where('schedule_type', LessonPackage::SCHEDULE_TYPE_POSTPAY);
            });

        if ($teamIds !== null) {
            if ($teamIds === []) {
                return false;
            }
            $query->whereIn('team_id', $teamIds);
        }

        return $query->exists();
    }

    /**
     * Map user_id => true для учеников с postpay в выбранном месяце (опционально фильтр группы журнала).
     *
     * @param  list<int>  $userIds
     * @return array<int, true>
     */
    public function postpayUserFlags(array $userIds, string $monthFirstDayYmd, string $teamFilter): array
    {
        if ($userIds === []) {
            return [];
        }

        $month = PostpayMonth::firstDayFromDate($monthFirstDayYmd);

        $query = UserPrice::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('new_month', $month)
            ->whereNotNull('lesson_package_id')
            ->whereHas('lessonPackage', static function ($q) {
                $q->where('schedule_type', LessonPackage::SCHEDULE_TYPE_POSTPAY);
            });

        if ($teamFilter !== 'all' && $teamFilter !== 'none' && is_numeric($teamFilter)) {
            $query->where('team_id', (int) $teamFilter);
        }

        $flags = [];
        foreach ($query->pluck('user_id') as $uid) {
            $flags[(int) $uid] = true;
        }

        return $flags;
    }

    /**
     * Map "userId_Y-m-d" => true когда день месяца залочен оплатой postpay.
     *
     * @param  list<int>  $userIds
     * @return array<int, bool> user_id => locked for the viewed month
     */
    public function postpayLockedUserFlags(array $userIds, string $monthFirstDayYmd, string $teamFilter): array
    {
        if ($userIds === []) {
            return [];
        }

        $month = PostpayMonth::firstDayFromDate($monthFirstDayYmd);

        $query = UserPrice::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('new_month', $month)
            ->whereNotNull('lesson_package_id')
            ->with('lessonPackage');

        if ($teamFilter !== 'all' && $teamFilter !== 'none' && is_numeric($teamFilter)) {
            $query->where('team_id', (int) $teamFilter);
        }

        $flags = [];
        foreach ($query->get() as $row) {
            if (! $row->lessonPackage || ! $row->lessonPackage->isPostpay()) {
                continue;
            }
            if ($row->effective_is_paid) {
                $flags[(int) $row->user_id] = true;
            }
        }

        return $flags;
    }

    public function assertNotLocked(UserPrice $row): void
    {
        if ($row->effective_is_paid) {
            throw new DomainException(self::LOCKED_MESSAGE);
        }
    }

    /**
     * Найти или создать UTSS без ULP для дневной отметки постоплаты.
     */
    public function ensureOccurrence(
        int $partnerId,
        User $user,
        int $teamId,
        string $occurrenceDateYmd,
        ?int $createdByUserId
    ): UserTeamScheduleSlot {
        $row = $this->findPostpayUserPrice((int) $user->id, $teamId, $occurrenceDateYmd);
        if (! $row) {
            throw new InvalidArgumentException('Для ученика не назначена постоплата на этот месяц в выбранной группе.');
        }

        $this->assertNotLocked($row);

        if (! UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
            throw new InvalidArgumentException('Ученик не состоит в выбранной группе.');
        }

        $weekday = (int) Carbon::parse($occurrenceDateYmd, PostpayMonth::TIMEZONE)->dayOfWeekIso;
        $coverDay = CarbonImmutable::parse($occurrenceDateYmd, PostpayMonth::TIMEZONE)->startOfDay();
        $slot = $this->slotEnsure->resolveOrCreateEarliestForWeekday(
            $partnerId,
            $teamId,
            $weekday,
            $coverDay,
            $coverDay,
        );

        /** @var UserTeamScheduleSlot|null $existing */
        $existing = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', (int) $user->id)
            ->where('team_schedule_slot_id', (int) $slot->id)
            ->whereDate('starts_at', $occurrenceDateYmd)
            ->whereNull('user_lesson_package_id')
            ->where('is_trial_lesson', false)
            ->first();

        if ($existing) {
            return $existing;
        }

        return UserTeamScheduleSlot::query()->create([
            'partner_id' => $partnerId,
            'user_id' => (int) $user->id,
            'team_schedule_slot_id' => (int) $slot->id,
            'user_lesson_package_id' => null,
            'is_trial_lesson' => false,
            'trial_lessons_remaining' => null,
            'trial_lessons_total' => null,
            'starts_at' => $occurrenceDateYmd,
            'ends_at' => $occurrenceDateYmd,
            'created_by' => $createdByUserId,
        ]);
    }

    /**
     * Проверка лока перед сменой статуса существующего UTSS (если день/группа под postpay).
     */
    public function assertOccurrenceEditable(int $userId, int $teamId, string $occurrenceDateYmd): void
    {
        $row = $this->findPostpayUserPrice($userId, $teamId, $occurrenceDateYmd);
        if ($row) {
            $this->assertNotLocked($row);
        }
    }
}
