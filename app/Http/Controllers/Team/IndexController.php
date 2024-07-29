<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Weekday;

class IndexController extends Controller
{
    public function __invoke()
    {
        $allTeams = Team::paginate(30);
        $weekdays = Weekday::all();
        return view("team.index", compact("allTeams", 'weekdays'));
    }
}
