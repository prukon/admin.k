<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamScheduleCalendarService;
use App\Services\UserLessonPackageCalendarPeriodService;
use App\Services\UserLessonPackageConsumptionAdjuster;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Разовое занятие в календаре школы: привязка существующего назначения, создание нового и отмена записи.
 */
final class SchoolCalendarSingleLessonRegistrationService
{
    public function __construct(
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly UserLessonPackageCalendarPeriodService $calendarPeriodService,
        private readonly SchoolCalendarAssignmentEligibilityService $assignmentEligibility,
    ) {
    }

    /**
     * @param  array{
     *   user_id: int,
     *   team_schedule_slot_id: int,
     *   occurrence_date: string,
     *   user_lesson_package_id?: int|null,
     *   lesson_package_id?: int|null,
     *   fee_amount?: float|null,
     * }  $data
     */
    public function register(int $partnerId, array $data, ?int $createdBy): void
    {
        $userId = (int) $data['user_id'];
        $slotId = (int) $data['team_schedule_slot_id'];
        $occurrence = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['occurrence_date'])->startOfDay();

        $slot = $this->resolveSlot($partnerId, $slotId);
        $this->assertSlotOccurrenceValid($slot, $occurrence);
        $this->assertCellFreeForUser($userId, $slot, $occurrence);

        if (isset($data['user_lesson_package_id']) && (int) $data['user_lesson_package_id'] > 0) {
            $ulp = $this->resolveBindableUlp($partnerId, $userId, (int) $data['user_lesson_package_id']);
            $this->bindUlpToSlot($partnerId, $ulp, $slot, $occurrence, $createdBy);

            return;
        }

        if (! isset($data['lesson_package_id']) || (int) $data['lesson_package_id'] < 1) {
            throw new InvalidArgumentException('Укажите назначение или шаблон разового занятия.');
        }

        $template = $this->resolveSingleLessonTemplate($partnerId, (int) $data['lesson_package_id']);
        $feeAmount = round((float) ($data['fee_amount'] ?? 0), 2);

