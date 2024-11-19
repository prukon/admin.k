<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;

class CreateController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,superadmin');    }

    public function __invoke(Team $team)
    {
        $allTeams = Team::All();
        $weekdays = Weekday::all();
        return view("admin.team.create", compact('team',
            "allTeams",
            'weekdays'));
    }
}
