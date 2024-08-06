<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;

class DestroyController extends Controller
{

    public function __invoke(Team $team)
    {
        $team->delete();
        return redirect()->route('team.index');
    }
}
