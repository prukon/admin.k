<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\AssignSchoolCalendarFixedRequest;
use App\Http\Requests\Admin\AssignSchoolCalendarFlexibleRequest;
use App\Models\LessonPackage;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\PartnerContext;
use App\Services\TeamScheduleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class LessonPackageSchoolCalendarAssignmentController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TeamScheduleCalendarService $calendarService,
    ) {
        parent::__construct($partnerContext);
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

        $ulpStart = Carbon::parse($ulp->starts_at)->startOfDay();
        $ulpEnd = Carbon::parse($ulp->ends_at)->endOfDay();
        if ($occurrence->lt($ulpStart) || $occurrence->gt($ulpEnd)) {
            return response()->json([
                'message' => 'Дата выходит за пределы периода абонемента.',
                'errors' => ['occurrence_date' => ['Дата выходит за пределы периода абонемента.']],
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
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось сохранить привязку. Попробуйте ещё раз.',
                'errors' => ['occurrence_date' => ['Не удалось сохранить привязку.']],
            ], 422);
        }

        return response()->json(['message' => 'Занятие привязано к абонементу.']);
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

        /** @var LessonPackage|null $package */
        $package = LessonPackage::query()
            ->with(['timeSlots' => fn ($q) => $q->orderBy('weekday')->orderBy('time_start')])
            ->whereKey((int) $data['lesson_package_id'])
            ->first();

        if (! $package || (string) $package->schedule_type !== 'fixed') {
            return response()->json([
                'errors' => ['lesson_package_id' => ['Выберите абонемент с фиксированным расписанием.']],
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
        $startsAt = $anchorDate->startOfDay();
        $endsAt = $startsAt->addDays((int) $package->duration_days)->startOfDay();

        try {
            $chain = $this->calendarService->buildFixedOccurrenceChain(
                $partnerId,
                $anchorDate,
                $anchorSlot,
                $package->timeSlots,
                (int) $package->lessons_count,
                $endsAt,
                $effectiveLocationFilter
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['anchor_date' => [$e->getMessage()]],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['anchor_date' => [$e->getMessage()]],
            ], 422);
        }

        try {
            DB::transaction(function () use (
                $partnerId,
                $user,
                $package,
                $chain,
                $startsAt,
                $endsAt,
            ) {
                /** @var UserLessonPackage $ulp */
                $ulp = UserLessonPackage::query()->create([
                    'user_id' => (int) $user->id,
                    'lesson_package_id' => (int) $package->id,
                    'starts_at' => $startsAt->toDateString(),
                    'ends_at' => $endsAt->toDateString(),
                    'lessons_total' => (int) $package->lessons_count,
                    'lessons_remaining' => (int) $package->lessons_count,
                    'created_by' => auth()->id(),
                ]);

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
                        'ends_at' => $endsAt->toDateString(),
                        'created_by' => auth()->id(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Не удалось создать назначение. Попробуйте ещё раз.',
                'errors' => ['lesson_package_id' => ['Не удалось создать назначение.']],
            ], 422);
        }

        return response()->json(['message' => 'Абонемент назначен, занятия привязаны к расписанию школы.']);
    }
}
