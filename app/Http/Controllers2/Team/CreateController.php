<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Weekday;

class CreateController extends Controller
{
    public function __invoke(Team $team)
    {
        $allTeams = Team::All();
        $weekdays = Weekday::all();
        return view("team.create", compact('team', "allTeams", 'weekdays'));
    }

}
