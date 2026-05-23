<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\GetScheduleCellContextRequest;
use App\Http\Requests\Admin\UpdateScheduleCellRequest;
use App\Http\Requests\Team\FilterRequest;
use App\Models\MyLog;
use App\Models\TrainerProfile;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ScheduleUser;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Team;
use App\Models\Status;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Support\BuildsLogTable;
use App\Services\PartnerContext;


class ScheduleController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index(Request $request)
    {
        // 1) Текущий партнер
        $partnerId = $this->requirePartnerId();

        // 2) Кастомные статусы партнёра + общие системные (partner_id IS NULL)
        $availableStatuses = Status::query()
            ->forSchedulePartner($partnerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 3) Фильтры: год, месяц и группа
        $year    = $request->get('year',  date('Y'));
        $month   = $request->get('month', date('m'));
        $team_id = $request->get('team',  'all');

        // 4) Начало и конец месяца
        $startOfMonth = Carbon::createFromDate($year, $month, 1);
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // 5) Пользователи: только включенные и только этого партнера
        $usersQuery = User::where('partner_id', $partnerId)
            ->where('is_enabled', 1);

        if ($team_id !== 'all') {
            if ($team_id === 'none') {
                $usersQuery->whereNull('team_id');
            } else {
                $usersQuery->where('team_id', $team_id);
            }
        }

        $users = $usersQuery->orderBy('lastname')->get();

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

    $visitedStatusId = Status::globalVisitedId();

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
        'visitedStatusId',
    ), [
        'activeTab' => 'journal',
    ]));
}

    public function cellContext(GetScheduleCellContextRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $user = User::query()
            ->where('id', $data['user_id'])
            ->where('partner_id', $partnerId)
            ->firstOrFail();

        $entry = ScheduleUser::query()
            ->with('trainerProfile.user')
            ->where('user_id', $user->id)
            ->where('date', $data['date'])
            ->first();

        $visitedStatusId = Status::globalVisitedId();
        $teamDefault = $this->teamDefaultTrainerProfile($partnerId, $user->team_id);
        $trainers = $this->trainerOptionsForPartner($partnerId);

        $currentStatusId = $entry?->status_id;
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
            'team_id' => $user->team_id ? (int) $user->team_id : null,
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
            $user = User::query()
                ->where('id', $data['user_id'])
                ->where('partner_id', $partnerId)
                ->firstOrFail();

            $status = Status::findForSchedulePartner((int) $data['status_id'], $partnerId);
            $descriptionText = $data['description'] ?? '';

            $visitedStatusId = Status::globalVisitedId();
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

            $oldStatusName = $existingSchedule?->statusRelation?->name ?? 'не было';
            $oldTrainerName = $this->trainerDisplayName($existingSchedule?->trainerProfile);

            $schedule = ScheduleUser::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $data['date'],
                ],
                [
                    'status_id' => $status->id,
                    'description' => $descriptionText,
                    'trainer_profile_id' => $trainerProfileId,
                ]
            );
            $schedule->load(['statusRelation', 'trainerProfile.user']);

            $newStatusName = $schedule->statusRelation?->name ?? 'неопределён';
            $newTrainerName = $this->trainerDisplayName($schedule->trainerProfile);

            $formattedDate = Carbon::parse($data['date'])->format('d.m.Y');

            MyLog::create([
                'type' => 9,
                'action' => 93,
                'target_type' => 'App\Models\ScheduleUser',
                'target_id' => $user->id,
                'target_label' => $user->full_name,
                'user_id' => $user->id,
                'partner_id' => $partnerId,
                'description' => sprintf(
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
                ),
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function getUserInfo(User $user)
    {
        // ИЗМЕНЕНИЕ #1: ограничиваем доступ – работаем только с пользователями своего партнёра
        $partnerId = $this->requirePartnerId();
        if ($user->partner_id !== $partnerId) {
            abort(404); // или return response()->json(['error'=>'Not found'], 404);
        }

        // ИЗМЕНЕНИЕ #2: при загрузке команды гарантируем, что это команда текущего партнёра
        // + сразу загружаем её weekdays
        $user->load(['team' => function($q) use ($partnerId) {
            $q->where('partner_id', $partnerId)
                ->with('weekdays');
        }]);

        // Собираем ID рабочих дней команды
        $teamWeekdays = [];
        if ($user->team) {
            foreach ($user->team->weekdays as $wd) {
                $teamWeekdays[] = $wd->id;
            }
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'team_id'    => $user->team?->id,
            'team_title' => $user->team?->title,
            'weekdays'   => $teamWeekdays,
        ],
    ]);
}

    //Вызов Личное расписание ученика
    public function getUserScheduleInfo(User $user)
    {
        // ИЗМЕНЕНИЕ #1: получаем текущего партнёра из контекста
        $partnerId = $this->requirePartnerId();

        // ИЗМЕНЕНИЕ #2: убеждаемся, что пользователь принадлежит этому партнёру
        if ($user->partner_id !== $partnerId) {
            abort(404); // или return response()->json(['error'=>'Not found'], 404);
        }

        // ИЗМЕНЕНИЕ #3: загружаем team только в рамках партнёра и её weekdays
        $user->load(['team' => function($q) use ($partnerId) {
            $q->where('partner_id', $partnerId)
                ->with('weekdays');
        }]);

        // Собираем массив ID-шников дней недели группы
        $groupWeekdays = [];
        if ($user->team) {
            foreach ($user->team->weekdays as $w) {
                $groupWeekdays[] = $w->id;
            }
        }

        // Пример: хотим по умолчанию "от" = сегодня, "до" = ближайший 31 августа
        $today = now()->format('Y-m-d');
        $year  = now()->year;
        $aug31 = Carbon::create($year, 8, 31);
        if (now()->greaterThan($aug31)) {
            $year++;
        }
        $defaultTo = Carbon::create($year, 8, 31)->format('Y-m-d');

        return response()->json([
            'success'       => true,
            'user'          => [
                'id'         => $user->id,
                'name'       => $user->name,
                'team_id'    => $user->team?->id,
            'team_title' => $user->team?->title,
        ],
        'groupWeekdays' => $groupWeekdays,
        'defaultFrom'   => $today,
        'defaultTo'     => $defaultTo,
    ]);
}

    //Установка группы через расписание
    public function setUserGroup(Request $request, User $user)
    {
        // ИЗМЕНЕНИЕ #1: получаем текущего партнёра из контекста
        $partnerId = $this->requirePartnerId();

        // ИЗМЕНЕНИЕ #2: проверяем, что пользователь принадлежит этому партнёру
        if ($user->partner_id !== $partnerId) {
            abort(404);
        }

        // 1) Валидация входных данных
        $request->validate([
            'team_id' => 'nullable|exists:teams,id',
        ]);

        DB::transaction(function () use ($partnerId, $request, $user) {
            // ИЗМЕНЕНИЕ #3: удостоверяемся, что выбранная команда также относится к этому партнёру
            if ($request->filled('team_id')) {
                $team = Team::where('id', $request->team_id)
                    ->where('partner_id', $partnerId)
                    ->firstOrFail();
            } else {
                $team = null;
            }

            // 2) Обновляем группу пользователя
            $user->update([
                'team_id' => $request->team_id,
            ]);

            // 3) Логируем действие с указанием partner_id
            MyLog::create([
                'type'        => 9,
                'action'      => 94,
                'user_id'   => $user->id,
                'target_type'  => 'App\Models\ScheduleUser',
                'target_id'    =>  $team->id,
                'target_label' => $team->title,
                'description' => sprintf(
                            'Имя: %s, Установлена группа: %s',
                            $user->full_name,
                            $team?->title ?? '—'
            ),
            'created_at'  => now(),
        ]);
    });

        return response()->json([
            'success' => true,
            'message' => 'Группа успешно назначена.',
        ]);
    }

    //Установка индивидуального расписания юзеру
    public function updateUserScheduleRange(Request $request, User $user)
    {
        // 1) Контекст
        $partnerId = $this->requirePartnerId();

        // 2) Проверяем, что пользователь именно нашего партнёра
        if ($user->partner_id !== $partnerId) {
            abort(404);
        }

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


        $defaultVisitedStatusId = Status::globalVisitedId();
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
                    'status_id'   => $defaultVisitedStatusId,
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

        MyLog::create([
            'type'        => 9,
            'action'      => 95,
            'target_type'  => 'App\Models\ScheduleUser',
            'target_id'    =>  $user->id,
            'user_id'   => $user->id,
            'target_label' => $user->full_name,
            'description' => sprintf(
                "Пользователь: %s (ID:%d)\nПериод: %s - %s\nДни: %s",
                $user->name,
                $user->id,
                $from->format('d.m.Y'),
                $to->format('d.m.Y'),
                $days
            ),
            'created_at'  => now(),
        ]);
    });

        return response()->json([
            'success' => true,
            'message' => 'Расписание успешно обновлено.',
        ]);
    }


    //Настройка логов
    public function getLogsData(FilterRequest $request)
    {
        return $this->buildLogDataTable(9);
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





