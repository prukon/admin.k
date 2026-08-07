<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use App\Services\UserLessonPackageCalendarPeriodService;
use App\Support\UserPriceTeamMembership;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Одно занятие из месячного гибкого абонемента (установка цен) в журнал /schedule.
 */
final class JournalFlexibleAbonementPlacementService
{
    public function __construct(
        private readonly JournalTeamScheduleSlotEnsureService $slotEnsure,
        private readonly UserLessonPackageCalendarPeriodService $calendarPeriodService,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
    ) {
    }

    /**
     * @return array{
     *     utss_id: int,
     *     user_lesson_package_id: int,
     *     occurrence_date: string,
     *     slots_remaining: int,
     *     lessons_total: int,
     *     comment: string|null,
     *     status: array{id: int, title: string, icon: string|null, color: string|null}
     * }
     */
    public function place(
        int $partnerId,
        User $user,
        UserLessonPackage $ulp,
        Team $team,
        CarbonImmutable $occurrenceDate,
        LessonOccurrenceStatus $status,
        ?int $createdByUserId,
        ?int $trainerProfileId = null,
        ?string $comment = null,
    ): array {
        $this->assertUserAndTeam($partnerId, $user, $team);
        $this->assertFlexibleAssignable($partnerId, $user, $ulp, $team, $occurrenceDate, $status);

        if ((int) $status->partner_id !== $partnerId || ! $status->is_active) {
            throw new InvalidArgumentException('Выбранный статус не найден или неактивен.');
        }

        $occurrenceYmd = $occurrenceDate->toDateString();
        $utssId = 0;
        $commentValue = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        DB::transaction(function () use (
            $partnerId,
            $user,
            $ulp,
            $team,
            $occurrenceDate,
            $occurrenceYmd,
            $createdByUserId,
            $status,
            $trainerProfileId,
            $commentValue,
            &$utssId,
        ): void {
            // Период у месячного flexible уже полный; метод безопасен (early return).
            $this->calendarPeriodService->applyFirstCalendarAnchor($ulp, $occurrenceDate);
            $ulp->refresh();

            if ($ulp->starts_at === null || $ulp->ends_at === null) {
                throw new InvalidArgumentException('У назначения не задан период действия.');
            }

            $ulpStart = CarbonImmutable::parse($ulp->starts_at->format('Y-m-d'))->startOfDay();
            $ulpEnd = CarbonImmutable::parse($ulp->ends_at->format('Y-m-d'))->startOfDay();
            if ($occurrenceDate->lt($ulpStart) || $occurrenceDate->gt($ulpEnd)) {
                throw new InvalidArgumentException(sprintf(
                    'Дата выходит за пределы периода абонемента (%s–%s).',
                    $ulpStart->format('d.m.Y'),
                    $ulpEnd->format('d.m.Y')
                ));
            }

            $weekday = (int) $occurrenceDate->format('N');
            $slot = $this->slotEnsure->resolveOrCreateNextFreeForUserDate(
                $partnerId,
                (int) $team->id,
                $weekday,
                (int) $user->id,
                $occurrenceYmd,
            );

            $periodEndYmd = $ulpEnd->toDateString();

            $utss = UserTeamScheduleSlot::query()->create([
                'partner_id' => $partnerId,
                'user_id' => (int) $user->id,
                'user_lesson_package_id' => (int) $ulp->id,
                'team_schedule_slot_id' => (int) $slot->id,
                'starts_at' => $occurrenceYmd,
                'ends_at' => $periodEndYmd,
                'created_by' => $createdByUserId,
                'is_trial_lesson' => false,
            ]);

            $this->occurrenceStatusService->apply(
                $partnerId,
                (int) $user->id,
                (int) $slot->id,
                $occurrenceYmd,
                (int) $ulp->id,
                $status,
                $createdByUserId,
                $trainerProfileId,
                $commentValue,
            );

            $utssId = (int) $utss->id;
        });

        $ulp->refresh();
        $ulp->loadMissing('lessonPackage:id,name');
        $packageName = trim((string) ($ulp->lessonPackage?->name ?? ''));

        return [
            'utss_id' => $utssId,
            'user_lesson_package_id' => (int) $ulp->id,
            'occurrence_date' => $occurrenceYmd,
            'is_flexible' => true,
            // Остаток по consumes_lesson (не COUNT utss).
            'slots_remaining' => max(0, (int) $ulp->lessons_remaining),
            'lessons_total' => (int) $ulp->lessons_total,
            'fee_amount_cents' => (int) ($ulp->fee_amount_cents ?? 0),
            'comment' => $commentValue,
            'package_name' => $packageName !== '' ? $packageName : 'Гибкий абонемент',
            'package_hover' => ScheduleJournalMonthService::packageHoverLabel(
                $packageName !== '' ? $packageName : 'Гибкий абонемент',
                (int) ($ulp->fee_amount_cents ?? 0),
            ),
            'status' => [
                'id' => (int) $status->id,
                'title' => (string) $status->title,
                'icon' => $status->icon !== null && $status->icon !== '' ? (string) $status->icon : null,
                'color' => $status->color !== null && $status->color !== '' ? (string) $status->color : null,
            ],
        ];
    }

