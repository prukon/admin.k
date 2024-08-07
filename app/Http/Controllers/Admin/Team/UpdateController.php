<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;

class UpdateController extends Controller
{

    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(Team $team)
    {
        $data = request()->validate([
            'title' => 'string',
            'weekdays' => '',
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => '',
            'order_by' => '',
        ]);


        $weekdays = $data['weekdays'];
        unset($data['weekdays']);

        $team->update($data);
        $team->weekdays()->sync($weekdays);
//dd($weekdays, $team);
        return redirect()->route('admin.team.index');
    }
}
