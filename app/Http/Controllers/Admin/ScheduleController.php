<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Enums\AuditEvent;
use App\Http\Requests\Admin\GetScheduleCellContextRequest;
use App\Http\Requests\Admin\GetScheduleUserScheduleInfoRequest;
use App\Http\Requests\Admin\SetScheduleUserGroupRequest;
use App\Http\Requests\Admin\SyncScheduleUserTeamsRequest;
use App\Http\Requests\Admin\UpdateScheduleCellRequest;
use App\Http\Requests\Team\FilterRequest;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\TeamUserSyncService;
use App\Models\TrainerProfile;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ScheduleUser;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Team;
use App\Models\LessonOccurrenceStatus;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Support\BuildsLogTable;
use App\Services\PartnerContext;


class ScheduleController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
        private readonly TeamUserSyncService $teamUserSync,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(Request $request)
    {
        // 1) Текущий партнер
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        // 2) Статусы занятий партнёра (для ячеек — все; в селекте — только активные)
        $statusesForDisplay = LessonOccurrenceStatus::query()
            ->forPartner($partnerId)
            ->ordered()
            ->get();
        $availableStatuses = $statusesForDisplay->where('is_active', true)->values();

        // 3) Фильтры: год, месяц и группа
        $year    = $request->get('year',  date('Y'));
        $month   = $request->get('month', date('m'));
        $team_id = $request->get('team',  'all');

        // 4) Начало и конец месяца
        $startOfMonth = Carbon::createFromDate($year, $month, 1);
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // 5) Ученики: активные, партнёр, системная роль user
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

        // 6) Записи расписания за месяц — по выбранным users
        $scheduleEntries = ScheduleUser::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(fn($item) => $item->user_id . '_' . Carbon::parse($item->date)->format('Y-m-d'));

    // 7) Статусы оплат — только для пользователей этого партнера
    $userPrices = DB::table('users_prices')
        ->select('user_id', 'is_paid')
        ->whereIn('user_id', $users->pluck('id'))
        ->whereYear('new_month',  $year)
        ->whereMonth('new_month', $month)
        ->get()
        ->keyBy('user_id');

    // 8) Список групп (команд) фильтра — только для текущего партнёра
    $teams = Team::where('partner_id', $partnerId)
        ->where('is_enabled', 1)
        ->orderBy('order_by')
        ->get();

    // 9) Дни недели для выбранной группы
    $teamWeekdays = [];
    if ($team_id !== 'all' && $team_id !== 'none') {
        $teamWeekdays = DB::table('team_weekdays')
            ->where('team_id', $team_id)
            ->pluck('weekday_id')
            ->toArray();
    }

    $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);

    return view('admin.schedule.index', array_merge(compact(
        'year',
        'month',
        'team_id',
        'users',
        'scheduleEntries',
        'userPrices',
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

    public function cellContext(GetScheduleCellContextRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $user = $this->findScheduleStudentForPartner($partnerId, (int) $data['user_id']);
        $user->load('teams');

        $entry = ScheduleUser::query()
            ->with('trainerProfile.user')
            ->where('user_id', $user->id)
            ->where('date', $data['date'])
            ->first();

        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        $contextTeamId = $this->resolveScheduleContextTeamId(
            $user,
            isset($data['context_team_id']) ? (int) $data['context_team_id'] : null
        );
        $teamDefault = $this->teamDefaultTrainerProfile($partnerId, $contextTeamId);
        $trainers = $this->trainerOptionsForPartner($partnerId);

        $currentStatusId = $entry?->lesson_occurrence_status_id;
        $isVisitedEntry = $visitedStatusId !== null
            && $currentStatusId !== null
            && (int) $currentStatusId === (int) $visitedStatusId;

        $trainerForSelect = '';
        if ($isVisitedEntry) {
            $trainerForSelect = $entry->trainer_profile_id !== null
                ? (string) $entry->trainer_profile_id
                : '';
        }

        return response()->json([
            'visited_status_id' => $visitedStatusId,
            'current_status_id' => $currentStatusId,
            'team_id' => $contextTeamId,
            'team_ids' => $this->teamUserSync->teamIdsForStudent($user),
            'teams_label' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
            'team_default_trainer_profile_id' => $teamDefault?->id,
            'trainer_profile_id_for_select' => $isVisitedEntry ? $trainerForSelect : null,
            'trainers' => $trainers->map(fn (TrainerProfile $profile) => [
                'id' => $profile->id,
                'name' => $this->trainerDisplayName($profile),
            ])->values(),
        ]);
    }

    public function update(UpdateScheduleCellRequest $request)
    {
        $authorId = auth()->id();
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        DB::transaction(function () use ($authorId, $partnerId, $data) {
            $user = $this->findScheduleStudentForPartner($partnerId, (int) $data['user_id']);

            $status = LessonOccurrenceStatus::findActiveForPartner(
                (int) $data['lesson_occurrence_status_id'],
                $partnerId
            );
            $descriptionText = $data['description'] ?? '';

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
                    throw new \InvalidArgumentException('Тренер не найден.');
                }
            }

            $existingSchedule = ScheduleUser::query()
                ->with(['statusRelation', 'trainerProfile.user'])
                ->where('user_id', $user->id)
                ->where('date', $data['date'])
                ->first();

            $oldStatusName = $existingSchedule?->statusRelation?->title ?? 'не было';
            $oldTrainerName = $this->trainerDisplayName($existingSchedule?->trainerProfile);

            $schedule = ScheduleUser::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $data['date'],
                ],
                [
                    'lesson_occurrence_status_id' => $status->id,
                    'description' => $descriptionText,
                    'trainer_profile_id' => $trainerProfileId,
                ]
            );
            $schedule->load(['statusRelation', 'trainerProfile.user']);

            $newStatusName = $schedule->statusRelation?->title ?? 'неопределён';
            $newTrainerName = $this->trainerDisplayName($schedule->trainerProfile);

            $formattedDate = Carbon::parse($data['date'])->format('d.m.Y');

            $this->auditLogger->record(
                AuditEvent::ScheduleDayUpdated,
                AuditContext::make(sprintf(
                    'Дата: "%s", Имя: "%s",%sСтатус до: "%s", Статус после: "%s",%sТренер до: "%s", Тренер после: "%s",%sКомментарий: "%s"',
                    $formattedDate,
                    $user->full_name,
                    "\n",
                    $oldStatusName,
                    $newStatusName,
                    "\n",
                    $oldTrainerName,
                    $newTrainerName,
                    "\n",
                    $descriptionText
                ))
                    ->withUser($user)
                    ->withTargetReference('App\Models\ScheduleUser', (int) $user->id, $user->full_name)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        return response()->json(['success' => true]);
    }

    public function getUserInfo(User $user)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $user->load(['teams' => function ($q) use ($partnerId) {
            $q->where('teams.partner_id', $partnerId)->with('weekdays');
        }]);

        $contextTeam = $user->teams->first();
        $teamWeekdays = [];
        if ($contextTeam) {
            foreach ($contextTeam->weekdays as $wd) {
                $teamWeekdays[] = $wd->id;
            }
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'team_id'    => $contextTeam?->id,
                'team_ids'   => $this->teamUserSync->teamIdsForStudent($user),
                'team_title' => $contextTeam?->title,
                'team_titles'=> $this->teamUserSync->teamTitlesLabel($user),
                'weekdays'   => $teamWeekdays,
            ],
        ]);
    }

    public function getUserScheduleInfo(GetScheduleUserScheduleInfoRequest $request, User $user)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $contextTeamId = $request->filled('context_team_id')
            ? (int) $request->input('context_team_id')
            : null;

        $contextTeam = $this->resolveContextTeamModel($user, $partnerId, $contextTeamId);
        $groupWeekdays = $this->weekdayIdsForTeam($contextTeam);

        // Пример: хотим по умолчанию "от" = сегодня, "до" = ближайший 31 августа
        $today = now()->format('Y-m-d');
        $year  = now()->year;
        $aug31 = Carbon::create($year, 8, 31);
        if (now()->greaterThan($aug31)) {
            $year++;
        }
        $defaultTo = Carbon::create($year, 8, 31)->format('Y-m-d');

        $teamIds = $this->teamUserSync->teamIdsForStudent($user->loadMissing([
            'teams' => fn ($q) => $q->where('teams.partner_id', $partnerId),
        ]));

        return response()->json([
            'success'       => true,
            'user'          => [
                'id'          => $user->id,
                'name'        => $user->name,
                'team_id'     => $contextTeam?->id,
                'team_ids'    => $teamIds,
                'team_title'  => $contextTeam?->title,
                'team_titles' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
            ],
            'groupWeekdays' => $groupWeekdays,
            'defaultFrom'   => $today,
            'defaultTo'     => $defaultTo,
        ]);
    }

    public function syncUserTeams(SyncScheduleUserTeamsRequest $request, User $user)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $teamIds = $request->validated()['team_ids'] ?? [];

        DB::transaction(function () use ($partnerId, $teamIds, $user) {
            $this->teamUserSync->syncTeamsForStudent($user, $teamIds);

            $labels = $this->teamUserSync->teamTitlesLabel($user) ?: '—';

            $this->auditLogger->record(
                AuditEvent::ScheduleUserTeamAssigned,
                AuditContext::make(sprintf(
                    'Имя: %s, %s',
                    $user->full_name,
                    'Группы: ' . $labels
                ))
                    ->withUser($user)
                    ->withTargetReference('App\Models\ScheduleUser', (int) $user->id, $labels)
                    ->withCreatedAt(now())
            );
        });

        $user->load(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)]);

        return response()->json([
            'success' => true,
            'message' => 'Группы ученика обновлены.',
            'team_ids' => $this->teamUserSync->teamIdsForStudent($user),
            'teams_label' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
        ]);
    }

    //Установка группы через расписание
    public function setUserGroup(SetScheduleUserGroupRequest $request, User $user)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        $validated = $request->validated();
        $teamId = isset($validated['team_id']) ? (int) $validated['team_id'] : null;

        $message = 'Группы ученика обновлены.';

        DB::transaction(function () use ($partnerId, $teamId, $user, &$message) {
            if ($teamId) {
                $team = Team::where('id', $teamId)
                    ->where('partner_id', $partnerId)
                    ->firstOrFail();

                $this->teamUserSync->attachTeamForStudent($user, $teamId);

                $this->auditLogger->record(
                    AuditEvent::ScheduleUserTeamAssigned,
                    AuditContext::make(sprintf(
                        'Имя: %s, %s',
                        $user->full_name,
                        'Добавлена группа: ' . $team->title
                    ))
                        ->withUser($user)
                        ->withTargetReference('App\Models\ScheduleUser', (int) $team->id, $team->title)
                        ->withCreatedAt(now())
                );

                $message = 'Группа успешно добавлена ученику.';
            } else {
                $this->teamUserSync->detachAllTeamsForStudent($user);

                $this->auditLogger->record(
                    AuditEvent::ScheduleUserTeamAssigned,
                    AuditContext::make(sprintf(
                        'Имя: %s, %s',
                        $user->full_name,
                        'Сняты все группы'
                    ))
                        ->withUser($user)
                        ->withTargetReference('App\Models\ScheduleUser', 0, '—')
                        ->withCreatedAt(now())
                );

                $message = 'Ученик снят со всех групп.';
            }
        });

        $user->load(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'team_ids' => $this->teamUserSync->teamIdsForStudent($user),
            'teams_label' => $this->teamUserSync->teamTitlesLabel($user) ?: null,
        ]);
    }

    //Установка индивидуального расписания юзеру
    public function updateUserScheduleRange(Request $request, User $user)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertScheduleStudent($user, $partnerId);

        // 3) Валидация
        $data = $request->validate([
            'weekdays'   => 'array',
            'weekdays.*' => 'in:1,2,3,4,5,6,7',
            'date_from'  => 'required|date',
            'date_to'    => 'required|date|after_or_equal:date_from',
        ]);

        $weekdays = $data['weekdays'] ?? [];
        $from     = Carbon::parse($data['date_from']);
        $to       = Carbon::parse($data['date_to']);


        $defaultVisitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        if (! $defaultVisitedStatusId) {
            return response()->json([
                'success' => false,
                'message' => 'Системный статус «Посетил» не найден.',
            ], 422);
        }

        // 4) Формируем массив вставок
        $period  = CarbonPeriod::create($from, $to);
        $inserts = [];
        foreach ($period as $day) {
            if (in_array($day->isoWeekday(), $weekdays)) {
                $inserts[] = [
                    'user_id'     => $user->id,
                    'date'        => $day->toDateString(),
                    'lesson_occurrence_status_id' => $defaultVisitedStatusId,
                    'description' => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        DB::transaction(function () use ($user, $from, $to, $inserts, $partnerId, $weekdays) {
            // 5) Удаляем старые через Eloquent, чтобы не запутываться с join
            $deleted = ScheduleUser::where('user_id', $user->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->delete();

            // 6) Вставляем новые
            if (!empty($inserts)) {
                $inserted = ScheduleUser::insert($inserts);
            }

            $map   = [1=>'пн',2=>'вт',3=>'ср',4=>'чт',5=>'пт',6=>'суб',7=>'вск'];
            $days  = implode(', ', array_map(fn($d)=> $map[$d] ?? $d, $weekdays));

        $this->auditLogger->record(
            AuditEvent::ScheduleUserRangeUpdated,
            AuditContext::make(sprintf(
                "Пользователь: %s (ID:%d)\nПериод: %s - %s\nДни: %s",
                $user->name,
                $user->id,
                $from->format('d.m.Y'),
                $to->format('d.m.Y'),
                $days
            ))
                ->withUser($user)
                ->withTargetReference('App\Models\ScheduleUser', (int) $user->id, $user->full_name)
                ->withCreatedAt(now())
        );
    });

        return response()->json([
            'success' => true,
            'message' => 'Расписание успешно обновлено.',
        ]);
    }


    //Настройка логов
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

    private function resolveContextTeamModel(User $user, int $partnerId, ?int $preferredTeamId = null): ?Team
    {
        $user->loadMissing([
            'teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->with('weekdays'),
        ]);

        if ($user->teams->isEmpty()) {
            return null;
        }

        if ($preferredTeamId) {
            $match = $user->teams->firstWhere('id', $preferredTeamId);
            if ($match) {
                return $match;
            }
        }

        return $user->teams->first();
    }

    /**
     * @return int[]
     */
    private function weekdayIdsForTeam(?Team $team): array
    {
        if (! $team) {
            return [];
        }

        $team->loadMissing('weekdays');

        return $team->weekdays
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
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
}





