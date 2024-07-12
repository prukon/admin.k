<?php

namespace App\Http\Controllers\User;

use App\Models\Team;
use App\Models\User;

class EditController extends BaseController
{
    public function __invoke(User $user)
    {
//        $this->service->edit($user);
        $allTeams = Team::All();
        return view('user.edit', compact('user', 'allTeams'));
    }
}
