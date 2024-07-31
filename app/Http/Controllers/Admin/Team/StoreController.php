<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\teamWeekday;

class StoreController extends Controller
{

    public function __invoke()
    {
        $data = request()->validate([
            'title' => 'string',
            'weekdays' => '',
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => '',
            'order_by' => '',
        ]);

//        if ( is_null($data['weekdays'])) {
//
//        } else {
//            $weekdays = $data['weekdays'];
//        }

        $weekdays = $data['weekdays'];
        unset($data['weekdays']);
        $team = Team::create($data);

        //Способ с логированием даты создания и изменения записи в бд
        foreach ($weekdays as $weekday) {
            teamWeekday::firstOrCreate([
                'weekday_id' => $weekday,
                'team_id' => $team->id,
            ]);
        }
//        $team->weekdays   ()->attach($weekday);   //Способ без логирования даты создания и изменения записи в бд

        return redirect()->route('admin.team.index');
    }

}
