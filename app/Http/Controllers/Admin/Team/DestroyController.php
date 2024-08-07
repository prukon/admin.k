<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;

class DestroyController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(Team $team)
    {
        $team->delete();
        return redirect()->route('admin.team.index');
    }
}
