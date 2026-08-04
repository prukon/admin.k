<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\GetScheduleCellContextRequest;
use App\Http\Requests\Admin\GetScheduleJournalAbonementContextRequest;
use App\Http\Requests\Admin\PlaceScheduleJournalFixedAbonementRequest;
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
use App\Services\Schedule\JournalFixedAbonementPlacementService;
use App\Services\Schedule\ScheduleJournalMonthService;
use App\Services\TeamUserSyncService;
use App\Services\Postpay\PostpayJournalService;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Support\BuildsLogTable;
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

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
        private readonly TeamUserSyncService $teamUserSync,
        private readonly ScheduleJournalMonthService $journalMonthService,
        private readonly JournalFixedAbonementPlacementService $fixedPlacementService,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
        private readonly PostpayJournalService $postpayJournal,
        private readonly PostpayUsersPriceSync $postpaySync,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        $statusesForDisplay = LessonOccurrenceStatus::query()
            ->forPartner($partnerId)
            ->ordered()
            ->get();
        $availableStatuses = $statusesForDisplay->where('is_active', true)->values();

        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $team_id = $request->get('team', 'all');

        $startOfMonth = Carbon::createFromDate((int) $year, (int) $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $usersQuery = User::where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser();

        if ($team_id !== 'all') {
            $usersQuery->filterByStudentTeam($partnerId, $team_id);
        }

        $users = $usersQuery
            ->with(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)])
            ->orderBy('lastname')
            ->get();

        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        $journalOccurrences = $this->journalMonthService->occurrencesByUserDate(
            $partnerId,
            $userIds,
            $startOfMonth,
            $endOfMonth,
            $team_id,
        );

        $journalAssignments = [];
        foreach ($users as $user) {
            $journalAssignments[(int) $user->id] = $this->journalMonthService->fixedAssignmentsForUser(
                $partnerId,
                (int) $user->id
            );
        }

        $monthFirst = $startOfMonth->format('Y-m-d');
        $postpayUsers = $this->postpayJournal->postpayUserFlags($userIds, $monthFirst, (string) $team_id);
        $postpayLockedUsers = $this->postpayJournal->postpayLockedUserFlags($userIds, $monthFirst, (string) $team_id);

        $userPrices = DB::table('users_prices')
            ->select('user_id', 'is_paid', 'is_manual_paid')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereYear('new_month', $year)
            ->whereMonth('new_month', $month)
            ->get()
            ->keyBy('user_id');

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

        return view('admin.schedule.index', array_merge(compact(
            'year',
            'month',
            'team_id',
            'users',
            'journalOccurrences',
            'journalAssignments',
            'userPrices',
            'postpayUsers',
            'postpayLockedUsers',
            'teams',
            'startOfMonth',
            'endOfMonth',
            'teamWeekdays',
            'availableStatuses',
            'statusesForDisplay',
            'visitedStatusId',
        ), [
            'activeTab' => 'journal',
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
        $teamDefault = $this->teamDefaultTrainerProfile($partnerId, $contextTeamId);
        $trainers = $this->trainerOptionsForPartner($partnerId);

        $currentStatusId = $selected['lesson_occurrence_status_id'] ?? null;
        $isVisitedEntry = $visitedStatusId !== null
            && $currentStatusId !== null
            && (int) $currentStatusId === (int) $visitedStatusId;

        $trainerForSelect = '';
        if ($isVisitedEntry && ! empty($selected['trainer_profile_id'])) {
            $trainerForSelect = (string) $selected['trainer_profile_id'];
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
            'team_default_trainer_profile_id' => $teamDefault?->id,
            'trainer_profile_id_for_select' => $isVisitedEntry ? $trainerForSelect : null,
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
            DB::transaction(function () use ($authorId, $partnerId, $data) {
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
                $trainerProfileId = ($visitedStatusId !== null && (int) $status->id === $visitedStatusId)
                    ? ($data['trainer_profile_id'] ?? null)
                    : null;

                if ($trainerProfileId !== null) {
                    $validTrainer = TrainerProfile::query()
                        ->where('partner_id', $partnerId)
                        ->whereKey($trainerProfileId)
                        ->exists();

                    if (! $validTrainer) {
                        throw new InvalidArgumentException('Тренер не найден.');
                    }
                }

                $ulpId = $utss->user_lesson_package_id !== null ? (int) $utss->user_lesson_package_id : null;
                $prevEvent = UserLessonOccurrenceStatusEvent::query()
                    ->with(['lessonOccurrenceStatus', 'trainerProfile.user'])
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
                $oldTrainerName = $this->trainerDisplayName($prevEvent?->trainerProfile);
                $comment = isset($data['comment']) ? (string) $data['comment'] : null;

                $this->occurrenceStatusService->apply(
                    $partnerId,
                    (int) $user->id,
                    (int) $utss->team_schedule_slot_id,
                    $occurrenceDate,
                    $ulpId,
                    $status,
                    $authorId !== null ? (int) $authorId : null,
                    $trainerProfileId !== null ? (int) $trainerProfileId : null,
                    $comment,
                );

                if ($teamIdForLock > 0) {
                    $this->postpaySync->syncAfterOccurrenceChange(
                        $partnerId,
                        (int) $user->id,
                        $occurrenceDate,
                        $teamIdForLock
                    );
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
                        $oldTrainerName,
                        $trainerProfileId
                            ? $this->trainerDisplayName(
                                TrainerProfile::query()->with('user')->find($trainerProfileId)
                            )
                            : 'Без тренера',
                        "\n",
                        (string) ($comment ?? '')
                    ))
                        ->withUser($user)
                        ->withTargetReference('App\Models\UserTeamScheduleSlot', (int) $utss->id, $user->full_name)
                        ->withPartnerId($partnerId)
                        ->withCreatedAt(now())
                );
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

        return $this->journalMutationResponse($request, 'Статус занятия сохранён.');
    }

    public function abonementContext(GetScheduleJournalAbonementContextRequest $request, User $user): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $user->load(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->with('weekdays')]);

        $assignments = $this->journalMonthService->fixedAssignmentsForUser($partnerId, (int) $user->id);
        $teamsPayload = $user->teams->map(function (Team $team) {
            return [
                'id' => (int) $team->id,
                'title' => (string) $team->title,
                'weekdays' => $team->weekdays->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => (int) $user->id,
                'name' => $user->full_name ?: $user->name,
                'team_ids' => $this->teamUserSync->teamIdsForStudent($user),
                'teams_label' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
            ],
            'teams' => $teamsPayload,
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

    private function teamDefaultTrainerProfile(int $partnerId, ?int $teamId): ?TrainerProfile
    {
        if (! $teamId) {
            return null;
        }

        return TrainerProfile::query()
            ->with('user')
            ->select('trainer_profiles.*')
            ->join('team_trainer', 'team_trainer.trainer_profile_id', '=', 'trainer_profiles.id')
            ->where('team_trainer.partner_id', $partnerId)
            ->where('team_trainer.team_id', $teamId)
            ->orderBy('team_trainer.id')
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
