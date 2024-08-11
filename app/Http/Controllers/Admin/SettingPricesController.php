<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
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
        $teamPrices = TeamPrice::where('month',$currentDate->date)->get();
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

//ajax
    // /Получение списка пользователей
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

    // Обработчик изменения даты
    public function updateDate(Request $request)
    {
        $allTeams = Team::all();
        $month = $request->query('month');
        if ($month) {
            // Получаем запись, которую нужно обновить
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
}