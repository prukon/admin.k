<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index() {

        $teams = Team::all();
        $users  = User::all();
        $weekdays = Weekday::all();

        return view('dashboard', compact('users', 'teams', 'weekdays'));
    }

    public function update() {

    }

}
