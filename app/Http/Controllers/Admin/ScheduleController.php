<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\DestroyScheduleJournalOccurrenceRequest;
use App\Http\Requests\Admin\GetScheduleJournalIndexRequest;
use App\Http\Requests\Admin\GetScheduleCellContextRequest;
use App\Http\Requests\Admin\GetScheduleJournalAbonementContextRequest;
use App\Http\Requests\Admin\GetScheduleJournalEmptyCellContextRequest;
use App\Http\Requests\Admin\GetScheduleJournalFlexibleContextRequest;
use App\Http\Requests\Admin\PlaceScheduleJournalFixedAbonementRequest;
use App\Http\Requests\Admin\PlaceScheduleJournalFlexibleAbonementRequest;
use App\Http\Requests\Admin\PlaceScheduleJournalSingleLessonRequest;
use App\Http\Requests\Admin\PlaceScheduleJournalTrialLessonRequest;
use App\Http\Requests\Admin\SyncScheduleUserTeamsRequest;
use App\Http\Requests\Admin\UpdateScheduleJournalOccurrenceRequest;
use App\Http\Requests\Team\FilterRequest;
use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use App\Services\PartnerContext;
use App\Services\Schedule\JournalEmptyCellPlacementContextService;
use App\Services\Schedule\JournalFixedAbonementPlacementService;
use App\Services\Schedule\JournalFlexibleAbonementPlacementService;
use App\Services\Schedule\JournalMonthlyPaymentStatusService;
use App\Services\Schedule\JournalOccurrenceAnnulmentService;
use App\Services\Schedule\JournalSingleLessonPlacementService;
use App\Services\Schedule\JournalTrialLessonPlacementService;
use App\Services\Schedule\ScheduleJournalMonthService;
use App\Services\TeamUserSyncService;
use App\Services\Postpay\PostpayJournalService;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Support\BuildsLogTable;
use App\Support\Money;
use App\Support\ScheduleOccurrenceTrainerIds;
use Carbon\Carbon;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ScheduleController extends AdminBaseController
{
    use BuildsLogTable;

    public const JOURNAL_STUDENTS_PER_PAGE = 100;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
        private readonly TeamUserSyncService $teamUserSync,
        private readonly ScheduleJournalMonthService $journalMonthService,
        private readonly JournalFixedAbonementPlacementService $fixedPlacementService,
        private readonly JournalFlexibleAbonementPlacementService $flexiblePlacementService,
        private readonly JournalEmptyCellPlacementContextService $emptyCellPlacementContextService,
        private readonly JournalTrialLessonPlacementService $trialLessonPlacementService,
        private readonly JournalSingleLessonPlacementService $singleLessonPlacementService,
        private readonly JournalOccurrenceAnnulmentService $occurrenceAnnulmentService,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
        private readonly PostpayJournalService $postpayJournal,
        private readonly PostpayUsersPriceSync $postpaySync,
        private readonly JournalMonthlyPaymentStatusService $journalMonthlyPaymentStatus,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(GetScheduleJournalIndexRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        $statusesForDisplay = LessonOccurrenceStatus::query()
            ->forPartner($partnerId)
            ->ordered()
            ->get();
        $availableStatuses = $statusesForDisplay->where('is_active', true)->values();

        $data = $request->validated();
        $year = (int) ($data['year'] ?? date('Y'));
        $month = str_pad((string) ($data['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
        $team_id = (string) ($data['team'] ?? 'all');
        $searchQ = trim((string) ($data['q'] ?? ''));

        $startOfMonth = Carbon::createFromDate($year, (int) $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $usersQuery = User::query()
            ->select(['id', 'name', 'lastname', 'partner_id', 'role_id', 'is_enabled'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser();

        if ($team_id !== 'all') {
            $usersQuery->filterByStudentTeam($partnerId, $team_id);
        }

        if ($searchQ !== '') {
            $like = '%'.$searchQ.'%';
            $usersQuery->where(function ($q) use ($like) {
                $q->where('lastname', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhereRaw(
                        "CONCAT(IFNULL(lastname, ''), ' ', IFNULL(name, '')) LIKE ?",
                        [$like]
                    );
            });
        }

        $users = $usersQuery
            ->with(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)])
            ->orderBy('lastname')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::JOURNAL_STUDENTS_PER_PAGE)
            ->withQueryString();

        $userIds = $users->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $journalOccurrences = $this->journalMonthService->occurrencesByUserDate(
            $partnerId,
            $userIds,
            $startOfMonth,
            $endOfMonth,
            $team_id,
        );

        $journalAssignments = $this->journalMonthService->fixedAssignmentsByUser($partnerId, $userIds);

        $monthFirst = $startOfMonth->format('Y-m-d');
        $postpayByUser = $this->postpayJournal->postpayAbonementHintsByUser($userIds, $monthFirst, (string) $team_id);
        $postpayUsers = [];
        foreach (array_keys($postpayByUser) as $uid) {
            $postpayUsers[(int) $uid] = true;
        }
        $postpayLockedUsers = $this->postpayJournal->postpayLockedUserFlags($userIds, $monthFirst, (string) $team_id);

        $flexibleByUser = $this->journalMonthService->flexibleAssignableByUserForBillingMonth(
            $partnerId,
            $userIds,
            $monthFirst,
            $team_id,
        );
        $flexibleUsers = [];
        foreach ($flexibleByUser as $uid => $assignments) {
            if ($assignments !== []) {
                $flexibleUsers[(int) $uid] = true;
            }
        }

        $journalPaymentStatuses = $this->journalMonthlyPaymentStatus->statusesByUser(
            $partnerId,
            $userIds,
            $monthFirst,
            (string) $team_id,
        );

        $teams = Team::where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->orderBy('order_by')
            ->get();

        $teamWeekdays = [];
        if ($team_id !== 'all' && $team_id !== 'none' && is_numeric($team_id)) {
            $teamWeekdays = DB::table('team_weekdays')
                ->where('team_id', (int) $team_id)
                ->pluck('weekday_id')
                ->toArray();
        }

        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        $scheduledStatusId = LessonOccurrenceStatus::scheduledIdForPartner($partnerId);

        return view('admin.schedule.index', array_merge(compact(
            'year',
            'month',
            'team_id',
            'searchQ',
            'users',
            'journalOccurrences',
            'journalAssignments',
            'journalPaymentStatuses',
            'postpayUsers',
            'postpayLockedUsers',
            'postpayByUser',
            'flexibleUsers',
            'flexibleByUser',
            'teams',
            'startOfMonth',
            'endOfMonth',
            'teamWeekdays',
            'availableStatuses',
            'statusesForDisplay',
            'visitedStatusId',
            'scheduledStatusId',
        ), [
            'activeTab' => 'journal',
            'canPlaceEmptyCellLesson' => auth()->user()?->can('lessonPackages.view') === true,
        ]));
    }

    public function cellContext(GetScheduleCellContextRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $user = $this->findScheduleStudentForPartner($partnerId, (int) $data['user_id']);
        $user->load('teams');

        $date = (string) $data['date'];
        $occurrences = $this->journalMonthService->occurrencesByUserDate(
            $partnerId,
            [(int) $user->id],
            Carbon::parse($date)->startOfDay(),
            Carbon::parse($date)->endOfDay(),
            'all',
        );
        $dayKey = $user->id.'_'.$date;
        $dayOccurrences = $occurrences[$dayKey] ?? [];

        $selectedUtssId = isset($data['utss_id']) ? (int) $data['utss_id'] : null;
        $selected = null;
        if ($selectedUtssId) {
            foreach ($dayOccurrences as $item) {
                if ((int) $item['utss_id'] === $selectedUtssId) {
                    $selected = $item;
                    break;
                }
            }
        } elseif (count($dayOccurrences) === 1) {
            $selected = $dayOccurrences[0];
        }

        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        $contextTeamId = $selected['team_id']
            ?? $this->resolveScheduleContextTeamId(
                $user,
                isset($data['context_team_id']) ? (int) $data['context_team_id'] : null
            );
        $teamDefaultIds = $this->teamDefaultTrainerProfileIds($partnerId, $contextTeamId);
        $trainers = $this->trainerOptionsForPartner($partnerId);

        $currentStatusId = $selected['lesson_occurrence_status_id'] ?? null;
        $isVisitedEntry = $visitedStatusId !== null
            && $currentStatusId !== null
            && (int) $currentStatusId === (int) $visitedStatusId;

        $trainerIdsForSelect = [];
        if ($isVisitedEntry) {
            $trainerIdsForSelect = array_values(array_map(
                'intval',
                $selected['trainer_profile_ids'] ?? (
                    ! empty($selected['trainer_profile_id'])
                        ? [(int) $selected['trainer_profile_id']]
                        : []
                )
            ));
        }

        $preferredTeamId = isset($data['context_team_id']) ? (int) $data['context_team_id'] : null;
        $postpayTeams = $this->postpayJournal->postpayTeamsForDate((int) $user->id, $date);
        $resolvedPostpayTeamId = $this->postpayJournal->resolvePostpayTeamId(
            (int) $user->id,
            $date,
            $preferredTeamId
        );

        return response()->json([
            'visited_status_id' => $visitedStatusId,
            'occurrences' => $dayOccurrences,
            'selected' => $selected,
            'current_status_id' => $currentStatusId,
            'comment' => $selected['comment'] ?? null,
            'team_id' => $contextTeamId,
            'team_ids' => $this->teamUserSync->teamIdsForStudent($user),
            'teams_label' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
            'postpay_teams' => $postpayTeams,
            'postpay_team_id' => $resolvedPostpayTeamId,
            'team_default_trainer_profile_ids' => $teamDefaultIds,
            // BC: первый id (старые клиенты / тесты).
            'team_default_trainer_profile_id' => $teamDefaultIds[0] ?? null,
            'trainer_profile_ids_for_select' => $isVisitedEntry ? $trainerIdsForSelect : [],
            // BC: первый id строкой (старые клиенты / тесты).
            'trainer_profile_id_for_select' => $isVisitedEntry && $trainerIdsForSelect !== []
                ? (string) $trainerIdsForSelect[0]
                : ($isVisitedEntry ? '' : null),
            'trainers' => $trainers->map(fn (TrainerProfile $profile) => [
                'id' => $profile->id,
                'name' => $this->trainerDisplayName($profile),
            ])->values(),
        ]);
    }

    public function update(UpdateScheduleJournalOccurrenceRequest $request): JsonResponse|RedirectResponse
    {
        $authorId = auth()->id();
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        try {
            /** @var array{
             *     utss_id: int,
             *     occurrence_date: string,
             *     comment: string|null,
             *     created: bool,
             *     status: array{id: int, title: string, icon: string|null, color: string|null}
             * } $result
             */
            $result = DB::transaction(function () use ($authorId, $partnerId, $data) {
                $user = $this->findScheduleStudentForPartner($partnerId, (int) $data['user_id']);
                $occurrenceDate = (string) $data['occurrence_date'];
                $createPostpay = ! empty($data['create_postpay']);

                /** @var UserTeamScheduleSlot|null $utss */
                $utss = null;

                if (! empty($data['utss_id'])) {
                    $utss = UserTeamScheduleSlot::query()
                        ->with(['slot.team'])
                        ->where('partner_id', $partnerId)
                        ->where('user_id', (int) $user->id)
                        ->whereKey((int) $data['utss_id'])
                        ->first();

                    if (! $utss) {
                        throw new InvalidArgumentException('Запись занятия не найдена.');
                    }

                    $utssDate = Carbon::parse($utss->starts_at)->format('Y-m-d');
                    if ($utssDate !== $occurrenceDate) {
                        throw new InvalidArgumentException('Дата не совпадает с записью занятия.');
                    }
                } elseif ($createPostpay) {
                    $preferredTeamId = (int) ($data['team_id'] ?? 0);
                    $teamId = $this->postpayJournal->resolvePostpayTeamId(
                        (int) $user->id,
                        $occurrenceDate,
                        $preferredTeamId > 0 ? $preferredTeamId : null
                    );
                    if ($teamId === null || $teamId <= 0) {
                        throw new InvalidArgumentException('Для ученика не назначена постоплата на этот месяц.');
                    }
                    $utss = $this->postpayJournal->ensureOccurrence(
                        $partnerId,
                        $user,
                        $teamId,
                        $occurrenceDate,
                        $authorId !== null ? (int) $authorId : null
                    );
                    $utss->load(['slot.team']);
                } else {
                    throw new InvalidArgumentException('Запись занятия не найдена.');
                }

                $teamIdForLock = (int) ($utss->slot?->team_id ?? ($data['team_id'] ?? 0));
                if ($teamIdForLock > 0) {
                    $this->postpayJournal->assertOccurrenceEditable((int) $user->id, $teamIdForLock, $occurrenceDate);
                }

                $status = LessonOccurrenceStatus::findActiveForPartner(
                    (int) $data['lesson_occurrence_status_id'],
                    $partnerId
                );

                $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
                $trainerProfileIds = ($visitedStatusId !== null && (int) $status->id === $visitedStatusId)
                    ? ScheduleOccurrenceTrainerIds::normalize(
                        $data['trainer_profile_ids'] ?? null,
                        $data['trainer_profile_id'] ?? null
                    )
                    : [];

                if ($trainerProfileIds !== []) {
                    $validIds = ScheduleOccurrenceTrainerIds::existingForPartner($partnerId, $trainerProfileIds);
                    if (count($validIds) !== count($trainerProfileIds)) {
                        throw new InvalidArgumentException('Тренер не найден.');
                    }
                    $trainerProfileIds = $validIds;
                }

                $trainerProfileId = $trainerProfileIds[0] ?? null;

                $ulpId = $utss->user_lesson_package_id !== null ? (int) $utss->user_lesson_package_id : null;
                $prevEvent = UserLessonOccurrenceStatusEvent::query()
                    ->with(['lessonOccurrenceStatus', 'trainerProfile.user', 'trainerProfiles.user'])
                    ->where('partner_id', $partnerId)
                    ->where('user_id', (int) $user->id)
                    ->where('team_schedule_slot_id', (int) $utss->team_schedule_slot_id)
                    ->whereDate('occurrence_date', $occurrenceDate)
                    ->when(
                        $ulpId !== null,
                        fn ($q) => $q->where('user_lesson_package_id', $ulpId),
                        fn ($q) => $q->whereNull('user_lesson_package_id')
                    )
                    ->orderByDesc('id')
                    ->first();

                $oldStatusName = $prevEvent?->lessonOccurrenceStatus?->title ?? 'не было';
                $oldTrainerName = ScheduleOccurrenceTrainerIds::formatNames($prevEvent?->trainerProfiles ?? collect())
                    ?? $this->trainerDisplayName($prevEvent?->trainerProfile);
                $comment = isset($data['comment']) ? (string) $data['comment'] : null;

                $this->occurrenceStatusService->apply(
                    $partnerId,
                    (int) $user->id,
                    (int) $utss->team_schedule_slot_id,
                    $occurrenceDate,
                    $ulpId,
                    $status,
                    $authorId !== null ? (int) $authorId : null,
                    $trainerProfileId,
                    $comment,
                    $trainerProfileIds,
                );

                if ($teamIdForLock > 0) {
                    $this->postpaySync->syncAfterOccurrenceChange(
                        $partnerId,
                        (int) $user->id,
                        $occurrenceDate,
                        $teamIdForLock
                    );
                }

                $newTrainerName = 'Без тренера';
                if ($trainerProfileIds !== []) {
                    $profilesForAudit = TrainerProfile::query()
                        ->with('user')
                        ->whereIn('id', $trainerProfileIds)
                        ->get()
                        ->sortBy(fn (TrainerProfile $p) => array_search((int) $p->id, $trainerProfileIds, true));
                    $newTrainerName = ScheduleOccurrenceTrainerIds::formatNames($profilesForAudit) ?? 'Без тренера';
                }

                $formattedDate = Carbon::parse($occurrenceDate)->format('d.m.Y');
                $this->auditLogger->record(
                    AuditEvent::ScheduleDayUpdated,
                    AuditContext::make(sprintf(
                        'Дата: "%s", Имя: "%s",%sСтатус до: "%s", Статус после: "%s",%sТренер до: "%s", Тренер после: "%s",%sКомментарий: "%s"',
                        $formattedDate,
                        $user->full_name,
                        "\n",
                        $oldStatusName,
                        $status->title,
                        "\n",
                        $oldTrainerName ?: 'Без тренера',
                        $newTrainerName,
                        "\n",
                        (string) ($comment ?? '')
                    ))
                        ->withUser($user)
                        ->withTargetReference('App\Models\UserTeamScheduleSlot', (int) $utss->id, $user->full_name)
                        ->withPartnerId($partnerId)
                        ->withCreatedAt(now())
                );

                $trainerNameForHover = null;
                $isAttendedStatus = $visitedStatusId !== null && (int) $status->id === (int) $visitedStatusId;
                if ($isAttendedStatus && $trainerProfileIds !== []) {
                    $profilesForHover = TrainerProfile::query()
                        ->with('user')
                        ->whereIn('id', $trainerProfileIds)
                        ->get()
                        ->sortBy(fn (TrainerProfile $p) => array_search((int) $p->id, $trainerProfileIds, true));
                    $trainerNameForHover = ScheduleOccurrenceTrainerIds::formatNames($profilesForHover);
                }

                $flexiblePayload = [];
                if ($ulpId !== null) {
                    /** @var UserLessonPackage|null $ulpFresh */
                    $ulpFresh = UserLessonPackage::query()
                        ->with('lessonPackage:id,name,schedule_type')
                        ->whereKey($ulpId)
                        ->first();
                    if ($ulpFresh && $ulpFresh->isMonthlyFlexibleFromSettingPrices()) {
                        $packageName = trim((string) ($ulpFresh->lessonPackage?->name ?? ''));
                        $flexiblePayload = [
                            'is_flexible' => true,
                            'user_lesson_package_id' => (int) $ulpFresh->id,
                            'slots_remaining' => max(0, (int) $ulpFresh->lessons_remaining),
                            'lessons_total' => (int) $ulpFresh->lessons_total,
                            'fee_amount_cents' => (int) ($ulpFresh->fee_amount_cents ?? 0),
                            'package_name' => $packageName !== '' ? $packageName : 'Гибкий абонемент',
                        ];
                    }
                }

                return array_merge([
                    'utss_id' => (int) $utss->id,
                    'occurrence_date' => $occurrenceDate,
                    'comment' => $comment,
                    'created' => $createPostpay,
                    'package_hover' => $createPostpay
                        ? $this->packageHoverForPostpayTeam(
                            (int) $user->id,
                            $occurrenceDate,
                            $teamIdForLock > 0 ? $teamIdForLock : null
                        )
                        : null,
                    'trainer_name' => $isAttendedStatus ? $trainerNameForHover : null,
                    'trainer_profile_ids' => $isAttendedStatus ? $trainerProfileIds : [],
                    'status' => [
                        'id' => (int) $status->id,
                        'title' => (string) $status->title,
                        'icon' => $status->icon !== null && $status->icon !== '' ? (string) $status->icon : null,
                        'color' => $status->color !== null && $status->color !== '' ? (string) $status->color : null,
                    ],
                ], $flexiblePayload);
            });
        } catch (DomainException $e) {
            return $this->journalMutationResponse(
                $request,
                $e->getMessage(),
                ['lesson_occurrence_status_id' => [$e->getMessage()]],
            );
        } catch (InvalidArgumentException $e) {
            return $this->journalMutationResponse(
                $request,
                $e->getMessage(),
                ['utss_id' => [$e->getMessage()]],
            );
        }

        $message = 'Статус занятия сохранён.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return $this->journalMutationResponse($request, $message);
    }

    public function destroyOccurrence(
        DestroyScheduleJournalOccurrenceRequest $request,
        UserTeamScheduleSlot $utss,
    ): JsonResponse|RedirectResponse {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        try {
            if (! empty($data['occurrence_date'])) {
                $utssDate = Carbon::parse($utss->starts_at)->format('Y-m-d');
                if ($utssDate !== (string) $data['occurrence_date']) {
                    throw new InvalidArgumentException('Дата не совпадает с записью занятия.');
                }
            }

            $result = $this->occurrenceAnnulmentService->annul(
                $partnerId,
                $utss,
                auth()->id() !== null ? (int) auth()->id() : null,
            );
        } catch (DomainException $e) {
            return $this->journalMutationResponse(
                $request,
                $e->getMessage(),
                ['utss_id' => [$e->getMessage()]],
            );
        } catch (InvalidArgumentException $e) {
            return $this->journalMutationResponse(
                $request,
                $e->getMessage(),
                ['utss_id' => [$e->getMessage()]],
            );
        }

        $message = 'Занятие удалено.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return $this->journalMutationResponse($request, $message);
    }

    public function abonementContext(GetScheduleJournalAbonementContextRequest $request, User $user): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);
        $data = $request->validated();

        $user->load(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->with('weekdays')]);

        // В модалке журнала — только fixed из установки цен (есть billing_month + team_id).
        $assignments = array_values(array_filter(
            $this->journalMonthService->fixedAssignmentsForUser($partnerId, (int) $user->id),
            static fn (array $row): bool => ! empty($row['from_setting_prices'])
                && isset($row['team_id'])
                && (int) $row['team_id'] > 0
        ));

        $placeableTeamIds = [];
        foreach ($assignments as $row) {
            if (! empty($row['placeable'])) {
                $placeableTeamIds[(int) $row['team_id']] = true;
            }
        }
        $placeableTeamIdList = array_map('intval', array_keys($placeableTeamIds));

        $preferredTeamId = isset($data['context_team_id']) ? (int) $data['context_team_id'] : null;
        $teamLocked = false;
        $resolvedTeamId = null;

        if ($preferredTeamId !== null && $preferredTeamId > 0 && isset($placeableTeamIds[$preferredTeamId])) {
            $resolvedTeamId = $preferredTeamId;
            $teamLocked = true;
        } elseif (count($placeableTeamIdList) === 1) {
            $resolvedTeamId = $placeableTeamIdList[0];
            $teamLocked = true;
        }

        $teamsSource = $user->teams;
        if ($teamLocked && $resolvedTeamId !== null) {
            $teamsSource = $teamsSource->where('id', $resolvedTeamId);
        } else {
            $teamsSource = $teamsSource->filter(
                static fn (Team $team): bool => isset($placeableTeamIds[(int) $team->id])
            );
        }

        $teamsPayload = $teamsSource->values()->map(function (Team $team) {
            return [
                'id' => (int) $team->id,
                'title' => (string) $team->title,
                'weekdays' => $team->weekdays->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        })->all();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => (int) $user->id,
                'name' => $user->full_name ?: $user->name,
                'team_ids' => $this->teamUserSync->teamIdsForStudent($user),
                'teams_label' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
            ],
            'teams' => $teamsPayload,
            'team_id' => $resolvedTeamId,
            'team_locked' => $teamLocked,
            'assignments' => $assignments,
            'default_start_date' => now()->format('Y-m-d'),
        ]);
    }

    public function placeFixedAbonement(PlaceScheduleJournalFixedAbonementRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);
        $data = $request->validated();

        /** @var UserLessonPackage|null $ulp */
        $ulp = UserLessonPackage::query()
            ->with('lessonPackage')
            ->whereKey((int) $data['user_lesson_package_id'])
            ->first();

        /** @var Team|null $team */
        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey((int) $data['team_id'])
            ->first();

        if (! $ulp || ! $team) {
            $errors = array_filter([
                'user_lesson_package_id' => ! $ulp ? ['Назначение не найдено.'] : null,
                'team_id' => ! $team ? ['Группа не найдена.'] : null,
            ]);

            return $this->journalMutationResponse(
                $request,
                'Назначение или группа не найдены.',
                $errors,
            );
        }

        $startDate = Carbon::createFromFormat('Y-m-d', (string) $data['start_date'])->startOfDay();
        $weekdays = array_map('intval', $data['weekdays'] ?? []);
        $previewOnly = (bool) ($data['preview'] ?? false);

        try {
            $result = $this->fixedPlacementService->place(
                $partnerId,
                $user,
                $ulp,
                $team,
                \Carbon\CarbonImmutable::instance($startDate),
                $weekdays,
                auth()->id() !== null ? (int) auth()->id() : null,
                $previewOnly,
            );
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = 'start_date';
            if (str_contains($msg, 'день недели') || str_contains($msg, 'День')) {
                $field = 'weekdays';
            } elseif (str_contains($msg, 'групп')) {
                $field = 'team_id';
            } elseif (str_contains($msg, 'назначен') || str_contains($msg, 'абонемент') || str_contains($msg, 'Абонемент')) {
                $field = 'user_lesson_package_id';
            } elseif (str_contains($msg, 'Конфликт') || str_contains($msg, 'слот') || str_contains($msg, 'периоде')) {
                $field = 'weekdays';
            }

            return $this->journalMutationResponse(
                $request,
                $msg,
                [$field => [$msg]],
            );
        } catch (Throwable $e) {
            report($e);

            return $this->journalMutationResponse(
                $request,
                'Не удалось разложить абонемент.',
                ['user_lesson_package_id' => ['Не удалось разложить абонемент.']],
            );
        }

        if (! $previewOnly) {
            $this->auditLogger->record(
                AuditEvent::ScheduleFixedLinked,
                AuditContext::make(sprintf(
                    'Журнал: фиксированный абонемент #%d разложен; ученик: %s; записей: %d; период %s — %s',
                    (int) $ulp->id,
                    $user->full_name,
                    (int) $result['linked_count'],
                    $result['starts_at'],
                    $result['ends_at'],
                ))
                    ->withUser($user)
                    ->withTargetReference('App\Models\UserLessonPackage', (int) $ulp->id, $user->full_name)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        }

        $message = $previewOnly
            ? 'Превью построено.'
            : 'Абонемент разложен в расписание.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'preview' => $previewOnly,
                'result' => $result,
                'message' => $message,
            ]);
        }

        return $this->journalMutationResponse($request, $message);
    }

    /**
     * Контекст для модалки «поставить занятие из гибкого» на дату.
     */
    public function flexibleContext(GetScheduleJournalFlexibleContextRequest $request, User $user): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $data = $request->validated();
        $occurrenceDate = (string) $data['occurrence_date'];
        $billingMonth = Carbon::parse($occurrenceDate)->startOfMonth()->format('Y-m-d');
        $preferredTeamId = isset($data['context_team_id']) ? (int) $data['context_team_id'] : null;

        $assignments = $this->journalMonthService->flexibleAssignableByUserForBillingMonth(
            $partnerId,
            [(int) $user->id],
            $billingMonth,
            'all',
        );
        $list = $assignments[(int) $user->id] ?? [];

        // Дата должна попадать в период назначения.
        $day = Carbon::parse($occurrenceDate)->startOfDay();
        $list = array_values(array_filter($list, static function (array $row) use ($day): bool {
            $start = $row['starts_at'] ?? null;
            $end = $row['ends_at'] ?? null;
            if ($start === null || $end === null) {
                return false;
            }
            $d = $day->format('Y-m-d');

            return $d >= $start && $d <= $end;
        }));

        $teamsPayload = [];
        $seenTeams = [];
        foreach ($list as $row) {
            $tid = (int) $row['team_id'];
            if (isset($seenTeams[$tid])) {
                continue;
            }
            $seenTeams[$tid] = true;
            $teamsPayload[] = [
                'id' => $tid,
                'title' => (string) ($row['team_title'] !== '' ? $row['team_title'] : 'Группа #'.$tid),
            ];
        }

        $resolvedTeamId = null;
        if ($preferredTeamId && isset($seenTeams[$preferredTeamId])) {
            $resolvedTeamId = $preferredTeamId;
        } elseif (count($teamsPayload) === 1) {
            $resolvedTeamId = (int) $teamsPayload[0]['id'];
        }

        $selectedAssignment = null;
        if ($resolvedTeamId !== null) {
            foreach ($list as $row) {
                if ((int) $row['team_id'] === $resolvedTeamId) {
                    $selectedAssignment = $row;
                    break;
                }
            }
        } elseif (count($list) === 1) {
            $selectedAssignment = $list[0];
            $resolvedTeamId = (int) $list[0]['team_id'];
        }

        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        $scheduledStatusId = LessonOccurrenceStatus::scheduledIdForPartner($partnerId);
        $teamDefaultIds = $this->teamDefaultTrainerProfileIds($partnerId, $resolvedTeamId);
        $trainers = $this->trainerOptionsForPartner($partnerId);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => (int) $user->id,
                'name' => $user->full_name ?: $user->name,
            ],
            'occurrence_date' => $occurrenceDate,
            'assignments' => $list,
            'teams' => $teamsPayload,
            'team_id' => $resolvedTeamId,
            'assignment' => $selectedAssignment,
            'can_place' => $list !== [],
            'visited_status_id' => $visitedStatusId,
            'scheduled_status_id' => $scheduledStatusId,
            'team_default_trainer_profile_ids' => $teamDefaultIds,
            'team_default_trainer_profile_id' => $teamDefaultIds[0] ?? null,
            'trainers' => $trainers->map(fn (TrainerProfile $profile) => [
                'id' => $profile->id,
                'name' => $this->trainerDisplayName($profile),
            ])->values(),
        ]);
    }

    public function placeFlexibleAbonement(
        PlaceScheduleJournalFlexibleAbonementRequest $request,
        User $user,
    ): JsonResponse|RedirectResponse {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);
        $data = $request->validated();

        /** @var UserLessonPackage|null $ulp */
        $ulp = UserLessonPackage::query()
            ->with('lessonPackage')
            ->whereKey((int) $data['user_lesson_package_id'])
            ->first();

        /** @var Team|null $team */
        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey((int) $data['team_id'])
            ->first();

        if (! $ulp || ! $team) {
            $errors = array_filter([
                'user_lesson_package_id' => ! $ulp ? ['Назначение не найдено.'] : null,
                'team_id' => ! $team ? ['Группа не найдена.'] : null,
            ]);

            return $this->journalMutationResponse(
                $request,
                'Назначение или группа не найдены.',
                $errors,
            );
        }

        $occurrenceDate = \Carbon\CarbonImmutable::createFromFormat(
            'Y-m-d',
            (string) $data['occurrence_date']
        )->startOfDay();

        try {
            $status = LessonOccurrenceStatus::findActiveForPartner(
                (int) $data['lesson_occurrence_status_id'],
                $partnerId
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->journalMutationResponse(
                $request,
                'Выбранный статус не найден или неактивен.',
                ['lesson_occurrence_status_id' => ['Выбранный статус не найден или неактивен.']],
            );
        }

        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        $trainerProfileIds = ($visitedStatusId !== null && (int) $status->id === (int) $visitedStatusId)
            ? ScheduleOccurrenceTrainerIds::normalize(
                $data['trainer_profile_ids'] ?? null,
                $data['trainer_profile_id'] ?? null
            )
            : [];

        if ($trainerProfileIds !== []) {
            $validIds = ScheduleOccurrenceTrainerIds::existingForPartner($partnerId, $trainerProfileIds);
            if (count($validIds) !== count($trainerProfileIds)) {
                return $this->journalMutationResponse(
                    $request,
                    'Тренер не найден.',
                    ['trainer_profile_ids' => ['Тренер не найден.']],
                );
            }
            $trainerProfileIds = $validIds;
        }

        $comment = isset($data['comment']) ? (string) $data['comment'] : null;

        try {
            $result = $this->flexiblePlacementService->place(
                $partnerId,
                $user,
                $ulp,
                $team,
                $occurrenceDate,
                $status,
                auth()->id() !== null ? (int) auth()->id() : null,
                $trainerProfileIds[0] ?? null,
                $comment,
                $trainerProfileIds,
            );
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = 'occurrence_date';
            $msgLower = mb_strtolower($msg);
            if (str_contains($msgLower, 'групп')) {
                $field = 'team_id';
            } elseif (
                str_contains($msgLower, 'статус')
            ) {
                $field = 'lesson_occurrence_status_id';
            } elseif (
                str_contains($msgLower, 'назначен')
                || str_contains($msgLower, 'абонемент')
                || str_contains($msgLower, 'лимит')
                || str_contains($msgLower, 'гибк')
            ) {
                $field = 'user_lesson_package_id';
            }

            return $this->journalMutationResponse(
                $request,
                $msg,
                [$field => [$msg]],
            );
        } catch (Throwable $e) {
            report($e);

            return $this->journalMutationResponse(
                $request,
                'Не удалось поставить занятие.',
                ['occurrence_date' => ['Не удалось поставить занятие.']],
            );
        }

        $this->postpaySync->syncAfterOccurrenceChange(
            $partnerId,
            (int) $user->id,
            $occurrenceDate->toDateString(),
            (int) $team->id,
        );

        $this->auditLogger->record(
            AuditEvent::ScheduleFlexibleLinked,
            AuditContext::make(sprintf(
                'Журнал: гибкий абонемент #%d — занятие на %s; ученик: %s; статус: %s; остаток занятий: %d',
                (int) $ulp->id,
                $occurrenceDate->format('d.m.Y'),
                $user->full_name,
                $status->title,
                (int) $result['slots_remaining'],
            ))
                ->withUser($user)
                ->withTargetReference('App\Models\UserLessonPackage', (int) $ulp->id, $user->full_name)
                ->withPartnerId($partnerId)
                ->withCreatedAt(now())
        );

        $message = 'Занятие из гибкого абонемента поставлено в журнал.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return $this->journalMutationResponse($request, $message);
    }

    /**
     * Контекст модалки пробного / разового на полностью пустой ячейке.
     */
    public function emptyCellContext(GetScheduleJournalEmptyCellContextRequest $request, User $user): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $data = $request->validated();
        $filterTeamId = isset($data['context_team_id']) ? (int) $data['context_team_id'] : null;
        if ($filterTeamId !== null && $filterTeamId < 1) {
            $filterTeamId = null;
        }

        return response()->json(
            $this->emptyCellPlacementContextService->build(
                $partnerId,
                $user,
                (string) $data['occurrence_date'],
                $filterTeamId,
            )
        );
    }

    public function placeTrialLesson(
        PlaceScheduleJournalTrialLessonRequest $request,
        User $user,
    ): JsonResponse|RedirectResponse {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);
        $data = $request->validated();

        /** @var Team|null $team */
        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey((int) $data['team_id'])
            ->first();

        if (! $team) {
            return $this->journalMutationResponse(
                $request,
                'Группа не найдена.',
                ['team_id' => ['Группа не найдена.']],
            );
        }

        $occurrenceDate = \Carbon\CarbonImmutable::createFromFormat(
            'Y-m-d',
            (string) $data['occurrence_date']
        )->startOfDay();

        try {
            $status = LessonOccurrenceStatus::findActiveForPartner(
                (int) $data['lesson_occurrence_status_id'],
                $partnerId
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->journalMutationResponse(
                $request,
                'Выбранный статус не найден или неактивен.',
                ['lesson_occurrence_status_id' => ['Выбранный статус не найден или неактивен.']],
            );
        }

        $trainerProfileIds = $this->resolveVisitedTrainerProfileIds(
            $partnerId,
            $status,
            $data['trainer_profile_ids'] ?? null,
            $data['trainer_profile_id'] ?? null,
            $request,
        );
        if ($trainerProfileIds instanceof JsonResponse || $trainerProfileIds instanceof RedirectResponse) {
            return $trainerProfileIds;
        }

        $comment = isset($data['comment']) ? (string) $data['comment'] : null;

        try {
            $result = $this->trialLessonPlacementService->place(
                $partnerId,
                $user,
                $team,
                $occurrenceDate,
                $status,
                auth()->id() !== null ? (int) auth()->id() : null,
                $trainerProfileIds[0] ?? null,
                $comment,
                $trainerProfileIds,
            );
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = 'occurrence_date';
            $msgLower = mb_strtolower($msg);
            if (str_contains($msgLower, 'групп')) {
                $field = 'team_id';
            } elseif (str_contains($msgLower, 'статус')) {
                $field = 'lesson_occurrence_status_id';
            } elseif (
                str_contains($msgLower, 'пробн')
                || str_contains($msgLower, 'ученик')
            ) {
                $field = 'occurrence_date';
            }

            return $this->journalMutationResponse(
                $request,
                $msg,
                [$field => [$msg]],
            );
        } catch (Throwable $e) {
            report($e);

            return $this->journalMutationResponse(
                $request,
                'Не удалось записать пробное занятие.',
                ['occurrence_date' => ['Не удалось записать пробное занятие.']],
            );
        }

        $this->postpaySync->syncAfterOccurrenceChange(
            $partnerId,
            (int) $user->id,
            $occurrenceDate->toDateString(),
            (int) $team->id,
        );

        $this->auditLogger->record(
            AuditEvent::ScheduleTrialRegistered,
            AuditContext::make(sprintf(
                'Журнал: пробное занятие на %s; ученик: %s; группа: %s; статус: %s',
                $occurrenceDate->format('d.m.Y'),
                $user->full_name,
                $team->title,
                $status->title,
            ))
                ->withUser($user)
                ->withTargetReference('App\Models\UserTeamScheduleSlot', (int) $result['utss_id'], $user->full_name)
                ->withPartnerId($partnerId)
                ->withCreatedAt(now())
        );

        $message = 'Пробное занятие записано в журнал.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return $this->journalMutationResponse($request, $message);
    }

    public function placeSingleLesson(
        PlaceScheduleJournalSingleLessonRequest $request,
        User $user,
    ): JsonResponse|RedirectResponse {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);
        $data = $request->validated();

        /** @var Team|null $team */
        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey((int) $data['team_id'])
            ->first();

        if (! $team) {
            return $this->journalMutationResponse(
                $request,
                'Группа не найдена.',
                ['team_id' => ['Группа не найдена.']],
            );
        }

        $occurrenceDate = \Carbon\CarbonImmutable::createFromFormat(
            'Y-m-d',
            (string) $data['occurrence_date']
        )->startOfDay();

        try {
            $status = LessonOccurrenceStatus::findActiveForPartner(
                (int) $data['lesson_occurrence_status_id'],
                $partnerId
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->journalMutationResponse(
                $request,
                'Выбранный статус не найден или неактивен.',
                ['lesson_occurrence_status_id' => ['Выбранный статус не найден или неактивен.']],
            );
        }

        $trainerProfileIds = $this->resolveVisitedTrainerProfileIds(
            $partnerId,
            $status,
            $data['trainer_profile_ids'] ?? null,
            $data['trainer_profile_id'] ?? null,
            $request,
        );
        if ($trainerProfileIds instanceof JsonResponse || $trainerProfileIds instanceof RedirectResponse) {
            return $trainerProfileIds;
        }

        $comment = isset($data['comment']) ? (string) $data['comment'] : null;

        try {
            $result = $this->singleLessonPlacementService->place(
                $partnerId,
                $user,
                $team,
                $occurrenceDate,
                $status,
                [
                    'user_lesson_package_id' => $data['user_lesson_package_id'] ?? null,
                    'lesson_package_id' => $data['lesson_package_id'] ?? null,
                    'fee_amount' => $data['fee_amount'] ?? null,
                ],
                auth()->id() !== null ? (int) auth()->id() : null,
                $trainerProfileIds[0] ?? null,
                $comment,
                $trainerProfileIds,
            );
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = 'occurrence_date';
            $msgLower = mb_strtolower($msg);
            if (str_contains($msgLower, 'групп')) {
                $field = 'team_id';
            } elseif (str_contains($msgLower, 'статус')) {
                $field = 'lesson_occurrence_status_id';
            } elseif (
                str_contains($msgLower, 'стоимость')
                || str_contains($msgLower, 'сумм')
                || str_contains($msgLower, 'денеж')
            ) {
                $field = 'fee_amount';
            } elseif (
                str_contains($msgLower, 'шаблон')
            ) {
                $field = 'lesson_package_id';
            } elseif (
                str_contains($msgLower, 'назначен')
                || str_contains($msgLower, 'абонемент')
                || str_contains($msgLower, 'лимит')
                || str_contains($msgLower, 'разовое')
            ) {
                $field = isset($data['user_lesson_package_id']) && (int) $data['user_lesson_package_id'] > 0
                    ? 'user_lesson_package_id'
                    : 'lesson_package_id';
            }

            return $this->journalMutationResponse(
                $request,
                $msg,
                [$field => [$msg]],
            );
        } catch (Throwable $e) {
            report($e);

            return $this->journalMutationResponse(
                $request,
                'Не удалось записать разовое занятие.',
                ['occurrence_date' => ['Не удалось записать разовое занятие.']],
            );
        }

        $this->postpaySync->syncAfterOccurrenceChange(
            $partnerId,
            (int) $user->id,
            $occurrenceDate->toDateString(),
            (int) $team->id,
        );

        $this->auditLogger->record(
            AuditEvent::ScheduleSingleLessonRegistered,
            AuditContext::make(sprintf(
                'Журнал: разовое занятие #%d на %s; ученик: %s; группа: %s; статус: %s',
                (int) $result['user_lesson_package_id'],
                $occurrenceDate->format('d.m.Y'),
                $user->full_name,
                $team->title,
                $status->title,
            ))
                ->withUser($user)
                ->withTargetReference('App\Models\UserLessonPackage', (int) $result['user_lesson_package_id'], $user->full_name)
                ->withPartnerId($partnerId)
                ->withCreatedAt(now())
        );

        $message = 'Разовое занятие записано в журнал.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return $this->journalMutationResponse($request, $message);
    }

    public function syncUserTeams(SyncScheduleUserTeamsRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $teamIds = $request->validated()['team_ids'] ?? [];

        DB::transaction(function () use ($teamIds, $user) {
            $this->teamUserSync->syncTeamsForStudent($user, $teamIds);

            $labels = $this->teamUserSync->teamTitlesLabel($user) ?: '—';

            $this->auditLogger->record(
                AuditEvent::ScheduleUserTeamAssigned,
                AuditContext::make(sprintf(
                    'Имя: %s, %s',
                    $user->full_name,
                    'Группы: '.$labels
                ))
                    ->withUser($user)
                    ->withTargetReference('App\Models\User', (int) $user->id, $labels)
                    ->withCreatedAt(now())
            );
        });

        $user->load(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)]);
        $message = 'Группы ученика обновлены.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'team_ids' => $this->teamUserSync->teamIdsForStudent($user),
                'teams_label' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
            ]);
        }

        return $this->journalMutationResponse($request, $message);
    }

    public function getLogsData(FilterRequest $request)
    {
        return $this->buildLogDataTable('schedule');
    }

    /**
     * Тренеры учитываются только для статуса «Посетил»; иначе [].
     * При невалидном тренере возвращает journalMutationResponse.
     *
     * @return list<int>|JsonResponse|RedirectResponse
     */
    private function resolveVisitedTrainerProfileIds(
        int $partnerId,
        LessonOccurrenceStatus $status,
        mixed $rawTrainerIds,
        mixed $rawLegacySingle,
        Request $request,
    ): array|JsonResponse|RedirectResponse {
        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        if ($visitedStatusId === null || (int) $status->id !== (int) $visitedStatusId) {
            return [];
        }

        $trainerProfileIds = ScheduleOccurrenceTrainerIds::normalize($rawTrainerIds, $rawLegacySingle);
        if ($trainerProfileIds === []) {
            return [];
        }

        $validIds = ScheduleOccurrenceTrainerIds::existingForPartner($partnerId, $trainerProfileIds);
        if (count($validIds) !== count($trainerProfileIds)) {
            return $this->journalMutationResponse(
                $request,
                'Тренер не найден.',
                ['trainer_profile_ids' => ['Тренер не найден.']],
            );
        }

        return $validIds;
    }

    private function findScheduleStudentForPartner(int $partnerId, int $userId): User
    {
        return User::query()
            ->whereKey($userId)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser()
            ->firstOrFail();
    }

    private function assertScheduleStudent(User $user, int $partnerId): void
    {
        $isScheduleStudent = User::query()
            ->whereKey($user->id)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser()
            ->exists();

        if (! $isScheduleStudent) {
            abort(404);
        }
    }

    private function trainerOptionsForPartner(int $partnerId)
    {
        return TrainerProfile::query()
            ->with('user')
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function resolveScheduleContextTeamId(User $user, ?int $preferredTeamId = null): ?int
    {
        $teamIds = $this->teamUserSync->teamIdsForStudent($user);
        if ($teamIds === []) {
            return null;
        }

        if ($preferredTeamId && in_array($preferredTeamId, $teamIds, true)) {
            return $preferredTeamId;
        }

        return $teamIds[0];
    }

    /**
     * @return list<int>
     */
    private function teamDefaultTrainerProfileIds(int $partnerId, ?int $teamId): array
    {
        if (! $teamId) {
            return [];
        }

        return TrainerProfile::query()
            ->select('trainer_profiles.id')
            ->join('team_trainer', 'team_trainer.trainer_profile_id', '=', 'trainer_profiles.id')
            ->where('team_trainer.partner_id', $partnerId)
            ->where('team_trainer.team_id', $teamId)
            ->orderBy('team_trainer.id')
            ->pluck('trainer_profiles.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function teamDefaultTrainerProfile(int $partnerId, ?int $teamId): ?TrainerProfile
    {
        $ids = $this->teamDefaultTrainerProfileIds($partnerId, $teamId);
        if ($ids === []) {
            return null;
        }

        return TrainerProfile::query()
            ->with('user')
            ->whereKey($ids[0])
            ->first();
    }

    private function trainerDisplayName(?TrainerProfile $profile): string
    {
        if (! $profile) {
            return 'Без тренера';
        }

        $name = trim($profile->user?->full_name ?? '');

        return $name !== '' ? $name : 'Без тренера';
    }

    private function packageHoverForPostpayTeam(int $userId, string $occurrenceDate, ?int $teamId): ?string
    {
        if ($teamId === null || $teamId <= 0) {
            return null;
        }

        foreach ($this->postpayJournal->postpayTeamsForDate($userId, $occurrenceDate) as $team) {
            if ((int) ($team['id'] ?? 0) !== $teamId) {
                continue;
            }

            $cents = Money::toCents($team['price_per_lesson'] ?? 0);

            return ScheduleJournalMonthService::postpayPackageHoverLabel($cents);
        }

        return ScheduleJournalMonthService::postpayPackageHoverLabel(null);
    }

    /**
     * AJAX → JSON {success,message,errors?}; non-AJAX → 302 на /schedule (+ flash/errors).
     * Не отдаёт пустой 200 при нативном submit без JS.
     *
     * @param  array<string, list<string>>  $errors
     */
    private function journalMutationResponse(
        Request $request,
        string $message,
        array $errors = [],
        int $errorStatus = 422,
    ): JsonResponse|RedirectResponse {
        if ($errors === []) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()
                ->route('schedule.index')
                ->with('status', $message);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $errorStatus);
        }

        return redirect()
            ->route('schedule.index')
            ->withInput()
            ->withErrors($errors)
            ->with('error', $message);
    }
}
