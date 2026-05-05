<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\ListUserLessonOccurrenceStatusHistoryRequest;
use App\Http\Requests\Admin\StoreUserLessonOccurrenceStatusEventRequest;
use App\Models\LessonOccurrenceStatus;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\PartnerContext;
use App\Services\SchoolScheduleTrialLessonConsumptionAdjuster;
use App\Services\UserLessonPackageConsumptionAdjuster;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class LessonPackageSchoolCalendarOccurrenceStatusController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function store(StoreUserLessonOccurrenceStatusEventRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $slotId = (int) $data['team_schedule_slot_id'];
        $userId = (int) $data['user_id'];
        $ulpId = isset($data['user_lesson_package_id']) ? (int) $data['user_lesson_package_id'] : null;
        if ($ulpId !== null && $ulpId < 1) {
            $ulpId = null;
        }
        $statusId = (int) $data['lesson_occurrence_status_id'];
        $occurrenceDate = (string) $data['occurrence_date'];

        /** @var LessonOccurrenceStatus|null $status */
        $status = LessonOccurrenceStatus::query()
            ->whereKey($statusId)
            ->where('partner_id', $partnerId)
            ->first();

        if (! $status || ! $status->is_active) {
            return response()->json([
                'message' => 'Статус не найден или отключён.',
                'errors' => ['lesson_occurrence_status_id' => ['Выберите активный статус из списка.']],
            ], 422);
        }

        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey($slotId)->where('partner_id', $partnerId)->first();
        if (! $slot) {
            return response()->json([
                'message' => 'Слот расписания не найден.',
                'errors' => ['team_schedule_slot_id' => ['Слот расписания не найден.']],
            ], 422);
        }

        /** @var User|null $user */
        $user = User::query()->where('partner_id', $partnerId)->whereKey($userId)->first();
        if (! $user) {
            return response()->json([
                'message' => 'Ученик не найден.',
                'errors' => ['user_id' => ['Ученик не найден.']],
            ], 422);
        }

        $assignmentExists = false;
        $savedUlpId = $ulpId;

        if ($ulpId !== null) {
            /** @var UserLessonPackage|null $ulp */
            $ulp = UserLessonPackage::query()
                ->whereKey($ulpId)
                ->where('user_id', $userId)
                ->whereHas('lessonPackage', fn ($q) => $q->where('partner_id', $partnerId))
                ->first();

            if (! $ulp) {
                return response()->json([
                    'message' => 'Назначение абонемента не найдено.',
                    'errors' => ['user_lesson_package_id' => ['Назначение не найдено или не принадлежит ученику.']],
                ], 422);
            }

            $assignmentExists = UserTeamScheduleSlot::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('team_schedule_slot_id', $slotId)
                ->where('user_lesson_package_id', $ulpId)
                ->whereDate('starts_at', $occurrenceDate)
                ->exists();
        } else {
            $assignmentExists = UserTeamScheduleSlot::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('team_schedule_slot_id', $slotId)
                ->whereDate('starts_at', $occurrenceDate)
                ->where('is_trial_lesson', true)
                ->whereNull('user_lesson_package_id')
                ->exists();
            $savedUlpId = null;
        }

        if (! $assignmentExists) {
            return response()->json([
                'message' => 'На эту дату нет записи ученика на выбранный слот.',
                'errors' => ['occurrence_date' => ['Нет соответствующей записи в расписании школы.']],
            ], 422);
        }

        /** @var UserTeamScheduleSlot|null $trialUtss */
        $trialUtss = null;
        if ($ulpId === null) {
            $trialUtss = UserTeamScheduleSlot::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('team_schedule_slot_id', $slotId)
                ->whereDate('starts_at', $occurrenceDate)
                ->where('is_trial_lesson', true)
                ->whereNull('user_lesson_package_id')
                ->first();
        }

        try {
            $event = DB::transaction(function () use (
                $partnerId,
                $userId,
                $slotId,
                $occurrenceDate,
                $savedUlpId,
                $statusId,
                $status,
                $trialUtss,
            ): UserLessonOccurrenceStatusEvent {
                if ($savedUlpId !== null) {
                    $prevEvent = UserLessonOccurrenceStatusEvent::query()
                        ->where('partner_id', $partnerId)
                        ->where('user_id', $userId)
                        ->where('team_schedule_slot_id', $slotId)
                        ->whereDate('occurrence_date', $occurrenceDate)
                        ->where('user_lesson_package_id', $savedUlpId)
                        ->with(['lessonOccurrenceStatus:id,consumes_lesson'])
                        ->orderByDesc('id')
                        ->first();

                    $prevConsumed = $prevEvent?->lessonOccurrenceStatus?->consumes_lesson;

                    $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(
                        $prevConsumed,
                        (bool) $status->consumes_lesson
                    );

                    if ($delta !== 0) {
                        $ulp = UserLessonPackage::query()->whereKey($savedUlpId)->firstOrFail();
                        UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, $delta);
                    }
                } elseif ($trialUtss !== null) {
                    $prevEvent = UserLessonOccurrenceStatusEvent::query()
                        ->where('partner_id', $partnerId)
                        ->where('user_id', $userId)
                        ->where('team_schedule_slot_id', $slotId)
                        ->whereDate('occurrence_date', $occurrenceDate)
                        ->whereNull('user_lesson_package_id')
                        ->with(['lessonOccurrenceStatus:id,consumes_lesson'])
                        ->orderByDesc('id')
                        ->first();

                    $prevConsumed = $prevEvent?->lessonOccurrenceStatus?->consumes_lesson;

                    $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(
                        $prevConsumed,
                        (bool) $status->consumes_lesson
                    );

                    if ($delta !== 0) {
                        SchoolScheduleTrialLessonConsumptionAdjuster::applyRemainingLessonsDelta($trialUtss, $delta);
                    }
                }

                return UserLessonOccurrenceStatusEvent::query()->create([
                    'partner_id' => $partnerId,
                    'user_id' => $userId,
                    'team_schedule_slot_id' => $slotId,
                    'occurrence_date' => $occurrenceDate,
                    'user_lesson_package_id' => $savedUlpId,
                    'lesson_occurrence_status_id' => $statusId,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['lesson_occurrence_status_id' => [$e->getMessage()]],
            ], 422);
        }

        $event->load(['lessonOccurrenceStatus:id,code,title,color,icon']);

        $payload = [
            'message' => 'Статус сохранён.',
            'event' => [
                'id' => (int) $event->id,
                'lesson_occurrence_status' => $event->lessonOccurrenceStatus
                    ? [
                        'id' => (int) $event->lessonOccurrenceStatus->id,
                        'code' => (string) $event->lessonOccurrenceStatus->code,
                        'title' => (string) $event->lessonOccurrenceStatus->title,
                        'color' => (string) $event->lessonOccurrenceStatus->color,
                        'icon' => $event->lessonOccurrenceStatus->icon,
                    ]
                    : null,
                'created_at' => $event->created_at?->toIso8601String(),
            ],
        ];

        if ($savedUlpId !== null) {
            $ulpFresh = UserLessonPackage::query()
                ->whereKey($savedUlpId)
                ->first(['id', 'lessons_remaining', 'lessons_total']);
            if ($ulpFresh !== null) {
                $payload['user_lesson_package'] = [
                    'id' => (int) $ulpFresh->id,
                    'lessons_remaining' => (int) $ulpFresh->lessons_remaining,
                    'lessons_total' => (int) $ulpFresh->lessons_total,
                ];
            }
        } elseif ($trialUtss !== null) {
            $trialFresh = UserTeamScheduleSlot::query()
                ->whereKey((int) $trialUtss->id)
                ->first(['id', 'trial_lessons_remaining', 'trial_lessons_total']);
            if ($trialFresh !== null) {
                $payload['trial_registration'] = [
                    'user_team_schedule_slot_id' => (int) $trialFresh->id,
                    'lessons_remaining' => (int) ($trialFresh->trial_lessons_remaining ?? 1),
                    'lessons_total' => (int) ($trialFresh->trial_lessons_total ?? 1),
                ];
            }
        }

        return response()->json($payload);
    }

    public function history(ListUserLessonOccurrenceStatusHistoryRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $slotId = (int) $data['team_schedule_slot_id'];
        $userId = (int) $data['user_id'];
        $ulpId = isset($data['user_lesson_package_id']) ? (int) $data['user_lesson_package_id'] : null;
        if ($ulpId !== null && $ulpId < 1) {
            $ulpId = null;
        }
        $occurrenceDate = (string) $data['occurrence_date'];

        $slotOk = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->whereKey($slotId)
            ->exists();
        if (! $slotOk) {
            return response()->json([
                'message' => 'Слот расписания не найден.',
                'errors' => ['team_schedule_slot_id' => ['Слот расписания не найден.']],
            ], 422);
        }

        $userOk = User::query()->where('partner_id', $partnerId)->whereKey($userId)->exists();
        if (! $userOk) {
            return response()->json([
                'message' => 'Ученик не найден.',
                'errors' => ['user_id' => ['Ученик не найден.']],
            ], 422);
        }

        if ($ulpId !== null) {
            $ulpOk = UserLessonPackage::query()
                ->whereKey($ulpId)
                ->where('user_id', $userId)
                ->whereHas('lessonPackage', fn ($q) => $q->where('partner_id', $partnerId))
                ->exists();
            if (! $ulpOk) {
                return response()->json([
                    'message' => 'Назначение абонемента не найдено.',
                    'errors' => ['user_lesson_package_id' => ['Назначение не найдено.']],
                ], 422);
            }
        }

        $rows = UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', $partnerId)
            ->where('team_schedule_slot_id', $slotId)
            ->whereDate('occurrence_date', $occurrenceDate)
            ->where('user_id', $userId)
            ->when($ulpId !== null, fn ($q) => $q->where('user_lesson_package_id', $ulpId), fn ($q) => $q->whereNull('user_lesson_package_id'))
            ->with([
                'lessonOccurrenceStatus:id,code,title,color,icon',
                'createdBy:id,name,lastname',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'events' => $rows->map(static function (UserLessonOccurrenceStatusEvent $e): array {
                $author = $e->createdBy;
                $authorLabel = $author
                    ? trim(($author->lastname ?? '').' '.($author->name ?? ''))
                    : null;
                if ($authorLabel === '') {
                    $authorLabel = null;
                }

                return [
                    'id' => (int) $e->id,
                    'lesson_occurrence_status' => $e->lessonOccurrenceStatus
                        ? [
                            'id' => (int) $e->lessonOccurrenceStatus->id,
                            'code' => (string) $e->lessonOccurrenceStatus->code,
                            'title' => (string) $e->lessonOccurrenceStatus->title,
                            'color' => (string) $e->lessonOccurrenceStatus->color,
                            'icon' => $e->lessonOccurrenceStatus->icon,
                        ]
                        : null,
                    'created_at' => $e->created_at?->toIso8601String(),
                    'created_by_label' => $authorLabel,
                ];
            })->values(),
        ]);
    }
}
