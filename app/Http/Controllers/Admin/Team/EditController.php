<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\teamWeekday;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;

class EditController extends Controller
{

    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(Team $team)
    {
        $allTeamsCount = Team::all()->count();
        $allUsersCount = User::all()->count();

        $weekdays = Weekday::all();
        return view('admin.team.edit', compact('team', 'weekdays', 'allUsersCount', 'allTeamsCount'));
    }

}
