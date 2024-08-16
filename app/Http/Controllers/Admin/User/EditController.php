<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;

class EditController extends Controller
{
    public function __construct(UserService $service)
    {
        $this->service = $service;
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

