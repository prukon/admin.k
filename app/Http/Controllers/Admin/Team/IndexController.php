<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Weekday;

class IndexController extends Controller
{
    public function __invoke()
    {
        $allTeams = Team::paginate(30);
        $weekdays = Weekday::all();
        return view("admin/team/index", compact("allTeams", 'weekdays'));
//return view('');
    }
}
