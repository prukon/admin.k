<?php

namespace App\Http\Controllers\Dasboard;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;

class IndexController extends Controller
{
    public function __invoke() {

        $allTeams = Team::all();
        $allUsers  = User::all();
        $weekdays = Weekday::all();

        return view('dashboard', compact('allUsers', 'allTeams', 'weekdays'));
    }

    public function update() {

    }

}
