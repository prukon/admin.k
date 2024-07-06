<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\teamWeekday;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;

class EditController extends Controller
{
    public function __invoke(Team $team)
    {
        $weekdays = Weekday::all();
        return view('team.edit', compact('team', 'weekdays'));
    }

}
