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

        return view("dashboard", compact(
            "allTeams",
            "allUsers",
            "allUsersCount",
            "allTeamsCount",
            "weekdays",
            "curTeam",
            "curUser"));
    }


    public function getUserDetails(Request $request)
    {
//        dd('3');
//        $userName = $request->query('name');
//        $user = User::where('name', $userName)->first();
//
//        if ($user) {
//            return response()->json(['success' => true, 'data' => $user]);
//        } else {
//            return response()->json(['success' => false, 'message' => 'User not found']);
//        }
    }
}
