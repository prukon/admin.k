<?php

namespace App\Servises;

use App\Models\Team;
use App\Models\TeamWeekday;

class TeamService
{
    public function store($data)
    {
//        Team::create($data);

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


    }

    public function update($team, $data)
    {
        $team->update($data);
    }

    public function delete ($team){
        $team->delete();
    }

}