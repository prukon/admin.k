<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;

class IndexController extends Controller
{
//    public function __invoke()
//    {
//        $allTeams = Team::paginate(30);
//        $weekdays = Weekday::all();
//        return view("admin/team/index", compact("allTeams", 'weekdays'));
////return view('');
//    }

    public function __invoke(FilterRequest $request)
    {

        $data = $request->validated();

        $filter = app()->make(TeamFilter::class, ['queryParams'=> array_filter($data)]);

        $allTeams = Team::filter($filter)->paginate(10);
        $allUsers = User::filter($filter)->paginate(20);
        $allTeamsCount = Team::all()->count();
        $allUsersCount  = User::all()->count();

        $weekdays = Weekday::all();
        return view("admin/team/index", compact("allTeams",'allUsers', 'allUsersCount', 'allTeamsCount', 'weekdays'));
    }
}
