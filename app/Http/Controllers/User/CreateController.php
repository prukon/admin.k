<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;

class CreateController extends Controller
{
    public function __invoke()
    {
        $allUsers = User::all();
        $allTeams = Team::All();
        return view("user.create", compact("allTeams")); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
    }
}
