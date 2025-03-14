<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\MyLog;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ScheduleUser;
use Carbon\Carbon;
use App\Models\Team;
use App\Models\Status;
use Yajra\DataTables\DataTables;


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
        $year    = $request->get('year', date('Y'));
        $month   = $request->get('month', date('m'));
        $team_id = $request->get('team', 'all'); // значения: all, none или id команды

        // Начало и конец месяца
        $startOfMonth = Carbon::createFromDate($year, $month, 1);
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

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
        if($team_id !== 'all' && $team_id !== 'none'){
            $teamWeekdays = \DB::table('team_weekdays')
                ->where('team_id', $team_id)
                ->pluck('weekday_id')
                ->toArray();
        }

        return view('admin.schedule.index', compact(
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

    public function update(Request $request)
    {
        $data = $request->validate([
            'user_id'   => 'required|integer|exists:users,id',
            'date'      => 'required|date_format:Y-m-d',
            'status_id' => 'required|exists:statuses,id',
            'description' => 'nullable|string'
        ]);

        $schedule = ScheduleUser::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'date'    => $data['date']
            ],
            [
                'status_id'   => $data['status_id'],
                'description' => $data['description'] ?? null
            ]
        );

        return response()->json(['success' => true]);
    }

    public function getLogsData()
    {
        $logs = MyLog::with('author')
      //      ->where('type', 9) // Добавляем условие для фильтрации по type
            ->select('my_logs.*');
 dump($logs);

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
                    90 => 'Удаление статуса расписания',
                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }
}





