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


class ScheduleController extends Controller
{
    public function index(Request $request)
    {

        $partnerId = $request->user()->partner_id ?? null;

        $availableStatuses = Status::where('partner_id', $partnerId)
            // ->where('is_deleted', false)
            ->orderBy('is_system', 'desc')
            ->get();


        // Фильтры: год, месяц и группа
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $team_id = $request->get('team', 'all'); // значения: all, none или id команды

        // Начало и конец месяца
        $startOfMonth = Carbon::createFromDate($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Получаем пользователей, фильтруя по группе если требуется
        $usersQuery = User::query()->where('is_enabled', 1);
        if ($team_id != 'all') {
            if ($team_id == 'none') {
                $usersQuery->whereNull('team_id');
            } else {
                $usersQuery->where('team_id', $team_id);
            }
        }
        $users = $usersQuery->orderBy('name')->get();

        // Получаем записи расписания для выбранных пользователей за месяц
        $scheduleEntries = ScheduleUser::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy(function ($item) {
                return $item->user_id . '_' . Carbon::parse($item->date)->format('Y-m-d');
            });

        // Получаем статус оплаты из таблицы users_prices по полю new_month
        $userPrices = \DB::table('users_prices')
            ->select('user_id', 'is_paid')
            ->whereYear('new_month', $year)
            ->whereMonth('new_month', $month)
            ->get()
            ->keyBy('user_id');

        // Получаем список групп (команд) для фильтра
        $teams = Team::where('is_enabled', 1)->orderBy('order_by')->get();

        // Если выбрана конкретная группа, получаем дни недели, в которые у этой группы есть расписание
        $teamWeekdays = [];
        if ($team_id !== 'all' && $team_id !== 'none') {
            $teamWeekdays = \DB::table('team_weekdays')
                ->where('team_id', $team_id)
                ->pluck('weekday_id')
                ->toArray();
        }

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
        $authorId = auth()->id();

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'status_id' => 'required|exists:statuses,id',
            'description' => 'nullable|string'
        ]);

        DB::transaction(function () use ($authorId, $data) {
            $user = User::find($data['user_id']);
            $status = Status::find($data['status_id']);
            $descriptionText = $data['description'];

            $existingSchedule = ScheduleUser::where('user_id', $data['user_id'])
                ->where('date', $data['date'])
                ->first();

            $oldStatusName = $existingSchedule && $existingSchedule->statusRelation
                ? $existingSchedule->statusRelation->name
                : 'не было';

            $schedule = ScheduleUser::updateOrCreate(
                [
                    'user_id' => $data['user_id'],
                    'date' => $data['date']
                ],
                [
                    'status_id' => $data['status_id'],
                    'description' => $data['description'] ?? null
                ]
            );

            $schedule->refresh();

            $newStatusName = $schedule->statusRelation
                ? $schedule->statusRelation->name
                : 'неопределён';

            // Преобразуем дату к нужному формату:
            $formattedDate = Carbon::parse($data['date'])->format('d.m.Y');

            MyLog::create([
                'type' => 9,
                'action' => 93,
                'author_id' => $authorId,
                'description' => sprintf(
                    'Дата: "%s", Имя: "%s",%sСтатус до: "%s", Статус после: "%s",%sКомментарий: "%s"',
                    $formattedDate,
                    $user->name,
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
        // Загружаем группу, если есть
        $user->load('team.weekdays');  // Загрузим team + привязанные weekdays

        // Если у юзера нет группы, мы просто вернём JSON с user_name, и признаком, что group отсутствует
        // Если есть — вернём и дни недели группы (например, где хранится расписание).
        // Допустим, в таблице weekdays есть поля: id, name (Понедельник, Вторник и т.д.)

        // Готовим массив дней [id => name], которые у команды включены
        $teamWeekdays = [];
        if ($user->team) {
            // Допустим, у team.weekdays уже есть привязанные дни
            foreach ($user->team->weekdays as $wd) {
                $teamWeekdays[] = $wd->id;  // только id или можно и название
            }
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'team_id' => $user->team ?->id,
            'team_title'  => $user->team ?->title,
            'weekdays'    => $teamWeekdays,
        ],
    ]);
}

//    public function clearTeamWeekdays(Team $team)
//    {
//        // Удаляем все записи о днях недели для этой команды
//        $team->weekdays()->detach();
//
//        return response()->json([
//            'success' => true,
//            'message' => 'Расписание команды очищено.'
//        ]);
//    }

    public function getUserScheduleInfo(User $user)
    {
        // Подгружаем команду (если есть) и дни недели команды, чтобы визуально выделять
        $user->load('team.weekdays');

        // Собираем массив ID-шников дней недели группы
        // (team.weekdays, если у вас есть pivot team_weekdays)
        $groupWeekdays = [];
        if ($user->team) {
            foreach ($user->team->weekdays as $w) {
                $groupWeekdays[] = $w->id; // 1=Пн, 2=Вт и т.д.
            }
        }

        // Пример: хотим по умолчанию "от" = сегодня, "до" = ближайший 31 августа текущего или следующего года
        $today = now()->format('Y-m-d');
        // Проверим, прошёл ли 31 августа
        $year = now()->year;
        $aug31 = \Carbon\Carbon::create($year, 8, 31);
        if (now()->greaterThan($aug31)) {
            // если уже прошли 31 августа, берём следующий год
            $year++;
        }
        $defaultTo = \Carbon\Carbon::create($year, 8, 31)->format('Y-m-d');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'team_id' => $user->team ?->id,
            'team_title' => $user->team ?->title,
        ],
        'groupWeekdays' => $groupWeekdays, // для визуального выделения
        'defaultFrom'    => $today,        // дата "От"
        'defaultTo'      => $defaultTo,    // дата "До"
    ]);
}

