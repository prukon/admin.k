<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\AssignSchoolCalendarFixedRequest;
use App\Http\Requests\Admin\AssignSchoolCalendarFlexibleRequest;
use App\Http\Requests\Admin\AssignSchoolCalendarSingleLessonRequest;
use App\Http\Requests\Admin\AssignSchoolCalendarTrialRequest;
use App\Models\LessonPackage;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\SchoolCalendarSlotUserBindActionsService;
use App\Services\LessonPackages\SchoolCalendarTrialEligibilityService;
use App\Services\PartnerContext;
use App\Services\TeamScheduleCalendarService;
use App\Services\UserLessonPackageCalendarPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LessonPackageSchoolCalendarAssignmentController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly UserLessonPackageCalendarPeriodService $calendarPeriodService,
        private readonly SchoolCalendarTrialEligibilityService $trialEligibilityService,
        private readonly SchoolCalendarSlotUserBindActionsService $slotUserBindActionsService,
    ) {
        parent::__construct($partnerContext);
    }

    /**
     * Доступность кнопок привязки в модалке слота для выбранного ученика и даты.
     */
    public function slotUserBindActions(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $userId = (int) $request->query('user_id', 0);
        $slotId = (int) $request->query('team_schedule_slot_id', 0);
        $occurrenceDate = trim((string) $request->query('occurrence_date', ''));

        if ($userId < 1 || $slotId < 1 || $occurrenceDate === '') {
            $r = 'Укажите ученика, слот и дату.';

            return response()->json([
                'flexible' => ['allowed' => false, 'reason' => $r],
                'fixed' => ['allowed' => false, 'reason' => $r],
                'single_lesson' => ['allowed' => false, 'reason' => $r],
                'trial' => ['allowed' => false, 'reason' => $r],
            ]);
        }

        $payload = $this->slotUserBindActionsService->evaluate($partnerId, $userId, $slotId, $occurrenceDate);

        return response()->json($payload);
    }

    public function assignFlexible(AssignSchoolCalendarFlexibleRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        /** @var UserLessonPackage|null $ulp */
        $ulp = UserLessonPackage::query()
            ->with(['lessonPackage', 'user'])
            ->whereKey((int) $data['user_lesson_package_id'])
            ->first();

        if (! $ulp || ! $ulp->user || (int) $ulp->user->partner_id !== $partnerId) {
            return response()->json([
                'message' => 'Назначение не найдено или недоступно.',
                'errors' => ['user_lesson_package_id' => ['Назначение не найдено или недоступно.']],
            ], 422);
        }

        $package = $ulp->lessonPackage;
        if (! $package || (string) $package->schedule_type !== 'flexible') {
            return response()->json([
                'message' => 'Привязка из календаря доступна только для абонементов с гибким расписанием.',
                'errors' => ['user_lesson_package_id' => ['Выберите назначение с типом «гибкое расписание».']],
            ], 422);
        }

        if ((int) $ulp->lessons_remaining < 1) {
            return response()->json([
                'message' => 'На абонементе не осталось занятий.',
                'errors' => ['user_lesson_package_id' => ['На абонементе не осталось занятий.']],
            ], 422);
        }

        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey((int) $data['team_schedule_slot_id'])->first();

        if (! $slot || (int) $slot->partner_id !== $partnerId) {
            return response()->json([
                'message' => 'Слот расписания не найден.',
                'errors' => ['team_schedule_slot_id' => ['Слот расписания не найден.']],
            ], 422);
        }

        $occurrence = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['occurrence_date'])->startOfDay();

        if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
            return response()->json([
                'message' => 'Дата не соответствует дню недели выбранного слота.',
                'errors' => ['occurrence_date' => ['Дата не соответствует дню недели выбранного слота.']],
            ], 422);
        }

        if (! $this->calendarService->slotActiveOnDate($slot, $occurrence)) {
            return response()->json([
                'message' => 'Слот недействителен на выбранную дату.',
                'errors' => ['occurrence_date' => ['Слот недействителен на выбранную дату.']],
            ], 422);
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $occurrence)) {
            return response()->json([
                'message' => 'На эту дату занятие исключено из расписания школы.',
                'errors' => ['occurrence_date' => ['На эту дату занятие исключено из расписания школы.']],
            ], 422);
        }

        $exists = UserTeamScheduleSlot::query()
            ->where('user_id', $ulp->user_id)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', $occurrence->toDateString())
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Это занятие уже привязано к ученику.',
                'errors' => ['occurrence_date' => ['Это занятие уже привязано к ученику.']],
            ], 422);
        }

        try {
            DB::transaction(function () use ($ulp, $partnerId, $slot, $occurrence) {
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
                    'created_by' => auth()->id(),
                ]);

                $ulp->lessons_remaining = max(0, (int) $ulp->lessons_remaining - 1);
                $ulp->save();
            });
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['occurrence_date' => [$e->getMessage()]],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось сохранить привязку. Попробуйте ещё раз.',
                'errors' => ['occurrence_date' => ['Не удалось сохранить привязку.']],
            ], 422);
        }

        return response()->json(['message' => 'Занятие привязано к абонементу.']);
    }

    /**
     * Разовое занятие (no_schedule): одна запись в расписании школы на одно назначение.
     */
    public function assignSingleLesson(AssignSchoolCalendarSingleLessonRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        /** @var UserLessonPackage|null $ulp */
        $ulp = UserLessonPackage::query()
            ->with(['lessonPackage', 'user'])
            ->whereKey((int) $data['user_lesson_package_id'])
            ->first();

        if (! $ulp || ! $ulp->user || (int) $ulp->user->partner_id !== $partnerId) {
            return response()->json([
                'message' => 'Назначение не найдено или недоступно.',
                'errors' => ['user_lesson_package_id' => ['Назначение не найдено или недоступно.']],
            ], 422);
        }

        $package = $ulp->lessonPackage;
        if (! $package || (string) $package->schedule_type !== 'no_schedule') {
            return response()->json([
                'message' => 'Выберите назначение с типом «разовое занятие».',
                'errors' => ['user_lesson_package_id' => ['Назначение не относится к разовому занятию.']],
            ], 422);
        }

        if (UserTeamScheduleSlot::query()->where('user_lesson_package_id', (int) $ulp->id)->exists()) {
            return response()->json([
                'message' => 'Это разовое занятие уже записано в расписание школы.',
                'errors' => ['user_lesson_package_id' => ['Для этого назначения слот в календаре уже выбран. Оформите новое разовое занятие отдельным абонементом.']],
            ], 422);
        }

        if ((int) $ulp->lessons_remaining < 1) {
            return response()->json([
                'message' => 'На абонементе не осталось занятий.',
                'errors' => ['user_lesson_package_id' => ['На абонементе не осталось занятий.']],
            ], 422);
        }

        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey((int) $data['team_schedule_slot_id'])->first();

        if (! $slot || (int) $slot->partner_id !== $partnerId) {
            return response()->json([
                'message' => 'Слот расписания не найден.',
                'errors' => ['team_schedule_slot_id' => ['Слот расписания не найден.']],
            ], 422);
        }

        $occurrence = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['occurrence_date'])->startOfDay();

        if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
            return response()->json([
                'message' => 'Дата не соответствует дню недели выбранного слота.',
                'errors' => ['occurrence_date' => ['Дата не соответствует дню недели выбранного слота.']],
            ], 422);
        }

        if (! $this->calendarService->slotActiveOnDate($slot, $occurrence)) {
            return response()->json([
                'message' => 'Слот недействителен на выбранную дату.',
                'errors' => ['occurrence_date' => ['Слот недействителен на выбранную дату.']],
            ], 422);
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $occurrence)) {
            return response()->json([
                'message' => 'На эту дату занятие исключено из расписания школы.',
                'errors' => ['occurrence_date' => ['На эту дату занятие исключено из расписания школы.']],
            ], 422);
        }

        $exists = UserTeamScheduleSlot::query()
            ->where('user_id', $ulp->user_id)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', $occurrence->toDateString())
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Это занятие уже привязано к ученику.',
                'errors' => ['occurrence_date' => ['Это занятие уже привязано к ученику.']],
            ], 422);
        }

        try {
            DB::transaction(function () use ($ulp, $partnerId, $slot, $occurrence) {
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
                    'created_by' => auth()->id(),
                ]);

                $ulp->lessons_remaining = max(0, (int) $ulp->lessons_remaining - 1);
                $ulp->save();
            });
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['occurrence_date' => [$e->getMessage()]],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось сохранить привязку. Попробуйте ещё раз.',
                'errors' => ['occurrence_date' => ['Не удалось сохранить привязку.']],
            ], 422);
        }

        return response()->json(['message' => 'Разовое занятие записано в расписание.']);
    }

    public function assignFixed(AssignSchoolCalendarFixedRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::query()
            ->where('partner_id', $partnerId)
            ->whereKey((int) $data['user_id'])
            ->first();

        if (! $user) {
            return response()->json([
                'errors' => ['user_id' => ['Ученик не найден.']],
            ], 422);
        }

        /** @var UserLessonPackage|null $ulp */
        $ulp = UserLessonPackage::query()
            ->with(['lessonPackage'])
            ->whereKey((int) $data['user_lesson_package_id'])
            ->first();

        if (! $ulp || (int) $ulp->user_id !== (int) $user->id) {
            return response()->json([
                'errors' => ['user_lesson_package_id' => ['Назначение не найдено или не принадлежит выбранному ученику.']],
            ], 422);
        }

        /** @var LessonPackage|null $package */
        $package = $ulp->lessonPackage;
        if (! $package || (int) $package->partner_id !== $partnerId || (string) $package->schedule_type !== 'fixed') {
            return response()->json([
                'errors' => ['user_lesson_package_id' => ['Выберите назначение с фиксированным расписанием текущего партнёра.']],
            ], 422);
        }

        if ($ulp->starts_at !== null || $ulp->ends_at !== null) {
            return response()->json([
                'message' => 'У этого назначения уже задан период действия.',
                'errors' => ['user_lesson_package_id' => ['Назначение уже имеет даты периода — повторная привязка недоступна.']],
            ], 422);
        }

        if (UserTeamScheduleSlot::query()->where('user_lesson_package_id', (int) $ulp->id)->exists()) {
            return response()->json([
                'errors' => ['user_lesson_package_id' => ['У назначения уже есть записи в расписании школы.']],
            ], 422);
        }

        if ((int) $ulp->lessons_remaining < 1) {
            return response()->json([
                'message' => 'На абонементе не осталось занятий.',
                'errors' => ['user_lesson_package_id' => ['На абонементе не осталось занятий.']],
            ], 422);
        }

        /** @var TeamScheduleSlot|null $anchorSlot */
        $anchorSlot = TeamScheduleSlot::query()->whereKey((int) $data['team_schedule_slot_id'])->first();

        if (! $anchorSlot || (int) $anchorSlot->partner_id !== $partnerId) {
            return response()->json([
                'errors' => ['team_schedule_slot_id' => ['Слот расписания не найден.']],
            ], 422);
        }

        $locationFilter = isset($data['location_id']) ? (int) $data['location_id'] : null;
        if ($locationFilter === 0) {
            $locationFilter = null;
        }

        if ($locationFilter !== null && $locationFilter > 0
            && (int) ($anchorSlot->location_id ?? 0) !== $locationFilter) {
            return response()->json([
                'errors' => ['location_id' => ['Выбранный слот не относится к этой локации.']],
            ], 422);
        }

        $effectiveLocationFilter = $locationFilter;
        if ($effectiveLocationFilter === null || $effectiveLocationFilter === 0) {
            $lid = $anchorSlot->location_id;
            $effectiveLocationFilter = $lid !== null ? (int) $lid : null;
        }

        $anchorDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['anchor_date'])->startOfDay();

        $patterns = $this->dedupeFixedPatternsFromValidated($data['patterns'] ?? []);
        if ($patterns->isEmpty()) {
            return response()->json([
                'message' => 'Укажите хотя бы один слот шаблона привязки.',
                'errors' => ['patterns' => ['Укажите хотя бы один слот шаблона привязки.']],
            ], 422);
        }

        if ((int) $anchorSlot->weekday !== (int) $anchorDate->format('N')) {
            return response()->json([
                'message' => 'Дата якоря не соответствует дню недели выбранного слота.',
                'errors' => ['anchor_date' => ['Дата якоря не соответствует дню недели выбранного слота.']],
            ], 422);
        }

        if (! $this->calendarService->patternMatchesSlot($patterns, $anchorSlot, (int) $anchorDate->format('N'))) {
            return response()->json([
                'message' => 'Шаблон привязки должен включать слот занятия, на которое вы кликнули (день недели и время).',
                'errors' => [
                    'patterns' => ['Добавьте в шаблон строку с днём недели и временем слота, с которого открыто окно.'],
                ],
            ], 422);
        }

        /** @var object{weekday: int, time_start: string, time_end: string}|null $matchingPattern */
        $matchingPattern = $patterns->first(function (object $p) use ($anchorSlot, $anchorDate): bool {
            return $this->calendarService->patternEqualsSlot($p, $anchorSlot, (int) $anchorDate->format('N'));
        });

        if ($matchingPattern === null) {
            return response()->json([
                'errors' => ['patterns' => ['Не удалось сопоставить шаблон с выбранным слотом.']],
            ], 422);
        }

        $resolvedAnchorSlot = $this->calendarService->findMatchingTeamSlotForPatternOnDay(
            $partnerId,
            $anchorDate,
            $matchingPattern,
            (int) $anchorSlot->team_id,
            $effectiveLocationFilter
        );

        if (! $resolvedAnchorSlot || (int) $resolvedAnchorSlot->id !== (int) $anchorSlot->id) {
            return response()->json([
                'message' => 'Для группы на выбранную дату не найдено занятие с указанным днём и временем.',
                'errors' => [
                    'patterns' => [
                        'Для группы на выбранную дату не найдено занятие с указанным временем в расписании школы.',
                    ],
                ],
            ], 422);
        }

        if (! $this->calendarService->slotActiveOnDate($anchorSlot, $anchorDate)) {
            return response()->json([
                'message' => 'Слот недействителен на выбранную дату.',
                'errors' => ['anchor_date' => ['Слот недействителен на выбранную дату.']],
            ], 422);
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $anchorSlot->id, $anchorDate)) {
            return response()->json([
                'message' => 'На эту дату занятие исключено из расписания школы.',
                'errors' => ['anchor_date' => ['На эту дату занятие исключено из расписания школы.']],
            ], 422);
        }

        try {
            DB::transaction(function () use (
                $partnerId,
                $user,
                $ulp,
                $anchorDate,
                $anchorSlot,
                $effectiveLocationFilter,
                $patterns,
            ) {
                $this->calendarPeriodService->applyFirstCalendarAnchor($ulp, $anchorDate);
                $ulp->refresh();

                $periodEnd = CarbonImmutable::parse($ulp->ends_at->format('Y-m-d'))->startOfDay();

                $lessonsNeeded = (int) $ulp->lessons_remaining;
                if ($lessonsNeeded < 1) {
                    throw new InvalidArgumentException('На абонементе не осталось занятий.');
                }

                $this->calendarService->assertEveryFixedPatternOccurrenceResolvableInPeriod(
                    $anchorSlot,
                    $anchorDate,
                    $periodEnd,
                    $patterns,
                    $partnerId,
                    (int) $anchorSlot->team_id,
                    $effectiveLocationFilter
                );

                $chain = $this->calendarService->buildFixedOccurrenceChain(
                    $partnerId,
                    $anchorDate,
                    $anchorSlot,
                    $patterns,
                    $lessonsNeeded,
                    $periodEnd,
                    $effectiveLocationFilter
                );

                $this->calendarService->assertFixedChainHasNoTimeOverlapWithExistingUserLessons(
                    (int) $user->id,
                    $partnerId,
                    $chain
                );

                $scheduledCount = count($chain);

                foreach ($chain as $item) {
                    /** @var CarbonImmutable $date */
                    $date = $item['date'];
                    /** @var TeamScheduleSlot $slot */
                    $slot = $item['slot'];

                    UserTeamScheduleSlot::query()->create([
                        'partner_id' => $partnerId,
                        'user_id' => (int) $user->id,
                        'user_lesson_package_id' => (int) $ulp->id,
                        'team_schedule_slot_id' => (int) $slot->id,
                        'starts_at' => $date->toDateString(),
                        'ends_at' => $periodEnd->toDateString(),
                        'created_by' => auth()->id(),
                    ]);
                }

                $ulp->lessons_remaining = max(0, (int) $ulp->lessons_remaining - $scheduledCount);
                $ulp->save();
            });
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = 'anchor_date';
            if (str_contains($msg, 'Конфликт расписания')
                || str_contains($msg, 'В периоде абонемента нет занятия')
                || str_contains($msg, 'исключено из расписания школы')
                || str_contains($msg, 'Слот недействителен на')) {
                $field = 'patterns';
            }

            return response()->json([
                'message' => $msg,
                'errors' => [$field => [$msg]],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['anchor_date' => [$e->getMessage()]],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось создать привязку. Попробуйте ещё раз.',
                'errors' => ['user_lesson_package_id' => ['Не удалось создать привязку.']],
            ], 422);
        }

        return response()->json(['message' => 'Абонемент назначен, занятия привязаны к расписанию школы.']);
    }

    /**
     * Пробное занятие: отметка в календаре без абонемента и без списаний.
     */
    public function storeTrialRegistration(AssignSchoolCalendarTrialRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::query()
            ->where('partner_id', $partnerId)
            ->whereKey((int) $data['user_id'])
            ->where('is_enabled', 1)
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Ученик не найден или недоступен.',
                'errors' => ['user_id' => ['Ученик не найден или недоступен.']],
            ], 422);
        }

        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey((int) $data['team_schedule_slot_id'])->first();

        if (! $slot || (int) $slot->partner_id !== $partnerId) {
            return response()->json([
                'message' => 'Слот расписания не найден.',
                'errors' => ['team_schedule_slot_id' => ['Слот расписания не найден.']],
            ], 422);
        }

        $occurrence = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['occurrence_date'])->startOfDay();

        if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
            return response()->json([
                'message' => 'Дата не соответствует дню недели выбранного слота.',
                'errors' => ['occurrence_date' => ['Дата не соответствует дню недели выбранного слота.']],
            ], 422);
        }

        if (! $this->calendarService->slotActiveOnDate($slot, $occurrence)) {
            return response()->json([
                'message' => 'Слот недействителен на выбранную дату.',
                'errors' => ['occurrence_date' => ['Слот недействителен на выбранную дату.']],
            ], 422);
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $occurrence)) {
            return response()->json([
                'message' => 'На эту дату занятие исключено из расписания школы.',
                'errors' => ['occurrence_date' => ['На эту дату занятие исключено из расписания школы.']],
            ], 422);
        }

        $eligibility = $this->trialEligibilityService->evaluate($partnerId, (int) $user->id, (int) $slot->id, $occurrence);
        if (! $eligibility['allowed']) {
            return response()->json([
                'message' => $eligibility['reason'] ?? 'Нельзя добавить пробное занятие.',
                'errors' => ['user_id' => [$eligibility['reason'] ?? 'Нельзя добавить пробное занятие.']],
            ], 422);
        }

        try {
            UserTeamScheduleSlot::query()->create([
                'partner_id' => $partnerId,
                'user_id' => (int) $user->id,
                'user_lesson_package_id' => null,
                'is_trial_lesson' => true,
                'team_schedule_slot_id' => (int) $slot->id,
                'starts_at' => $occurrence->toDateString(),
                'ends_at' => '9999-12-31',
                'created_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось сохранить пробную запись. Попробуйте ещё раз.',
                'errors' => ['user_id' => ['Не удалось сохранить пробную запись.']],
            ], 422);
        }

        return response()->json(['message' => 'Пробное занятие добавлено в расписание.']);
    }

    /**
     * Проверка перед добавлением пробного (подсказка на кнопке / в модалке).
     */
    public function trialRegistrationEligibility(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $userId = (int) $request->query('user_id', 0);
        $slotId = (int) $request->query('team_schedule_slot_id', 0);
        $dateRaw = (string) $request->query('occurrence_date', '');

        if ($userId < 1 || $slotId < 1 || $dateRaw === '') {
            return response()->json([
                'allowed' => false,
                'reason' => 'Укажите ученика, слот и дату.',
            ]);
        }

        try {
            $occurrence = CarbonImmutable::createFromFormat('Y-m-d', $dateRaw)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'allowed' => false,
                'reason' => 'Некорректная дата.',
            ]);
        }

        $payload = $this->trialEligibilityService->evaluate($partnerId, $userId, $slotId, $occurrence);

        return response()->json($payload);
    }

    public function destroyTrialRegistration(UserTeamScheduleSlot $userTeamScheduleSlot): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $userTeamScheduleSlot->partner_id !== $partnerId || ! $userTeamScheduleSlot->is_trial_lesson) {
            return response()->json([
                'message' => 'Запись не найдена или не является пробным занятием.',
            ], 404);
        }

        try {
            $userTeamScheduleSlot->delete();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось удалить запись.',
            ], 422);
        }

        return response()->json(['message' => 'Пробное занятие удалено из расписания.']);
    }

    /**
     * @param list<array{weekday?: mixed, time_start?: mixed, time_end?: mixed}> $rows
     * @return Collection<int, object{weekday: int, time_start: string, time_end: string}>
     */
    private function dedupeFixedPatternsFromValidated(array $rows): Collection
    {
        $seen = [];
        $out = collect();
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $w = (int) ($row['weekday'] ?? 0);
            $ts = substr((string) ($row['time_start'] ?? ''), 0, 5);
            $te = substr((string) ($row['time_end'] ?? ''), 0, 5);
            if ($w < 1 || $w > 7 || $ts === '' || $te === '') {
                continue;
            }
            $key = $w.'|'.$ts.'|'.$te;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out->push((object) [
                'weekday' => $w,
                'time_start' => $ts,
                'time_end' => $te,
            ]);
        }

        return $out;
    }
}
