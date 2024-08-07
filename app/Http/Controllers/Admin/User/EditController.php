<?php

namespace App\Http\Controllers\Admin\User;

use App\Models\Team;
use App\Models\User;

class EditController extends BaseController
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(User $user)
    {
//        $this->service->edit($user);
        $allTeams = Team::All();
        $allTeamsCount = Team::all()->count();
        $allUsersCount  = User::all()->count();

        return view('admin.user.edit', compact('user', 'allTeams', 'allUsersCount', 'allTeamsCount'));
    }
}

