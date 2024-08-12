<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;


class SettingPricesController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function index(FilterRequest $request)
    {
//        $data = $request->validated();
//        $filter = app()->make(TeamFilter::class, ['queryParams' => array_filter($data)]);
//
        $currentDate = Setting::where('id', 1)->first();
        $allTeams = Team::all();
//        $allUsers = User::all()->paginate(20);
        $allTeamsCount = Team::all()->count();
        $allUsersCount = User::all()->count();
//        dd($currentDate->date);
        $teamPrices = TeamPrice::where('month', $currentDate->date)->get();
//dd($teamPrices);
//        $weekdays = Weekday::all();
//
//        $curUser = auth()->user();
//        $curTeam = Team::where('id', auth()->user()->team_id)->first();

        return view("admin/settingPrices", compact(
            "allTeams",
//            "allUsers",
            "allUsersCount",
            "allTeamsCount",
            'currentDate',
            'teamPrices',
//            "weekdays",
//            "curTeam",
//            "curUser"
        ));
    }

    // AJAX Получение списка пользователей
    public function getTeamPrice(Request $request)
    {

        $teamId = $request->query('teamId');
        $usersTeam = User::where('team_id', $teamId)->get();
        if ($usersTeam) {
            return response()->json([
                'success' => true,
                'usersTeam' => $usersTeam,          //fix сделать проверку на существование
            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }

    // AJAX Обработчик изменения даты
    public function updateDate(Request $request)
    {
        $allTeams = Team::all();
        $month = $request->query('month');
        if ($month) {
            $setting = Setting::first(); // Здесь вы можете использовать другой метод для поиска нужной записи
            if ($setting) {
                // Изменяем поле date на новое значение
                $setting->date = $month; // Здесь можно использовать $month или любую другую логику для определения нового значения
                // Сохраняем изменения в базе данных
                $setting->save();
                foreach ($allTeams as $team) {
                    \App\Models\TeamPrice::firstOrCreate(
                        [
                            'team_id' => $team->id,
                            'month' => $month
                        ],
                        [
                            'price' => 0 // можно задать дефолтное значение для price, если нужно
                        ]
                    );
                }
                return response()->json([
                    'success' => true,
                    'month' => $month,
                ]);
            } else {
                return response()->json([
                    'success' => false]);
            }
        }
    }

//  AJAX Получение списка пользователей
    public function setTeamPrice(Request $request)
    {
        $teamPrice = $request->query('teamPrice');
        $teamId = $request->query('teamId');
        $selectedDate = $request->query('selectedDate');
        $usersTeam = User::where('team_id', $teamId)->get();

        \App\Models\TeamPrice::updateOrCreate(
            [
                'team_id' => $teamId,
                'month' => $selectedDate,
            ],
            [
                'price' => $teamPrice
            ]
        );

        return response()->json([
            'success' => true,
            'teamPrice' => $teamPrice,
            'selectedDate' => $selectedDate,
            'teamId' => $teamId,
        ]);
    }

    //AJAX Применить слева.Установка цен всем группам
    public function setPriceAllTeams(Request $request)
    {

        $selectedDate = $request->query('selectedDate');
        $teamsData = json_decode($request->query('teamsData'), true);

        // Перебираем массив и обновляем цены команд
        foreach ($teamsData as $teamData) {
            // Находим команду по названию
            $team = Team::where('title', $teamData['name'])->first();

            // Обновляем цены для групп
            if ($team) {
                // Обновляем или создаем запись в таблице team_prices
                TeamPrice::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'month' => $selectedDate
                    ],
                    [
                        'price' => $teamData['price']
                    ]
                );
            }
            // Обновляем цены для пользователей
            $users = User::where('team_id', $team->id)->get(); // Предполагается, что пользователи связаны с командами
            foreach ($users as $user) {
                UserPrice::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'month' => $selectedDate
                    ],
                    [
                        'price' => $teamData['price'], // Используем ту же цену, что и для команды
                        'is_paid' => false // Или оставляем текущий статус оплаты
                    ]
                );
            }
        }



        return response()->json([
            'success' => true,
        ]);
    }
}