        DB::transaction(function () use ($partnerId, $userId, $template, $feeAmount, $slot, $occurrence, $createdBy): void {
            /** @var UserLessonPackage $ulp */
            $ulp = UserLessonPackage::query()->create([
                'user_id' => $userId,
                'lesson_package_id' => (int) $template->id,
                'starts_at' => null,
                'ends_at' => null,
                'lessons_total' => (int) $template->lessons_count,
                'lessons_remaining' => (int) $template->lessons_count,
                'fee_amount' => $feeAmount,
                'is_paid' => false,
                'created_by' => $createdBy,
            ]);

            $this->bindUlpToSlot($partnerId, $ulp, $slot, $occurrence, $createdBy, false);
        });
    }

    public function bindUlpToSlot(
        int $partnerId,
        UserLessonPackage $ulp,
        TeamScheduleSlot $slot,
        CarbonImmutable $occurrence,
        ?int $createdBy,
        bool $useTransaction = true,
    ): void {
        $ulp->loadMissing(['user']);
        if ($ulp->relationLoaded('lessonPackage')) {
            $ulp->unsetRelation('lessonPackage');
        }
        $ulp->load('lessonPackage');

        $package = $ulp->lessonPackage;
        if (! $package || (string) $package->schedule_type !== 'no_schedule') {
            throw new InvalidArgumentException('Выберите назначение с типом «разовое занятие».');
        }

        if (! $ulp->user || (int) $ulp->user->partner_id !== $partnerId) {
            throw new InvalidArgumentException('Назначение не найдено или недоступно.');
        }

        $scheduledForUlp = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', (int) $ulp->id)
            ->count();
        if ($scheduledForUlp >= (int) $ulp->lessons_total) {
            $msg = (int) $ulp->lessons_total === 1
                ? 'Для этого назначения слот в календаре уже выбран. Оформите новое разовое занятие отдельным абонементом.'
                : 'Достигнут лимит занятий в календаре для этого абонемента.';

            throw new InvalidArgumentException($msg);
        }

        $bind = function () use ($ulp, $partnerId, $slot, $occurrence, $createdBy): void {
            $this->calendarPeriodService->applyFirstCalendarAnchor($ulp, $occurrence);
            $ulp->refresh();

            $ulpStart = Carbon::parse((string) $ulp->starts_at)->startOfDay();
            $ulpEnd = Carbon::parse((string) $ulp->ends_at)->endOfDay();
            if ($occurrence->lt($ulpStart) || $occurrence->gt($ulpEnd)) {
                throw new InvalidArgumentException('Дата выходит за пределы периода абонемента.');
            }

            UserTeamScheduleSlot::query()->create([
                'partner_id' => $partnerId,
                'user_id' => (int) $ulp->user_id,
                'user_lesson_package_id' => (int) $ulp->id,
                'team_schedule_slot_id' => (int) $slot->id,
                'starts_at' => $occurrence->toDateString(),
                'ends_at' => Carbon::parse((string) $ulp->ends_at)->format('Y-m-d'),
                'created_by' => $createdBy,
            ]);
        };

        if ($useTransaction) {
            DB::transaction($bind);
        } else {
            $bind();
        }
    }

    public function cancelRegistration(UserTeamScheduleSlot $userTeamScheduleSlot, int $partnerId, ?int $authorId): void
    {
        if ((int) $userTeamScheduleSlot->partner_id !== $partnerId
            || $userTeamScheduleSlot->is_trial_lesson
            || $userTeamScheduleSlot->user_lesson_package_id === null) {
            throw new InvalidArgumentException('Запись не найдена или не является разовым занятием.');
        }

        $userTeamScheduleSlot->loadMissing([
            'user:id,name,lastname',
            'userLessonPackage.lessonPackage:id,schedule_type,name',
            'slot:id,team_id,weekday,time_start,time_end',
            'slot.team:id,title',
        ]);

        $ulp = $userTeamScheduleSlot->userLessonPackage;
        $package = $ulp?->lessonPackage;
        if (! $ulp || ! $package || (string) $package->schedule_type !== 'no_schedule') {
            throw new InvalidArgumentException('Запись не найдена или не является разовым занятием.');
        }

        $bindId = (int) $userTeamScheduleSlot->id;
        $userId = (int) $userTeamScheduleSlot->user_id;
        $slotId = (int) $userTeamScheduleSlot->team_schedule_slot_id;
        $ulpId = (int) $userTeamScheduleSlot->user_lesson_package_id;
        $occurrenceDate = $userTeamScheduleSlot->starts_at?->format('Y-m-d') ?? (string) $userTeamScheduleSlot->starts_at;

        $userLabel = $userTeamScheduleSlot->user
            ? trim(($userTeamScheduleSlot->user->lastname ?? '').' '.($userTeamScheduleSlot->user->name ?? ''))
            : ('Ученик #'.$userId);
        if ($userLabel === '') {
            $userLabel = 'Ученик #'.$userId;
        }

        $teamTitle = (string) ($userTeamScheduleSlot->slot?->team?->title ?? '');
        $timeStart = substr((string) ($userTeamScheduleSlot->slot?->time_start ?? ''), 0, 5);
        $timeEnd = substr((string) ($userTeamScheduleSlot->slot?->time_end ?? ''), 0, 5);
        $timeLabel = trim($timeStart.($timeStart && $timeEnd ? '–' : '').$timeEnd);
        $packageName = trim((string) ($package->name ?? 'Разовое занятие'));

        DB::transaction(function () use (
            $partnerId,
            $userId,
            $slotId,
            $ulpId,
            $occurrenceDate,
            $bindId,
            $userLabel,
            $teamTitle,
            $timeLabel,
            $packageName,
            $ulp,
            $userTeamScheduleSlot,
            $authorId,
        ): void {
            /** @var UserLessonOccurrenceStatusEvent|null $latestEvent */
            $latestEvent = UserLessonOccurrenceStatusEvent::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('team_schedule_slot_id', $slotId)
                ->whereDate('occurrence_date', $occurrenceDate)
                ->where('user_lesson_package_id', $ulpId)
                ->orderByDesc('id')
                ->first();

            if ($latestEvent !== null) {
                $latestEvent->loadMissing('lessonOccurrenceStatus:id,consumes_lesson');
                $consumed = (bool) ($latestEvent->lessonOccurrenceStatus?->consumes_lesson ?? false);
                if ($consumed) {
                    UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, 1);
                }
            }

            UserLessonOccurrenceStatusEvent::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('team_schedule_slot_id', $slotId)
                ->whereDate('occurrence_date', $occurrenceDate)
                ->where('user_lesson_package_id', $ulpId)
                ->delete();

            $userTeamScheduleSlot->delete();

            $slotPart = $teamTitle !== '' ? ('; группа: '.$teamTitle) : '';
            $whenPart = $occurrenceDate !== '' ? ('; дата: '.$occurrenceDate) : '';
            $timePart = $timeLabel !== '' ? (' '.$timeLabel) : '';

            MyLog::query()->create([
                'type' => 60,
                'action' => 602,
                'author_id' => $authorId,
                'partner_id' => $partnerId,
                'user_id' => $userId,
                'description' => 'Отменена запись разового занятия в расписании; ученик: '.$userLabel.$slotPart.$whenPart.$timePart,
                'target_type' => UserTeamScheduleSlot::class,
                'target_id' => $bindId,
                'target_label' => $userLabel.', '.$packageName.', '.$occurrenceDate.($timeLabel !== '' ? (' '.$timeLabel) : ''),
            ]);
        });
    }

    private function resolveSlot(int $partnerId, int $slotId): TeamScheduleSlot
    {
        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey($slotId)->first();

        if (! $slot || (int) $slot->partner_id !== $partnerId) {
            throw new InvalidArgumentException('Слот расписания не найден.');
        }

        return $slot;
    }

    private function assertSlotOccurrenceValid(TeamScheduleSlot $slot, CarbonImmutable $occurrence): void
    {
        if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
            throw new InvalidArgumentException('Дата не соответствует дню недели выбранного слота.');
        }

        if (! $this->calendarService->slotActiveOnDate($slot, $occurrence)) {
            throw new InvalidArgumentException('Слот недействителен на выбранную дату.');
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $occurrence)) {
            throw new InvalidArgumentException('На эту дату занятие исключено из расписания школы.');
        }
    }

    private function assertCellFreeForUser(int $userId, TeamScheduleSlot $slot, CarbonImmutable $occurrence): void
    {
        $exists = UserTeamScheduleSlot::query()
            ->where('user_id', $userId)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', $occurrence->toDateString())
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('На это занятие у ученика уже есть запись в календаре на выбранную дату.');
        }
    }

    private function resolveBindableUlp(int $partnerId, int $userId, int $ulpId): UserLessonPackage
    {
        /** @var UserLessonPackage|null $ulp */
        $ulp = $this->assignmentEligibility
            ->singleLessonAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->whereKey($ulpId)
            ->first();

        if (! $ulp) {
            throw new InvalidArgumentException('Выберите свободное назначение разового занятия.');
        }

        return $ulp;
    }

    private function resolveSingleLessonTemplate(int $partnerId, int $lessonPackageId): LessonPackage
    {
        /** @var LessonPackage|null $template */
        $template = $this->assignmentEligibility
            ->singleLessonTemplatesQuery($partnerId)
            ->whereKey($lessonPackageId)
            ->first();

        if (! $template) {
            throw new InvalidArgumentException('Шаблон разового занятия не найден или недоступен.');
        }

        return $template;
    }
}
