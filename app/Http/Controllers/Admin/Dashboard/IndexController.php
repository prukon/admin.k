<?php

namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;

class IndexController extends Controller
{
    public function __invoke() {

        $allTeams = Team::all();
        $allUsers  = User::all();

        $allTeamsCount = Team::all()->count();
        $allUsersCount  = User::all()->count();
        $weekdays = Weekday::all();

        return view('admin.dashboard', compact('allUsers', 'allTeams', 'allUsersCount', 'allTeamsCount', 'weekdays'));
    }

    public function update() {

    }

}
