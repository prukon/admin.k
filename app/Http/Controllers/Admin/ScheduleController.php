<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ScheduleUser;
use Carbon\Carbon;
use App\Models\Team;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
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
            'teamWeekdays'
        ));
    }

    public function update(Request $request)
    {
        // Валидация входных данных
        $data = $request->validate([
            'user_id'     => 'required|integer',
            'date'        => 'required|date_format:Y-m-d',
            'status'      => 'required|in:N,R,Z',
            'description' => 'nullable|string'
        ]);

        // Обновляем или создаём запись расписания
        $schedule = ScheduleUser::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'date'    => $data['date']
            ],
            [
                'status'      => $data['status'],
                'description' => $data['description'] ?? null
            ]
        );

        return response()->json(['success' => true]);
    }
}





