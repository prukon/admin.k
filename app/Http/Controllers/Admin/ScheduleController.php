<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\MyLog;
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


class ScheduleController extends Controller
{
    use BuildsLogTable;

    public function index(Request $request)
    {
        // 1) Текущий партнер
        $partnerId = app('current_partner')->id;

        // 2) Доступные статусы: свои + системные
        //    ИЗМЕНЕНИЕ #1: вместо фильтра только по partner_id делаем OR с is_system = 1
        $availableStatuses = Status::where(function($q) use ($partnerId) {
            $q->where('partner_id', $partnerId)
                ->orWhere('is_system', 1);
        })
            ->orderBy('is_system', 'desc')
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

    // 10) Рендерим
    return view('admin.schedule', compact(
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
        'availableStatuses'
    ));
}

//    обновление расписания ячейки
    public function update(Request $request)
    {
        // Авторизованный пользователь
        $authorId  = auth()->id();
        // ИЗМЕНЕНИЕ #1: получаем partner_id из контекста (не из $request->user())
        $partnerId = app('current_partner')->id;

        // 1) Валидация входящих данных
        $data = $request->validate([
            'user_id'     => 'required|integer|exists:users,id',
            'date'        => 'required|date_format:Y-m-d',
            'status_id'   => 'required|exists:statuses,id',
            'description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($authorId, $partnerId, $data) {
            // 2) ИЗМЕНЕНИЕ #2: ищем пользователя только в рамках партнёра
            $user = User::where('id', $data['user_id'])
                ->where('partner_id', $partnerId)
                ->firstOrFail();

            // 3) ИЗМЕНЕНИЕ #3: ищем статус только для этого партнёра или системный
            $status = Status::where('id', $data['status_id'])
                ->where(function ($q) use ($partnerId) {
                    $q->where('partner_id', $partnerId)
                        ->orWhere('is_system', 1);
                })
                ->firstOrFail();

            $descriptionText = $data['description'] ?? '';

            // 4) Получаем существующую запись расписания (если есть)
            $existingSchedule = ScheduleUser::where('user_id', $user->id)
                ->where('date', $data['date'])
                ->first();

            $oldStatusName = $existingSchedule && $existingSchedule->statusRelation
                ? $existingSchedule->statusRelation->name
                : 'не было';

            // 5) Создаём или обновляем запись расписания
            $schedule = ScheduleUser::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date'    => $data['date'],
                ],
                [
                    'status_id'   => $status->id,
                    'description' => $descriptionText,
                ]
            );
            $schedule->refresh();

            $newStatusName = $schedule->statusRelation
                ? $schedule->statusRelation->name
                : 'неопределён';

            // 6) Форматируем дату для лога
            $formattedDate = Carbon::parse($data['date'])->format('d.m.Y');

            // 7) Запись в лог с указанием partner_id
            MyLog::create([
                'type'        => 9,
                'action'      => 93,
                'target_type'  => 'App\Models\ScheduleUser',
                'target_id'    => $user->id,
                'target_label' => $user->full_name,
                'user_id'   => $user->id,
                'description' => sprintf(
                    'Дата: "%s", Имя: "%s",%sСтатус до: "%s", Статус после: "%s",%sКомментарий: "%s"',
                    $formattedDate,
                    $user->full_name,
                    "\n",
                    $oldStatusName,
                    $newStatusName,
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
        $partnerId = app('current_partner')->id;
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
        $partnerId = app('current_partner')->id;

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
        $partnerId = app('current_partner')->id;

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
        $partnerId = app('current_partner')->id;

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


        // 4) Формируем массив вставок
        $period  = CarbonPeriod::create($from, $to);
        $inserts = [];
        foreach ($period as $day) {
            if (in_array($day->isoWeekday(), $weekdays)) {
                $inserts[] = [
                    'user_id'     => $user->id,
                    'date'        => $day->toDateString(),
                    'status_id'   => 2,
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
}





