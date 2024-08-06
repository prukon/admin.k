<?php
//
namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;


class IndexController extends Controller
{

    public function getUserDetails(Request $request)
    {
        dd('3');
        $userName = $request->query('name');
        $user = User::where('name', $userName)->first();

        if ($user) {
            return response()->json(['success' => true, 'data' => $user]);
        } else {
            return response()->json(['success' => false, 'message' => 'User not found']);
        }
    }

//        public function __invoke()
//        {
//
//            $allTeams = Team::all();
//            $allUsers = User::all();
//
//            $allTeamsCount = Team::all()->count();
//            $allUsersCount = User::all()->count();
//            $weekdays = Weekday::all();
//
//            $curUser = auth()->user();
//            $curTeamId = $curUser->team_id;
//
//            dd($curTeamId);
//
//            return view('admin.dashboard', compact(
//                'allUsers',
//                'allTeams',
//                'allUsersCount',
//                'allTeamsCount',
//                'weekdays',
//                'currentTeam'));
//        }
//
//        public function update()
//        {
//
//        }
//
}
