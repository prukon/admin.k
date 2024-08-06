<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;

class CreateController extends BaseController
{
    public function __invoke()
    {
        $allTeamsCount = Team::all()->count();
        $allUsersCount  = User::all()->count();

        $allTeams = Team::All();
        return view("admin.user.create", compact("allTeams", 'allUsersCount', 'allTeamsCount')); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
    }
}