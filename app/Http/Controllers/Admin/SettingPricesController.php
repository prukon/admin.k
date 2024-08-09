<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Team;
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
        $allTeams = Team::all();
//        $allUsers = User::all()->paginate(20);
        $allTeamsCount = Team::all()->count();
        $allUsersCount = User::all()->count();
//        $weekdays = Weekday::all();
//
//        $curUser = auth()->user();
//        $curTeam = Team::where('id', auth()->user()->team_id)->first();

//dd($year);


        return view("admin/settingPrices", compact(
            "allTeams",
//            "allUsers",
            "allUsersCount",
            "allTeamsCount",
//            "weekdays",
//            "curTeam",
//            "curUser"
        ));
    }

//ajax

    public function getTeamPrice(Request $request)
    {
//        $teamName = $request->query('name');
//        $team = Team::where('title', $teamName)->first();
//        $usersTeam = User::where('team_id', $team->id)->get();
//
//        foreach ($team->weekdays as $teamWeekDay) {
//            $teamWeekDayId[] = $teamWeekDay->id;
//        }
//
//        if ($team) {
//            return response()->json([
//                'success' => true,
//                'data' => $team,
//                'teamWeekDayId' => $teamWeekDayId,  //fix сделать проверку на существование
//                'usersTeam' => $usersTeam,          //fix сделать проверку на существование
//            ]);
//        } else {
//            return response()->json([
//                'success' => false]);
//        }
    }

}
