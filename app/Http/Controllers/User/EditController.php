<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;

class EditController extends Controller
{
    public function __invoke(User $user)
    {
        $allTeams = Team::All();
        return view('user.edit', compact('user', 'allTeams'));
    }
}
