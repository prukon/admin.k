<?php

namespace App\Http\Controllers;

use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\Weekday;
use Illuminate\Http\Request;


class DashboardController extends Controller
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
        $data = $request->validated();
        $filter = app()->make(TeamFilter::class, ['queryParams' => array_filter($data)]);

        $allTeams = Team::filter($filter)->paginate(10);
        $allUsers = User::filter($filter)->paginate(20);
        $allTeamsCount = Team::all()->count();
        $allUsersCount = User::all()->count();
        $weekdays = Weekday::all();

        $curUser = auth()->user();
        $curTeam = Team::where('id', auth()->user()->team_id)->first();

        return view("dashboard", compact(
            "allTeams",
            "allUsers",
            "allUsersCount",
            "allTeamsCount",
            "weekdays",
            "curTeam",
            "curUser"));
    }

    //AJAX Изменение юзера
    public function getUserDetails(Request $request)
    {
        $userName = $request->query('name');
        $user = User::where('name', $userName)->first();
        $userTeam = Team::where('id', $user->team_id)->first();
        $userPrice = UserPrice::where('user_id', $user->id)->get();

        if ($user) {
            return response()->json([
                'success' => true,
                'userData' => $user,
                'userTeam' => $userTeam,
                'userPrice' => $userPrice
            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }

    //AJAX Изменение команды
    public function getTeamDetails(Request $request)
    {
        $teamName = $request->query('name');
        $team = Team::where('title', $teamName)->first();
        $usersTeam = User::where('team_id', $team->id)->get();

        foreach ($team->weekdays as $teamWeekDay) {
            $teamWeekDayId[] = $teamWeekDay->id;
        }

        if ($team) {
            return response()->json([
                'success' => true,
                'data' => $team,
                'teamWeekDayId' => $teamWeekDayId,  //fix сделать проверку на существование
                'usersTeam' => $usersTeam,          //fix сделать проверку на существование
            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }

    //AJAX клик по УСТАНОВИТЬ
    public function setupBtn(Request $request)
    {
//        $teamName = $request->query('name');
//        $team = Team::where('title', $teamName)->first();
//        $usersTeam = User::where('team_id', $team->id)->get();

        $userName = $request->query('userName');
        $inputDate = $request->query('inputDate');
        $inputDate = date('Y-m-d', strtotime($inputDate));

        $user = User::where('name', $userName)->first();


        if ($user) {
            $user->update([
                'start_date' => $inputDate
            ]);
        }


//        foreach ($team->weekdays as $teamWeekDay) {
//            $teamWeekDayId[] = $teamWeekDay->id;
//        }

        if ($inputDate) {
            return response()->json([
                'success' => true,
                'userName' => $userName,
                'inputDate' => $inputDate,
            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }
}
