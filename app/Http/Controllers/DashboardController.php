<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index() {

        $teams = Team::all();
        $users  = User::all();
return view('dashboard', compact('users', 'teams'));
    }

    public function update() {

    }

}
