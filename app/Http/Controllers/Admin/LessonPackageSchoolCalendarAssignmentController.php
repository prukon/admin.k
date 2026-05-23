<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\AssignSchoolCalendarFixedRequest;
use App\Http\Requests\Admin\AssignSchoolCalendarFlexibleRequest;
use App\Http\Requests\Admin\AssignSchoolCalendarSingleLessonRequest;
use App\Http\Requests\Admin\AssignSchoolCalendarTrialRequest;
use App\Http\Requests\Admin\StoreSchoolCalendarSingleLessonRegistrationRequest;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\SchoolCalendarSingleLessonRegistrationService;
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
        private readonly SchoolCalendarSingleLessonRegistrationService $singleLessonRegistrationService,
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
                'single_lesson' => [
                    'allowed' => false,
                    'reason' => $r,
                    'mode' => null,
                    'existing_assignments' => [],
                    'templates' => [],
                ],
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

        $scheduledForUlp = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', (int) $ulp->id)
            ->count();
        if ($scheduledForUlp >= (int) $ulp->lessons_total) {
            return response()->json([
                'message' => 'Достигнут лимит занятий в календаре для этого абонемента.',
                'errors' => ['user_lesson_package_id' => ['Достигнут лимит занятий в календаре для этого абонемента.']],
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

        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey((int) $data['team_schedule_slot_id'])->first();

        if (! $slot || (int) $slot->partner_id !== $partnerId) {
            return response()->json([
                'message' => 'Слот расписания не найден.',
                'errors' => ['team_schedule_slot_id' => ['Слот расписания не найден.']],
            ], 422);
        }

        $occurrence = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['occurrence_date'])->startOfDay();

        try {
            $this->singleLessonRegistrationService->bindUlpToSlot(
                $partnerId,
                $ulp,
                $slot,
                $occurrence,
                auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = str_contains($msg, 'назначен') || str_contains($msg, 'абонемент') || str_contains($msg, 'лимит') || str_contains($msg, 'слот в календаре')
                ? 'user_lesson_package_id'
                : 'occurrence_date';

            return response()->json([
                'message' => $msg,
                'errors' => [$field => [$msg]],
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

    /**
     * Разовое занятие из модалки слота: привязка существующего назначения или создание нового с записью в календарь.
     */
    public function storeSingleLessonRegistration(StoreSchoolCalendarSingleLessonRegistrationRequest $request): JsonResponse
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

        try {
            $this->singleLessonRegistrationService->register($partnerId, $data, auth()->id());
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = 'occurrence_date';
            if (str_contains($msg, 'назначен') || str_contains($msg, 'абонемент') || str_contains($msg, 'лимит') || str_contains($msg, 'слот в календаре')) {
                $field = 'user_lesson_package_id';
            } elseif (str_contains($msg, 'Шаблон') || str_contains($msg, 'шаблон')) {
                $field = 'lesson_package_id';
            } elseif (str_contains($msg, 'слот')) {
                $field = 'team_schedule_slot_id';
            }

            return response()->json([
                'message' => $msg,
                'errors' => [$field => [$msg]],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось записать разовое занятие. Попробуйте ещё раз.',
                'errors' => ['user_id' => ['Не удалось записать разовое занятие.']],
            ], 422);
        }

        return response()->json(['message' => 'Разовое занятие записано в расписание.']);
    }

    /**
     * Отмена записи разового занятия в календаре: строка расписания удаляется, назначение абонемента сохраняется.
     */
    public function destroySingleLessonRegistration(UserTeamScheduleSlot $userTeamScheduleSlot): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        try {
            $this->singleLessonRegistrationService->cancelRegistration(
                $userTeamScheduleSlot,
                $partnerId,
                auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось отменить запись.',
            ], 422);
        }

        return response()->json(['message' => 'Запись разового занятия отменена. Назначение абонемента сохранено.']);
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

        if ((int) $ulp->lessons_total < 1) {
            return response()->json([
                'message' => 'У абонемента не задан объём занятий.',
                'errors' => ['user_lesson_package_id' => ['У абонемента не задан объём занятий.']],
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

                $scheduledExisting = UserTeamScheduleSlot::query()
                    ->where('user_lesson_package_id', (int) $ulp->id)
                    ->count();
                $lessonsNeeded = (int) $ulp->lessons_total - $scheduledExisting;
                if ($lessonsNeeded < 1) {
                    throw new InvalidArgumentException('Нет свободных занятий для записи в календарь по этому абонементу.');
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
     * Пробное занятие: одна активная запись на ученика; флаг has_used_school_schedule_trial при создании и сброс при отмене, если других пробных строк нет.
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
            DB::transaction(function () use ($partnerId, $user, $slot, $occurrence): void {
                /** @var User $lockedUser */
                $lockedUser = User::query()->whereKey((int) $user->id)->lockForUpdate()->firstOrFail();

                if ($lockedUser->has_used_school_schedule_trial) {
                    throw new \RuntimeException('trial_already_used');
                }

                $trialDup = UserTeamScheduleSlot::query()
                    ->where('partner_id', $partnerId)
                    ->where('user_id', (int) $lockedUser->id)
                    ->where('is_trial_lesson', true)
                    ->whereNull('user_lesson_package_id')
                    ->lockForUpdate()
                    ->exists();

                if ($trialDup) {
                    throw new \RuntimeException('trial_already_scheduled');
                }

                UserTeamScheduleSlot::query()->create([
                    'partner_id' => $partnerId,
                    'user_id' => (int) $lockedUser->id,
                    'user_lesson_package_id' => null,
                    'is_trial_lesson' => true,
                    'trial_lessons_remaining' => 1,
                    'trial_lessons_total' => 1,
                    'team_schedule_slot_id' => (int) $slot->id,
                    'starts_at' => $occurrence->toDateString(),
                    'ends_at' => $occurrence->toDateString(),
                    'created_by' => auth()->id(),
                ]);

                $lockedUser->forceFill(['has_used_school_schedule_trial' => true])->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'trial_already_used') {
                return response()->json([
                    'message' => 'Пробное занятие для этого ученика уже было использовано.',
                    'errors' => ['user_id' => ['Пробное занятие для этого ученика уже было использовано.']],
                ], 422);
            }
            if ($e->getMessage() === 'trial_already_scheduled') {
                return response()->json([
                    'message' => 'У ученика уже есть запись на пробное занятие.',
                    'errors' => ['user_id' => ['У ученика уже есть запись на пробное занятие.']],
                ], 422);
            }

            throw $e;
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

        if ((int) $userTeamScheduleSlot->partner_id !== $partnerId
            || ! $userTeamScheduleSlot->is_trial_lesson
            || $userTeamScheduleSlot->user_lesson_package_id !== null) {
            return response()->json([
                'message' => 'Запись не найдена или не является пробным занятием.',
            ], 404);
        }

        try {
            $userTeamScheduleSlot->loadMissing([
                'user:id,name,lastname',
                'slot:id,team_id,weekday,time_start,time_end',
                'slot.team:id,title',
            ]);

            $trialId = (int) $userTeamScheduleSlot->id;
            $userId = (int) $userTeamScheduleSlot->user_id;
            $slotId = (int) $userTeamScheduleSlot->team_schedule_slot_id;
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

            DB::transaction(function () use ($partnerId, $userId, $slotId, $occurrenceDate, $trialId, $userLabel, $teamTitle, $timeLabel, $userTeamScheduleSlot) {
                UserLessonOccurrenceStatusEvent::query()
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->where('team_schedule_slot_id', $slotId)
                    ->whereDate('occurrence_date', $occurrenceDate)
                    ->whereNull('user_lesson_package_id')
                    ->delete();

                $userTeamScheduleSlot->delete();

                $lockedUser = User::query()->whereKey($userId)->lockForUpdate()->first();
                if ($lockedUser !== null) {
                    $stillHasTrialSlot = UserTeamScheduleSlot::query()
                        ->where('user_id', $userId)
                        ->where('is_trial_lesson', true)
                        ->whereNull('user_lesson_package_id')
                        ->exists();
                    if (! $stillHasTrialSlot) {
                        $lockedUser->forceFill(['has_used_school_schedule_trial' => false])->save();
                    }
                }

                $slotPart = $teamTitle !== '' ? ('; группа: '.$teamTitle) : '';
                $whenPart = $occurrenceDate !== '' ? ('; дата: '.$occurrenceDate) : '';
                $timePart = $timeLabel !== '' ? (' '.$timeLabel) : '';

                MyLog::query()->create([
                    'type' => 60,
                    'action' => 601,
                    'author_id' => auth()->id(),
                    'partner_id' => $partnerId,
                    'user_id' => $userId,
                    'description' => 'Отменено пробное занятие в расписании; ученик: '.$userLabel.$slotPart.$whenPart.$timePart,
                    'target_type' => UserTeamScheduleSlot::class,
                    'target_id' => $trialId,
                    'target_label' => $userLabel.', пробное занятие, '.$occurrenceDate.($timeLabel !== '' ? (' '.$timeLabel) : ''),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось удалить запись.',
            ], 422);
        }

        return response()->json(['message' => 'Пробное занятие отменено.']);
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
