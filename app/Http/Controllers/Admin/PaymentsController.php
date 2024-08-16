<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;

class PaymentsController extends Controller
{


    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index()
    {

        $allTeamsCount = Team::all()->count();
        $allUsersCount = User::all()->count();
        $allTeams = Team::all();
        $allUsers = User::all();


        return view('payments', compact(
            "allTeams",
            "allUsers",
            "allUsersCount",
            "allTeamsCount",
        ));

    }
}
