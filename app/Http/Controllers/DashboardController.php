<?php

namespace App\Http\Controllers;

use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Team;
use App\Models\User;
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


////        $testTeam = Team::where('id', 2)->first();
////        foreach ($testTeam->weekdays as $teamWeekdayTest) {
////            dump($teamWeekdayTest->id);
////        }
//
////        $testTeamWeekdays = $curTeam->weekdays();
////        dump($testTeamWeekdays);
//
//
//        $teamName = 'Феникс';
//        //$testTeam = Team::where('id', 2)->first();
//        $team = Team::where('title', $teamName)->first();
////        $teamWeekDays = $team->weekdays();
//
////        $teamWeekDayId = [];
//        foreach ($team->weekdays as $teamWeekDay) {
////            $teamWeekDayId = append($teamWeekDay->id);
//            $teamWeekDayId[] = $teamWeekDay->id;
//        }
////        dd($teamWeekDayId);

        return view("dashboard", compact(
            "allTeams",
            "allUsers",
            "allUsersCount",
            "allTeamsCount",
            "weekdays",
            "curTeam",
            "curUser"));
    }


    //AJAX
    //Изменение юзера
    public function getUserDetails(Request $request)
    {
        $userName = $request->query('name');
        $user = User::where('name', $userName)->first();
        if ($user) {
            return response()->json([
                'success' => true,
                'data' => $user,
                'userTeam' => $userTeam = Team::where('id', $user->team_id)->first()
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'User not found']);
        }
    }

    //Изменение команды
    public function getTeamDetails(Request $request)
    {
        $teamName = $request->query('name');
        $team = Team::where('title', $teamName)->first();
//        $teamWeekDays = $team->weekdays();

        $teamWeekDayId = [];
        foreach ($team->weekdays as $teamWeekDay) {
//            $teamWeekDayId = append($teamWeekDay->id);
            $teamWeekDayId[] = $teamWeekDay->id;
        }



        if ($team) {
            return response()->json([
                'success' => true,
                'data' => $team,
//              'teamTest' => $teamTest,
                'teamWeekDayId' => $teamWeekDayId,
//                'userTeam' => $userTeam = Team::where('id', $team->team_id)->first()
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'User not found']);
        }
    }
}