    private function assertUserAndTeam(int $partnerId, User $user, Team $team): void
    {
        if ((int) $user->partner_id !== $partnerId || ! (bool) $user->is_enabled) {
            throw new InvalidArgumentException('Ученик не найден.');
        }
        if ((int) $team->partner_id !== $partnerId || $team->deleted_at !== null) {
            throw new InvalidArgumentException('Группа не найдена.');
        }

        if (! UserPriceTeamMembership::studentBelongsToTeam($user, (int) $team->id, $partnerId)) {
            throw new InvalidArgumentException('Ученик не состоит в выбранной группе.');
        }
    }

    private function assertFlexibleAssignable(
        int $partnerId,
        User $user,
        UserLessonPackage $ulp,
        Team $team,
        CarbonImmutable $occurrenceDate,
        LessonOccurrenceStatus $status,
    ): void {
        if ((int) $ulp->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Назначение не принадлежит ученику.');
        }

        $ulp->loadMissing('lessonPackage:id,name,schedule_type,partner_id');

        /** @var LessonPackage|null $package */
        $package = $ulp->lessonPackage;
        if (
            ! $package
            || (int) $package->partner_id !== $partnerId
            || (string) $package->schedule_type !== LessonPackage::SCHEDULE_TYPE_FLEXIBLE
        ) {
            throw new InvalidArgumentException('Доступна привязка только для гибкого абонемента.');
        }

        if (! $ulp->isFromSettingPrices() || $ulp->billing_month === null) {
            throw new InvalidArgumentException(
                'Из журнала можно ставить занятия только для гибкого абонемента из установки цен.'
            );
        }

        $billingMonth = Carbon::parse($ulp->billing_month->format('Y-m-d'))->startOfMonth();
        $occurrenceMonth = $occurrenceDate->startOfMonth();
        if (! $billingMonth->equalTo($occurrenceMonth)) {
            throw new InvalidArgumentException('Дата не входит в месяц начисления абонемента.');
        }

        if ((int) ($ulp->team_id ?? 0) !== (int) $team->id) {
            throw new InvalidArgumentException('Группа не соответствует назначению абонемента.');
        }

        if ((int) $ulp->lessons_total < 1) {
            throw new InvalidArgumentException('У абонемента не задан объём занятий.');
        }

        // Лимит только для статусов со списанием; без consumes_lesson — без потолка по числу UTSS.
        if ((bool) $status->consumes_lesson && (int) $ulp->lessons_remaining < 1) {
            throw new InvalidArgumentException('Достигнут лимит занятий для этого абонемента.');
        }

        try {
            $this->calendarPeriodService->assertAnchorInsideBillingMonth($ulp, $occurrenceDate);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }
}
