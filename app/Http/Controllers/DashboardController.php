<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index() {

        $teams = Team::find(1);
        dd($teams);

    }
}