//Установка группы через расписание
    public function setUserGroup(Request $request, User $user)
    {
        $request->validate([
            'team_id' => 'nullable|exists:teams,id'
        ]);
        $authorId = auth()->id();


        DB::transaction(function () use ($authorId, $request, $user) {

            $team = Team::find($request->team_id);
            $user->update(['team_id' => $request->team_id]);

            // Логирование
            MyLog::create([
                'type' => 9,
                'action' => 94,
                'author_id' => $authorId,
                'description' => ("Имя: " . $user->name . ", Установлена группа: " . $team->title),
                'created_at' => now(),
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
        $authorId = auth()->id();

        // Добавим лог входных данных
        Log::info('===> updateUserScheduleRange: входные данные', [
            'user_id' => $user->id,
            'payload' => $request->all(),
        ]);

        $data = $request->validate([
            'weekdays' => 'array',
            'weekdays.*' => 'in:1,2,3,4,5,6,7', // пн(1)–вс(7) по isoWeekday
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $weekdays = $data['weekdays'] ?? [];
        $dateFrom = Carbon::parse($data['date_from']);
        $dateTo = Carbon::parse($data['date_to']);

        // Логируем, что распарсили
        Log::debug('Парсинг дат', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
            'weekdays' => $weekdays,
        ]);

        // 1) Удаляем все записи для этого пользователя в выбранном диапазоне
        Log::info('Удаляем старые записи', [
            'user_id' => $user->id,
            'range' => [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')],
        ]);

        DB::table('schedule_users')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
            ->delete();

        // 2) Создаём вставки
        $period = CarbonPeriod::create($dateFrom, $dateTo);
        $inserts = [];

        foreach ($period as $date) {
            // isoWeekday(): Пн=1 ... Вс=7
            $dw = $date->isoWeekday();
            if (in_array($dw, $weekdays)) {
                $inserts[] = [
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                    'status_id' => 2,
                    'description' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($authorId, $user, $dateFrom, $dateTo, $weekdays, $inserts) {

            // Массив соответствий для дней недели
            $daysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'суб',
                7 => 'вск',
            ];

            if (!empty($inserts)) {
                DB::table('schedule_users')->insert($inserts);
            }

            // Готовим строку с названиями дней недели
            $weekdaysStrings = array_map(function ($dw) use ($daysMap) {
                return $daysMap[$dw] ?? $dw;
            }, $weekdays);

            // Логирование: Имя, диапазон дат и дни недели
            MyLog::create([
                'type' => 9,
                'action' => 95,
                'author_id' => $authorId,
                'description' =>
                    'Имя "' . $user->name . "\"\n" .
                    'Дата "' . $dateFrom->format('d.m.Y') . '" - "' . $dateTo->format('d.m.Y') . "\"\n" .
                    'Дни недели: "' . implode(', ', $weekdaysStrings) . '"',
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Расписание пользователя обновлено в заданном диапазоне.',
        ]);
    }

    //Настройка логов
    public function getLogsData()
    {
        $logs = MyLog::with('author')
            ->where('type', 9)// Добавляем условие для фильтрации по type
            ->select('my_logs.*');


        return DataTables::of($logs)
            ->addColumn('author', function ($log) {
                return $log->author ? $log->author->name : 'Неизвестно';
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at->format('d.m.Y / H:i:s');
            })
            ->editColumn('action', function ($log) {
                // Логика для преобразования типа
                $typeLabels = [
                    90 => 'Создание статуса расписания',
                    91 => 'Изменение статуса расписания',
                    92 => 'Удаление статуса расписания',

                    93 => 'Изменение расписания (дня)',
                    94 => 'Установка группы через расписание',
                    95 => 'Установка индивидуального расписания',

                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }

}





