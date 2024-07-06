<?php

namespace App\Http\Controllers\Dasboard;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;

class IndexController extends Controller
{
    public function __invoke() {

        $teams = Team::all();
        $users  = User::all();
        $weekdays = Weekday::all();

        return view('dashboard', compact('users', 'teams', 'weekdays'));
    }

    public function update() {

    }

}
