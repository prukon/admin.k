<?php

namespace App\Servises\User;

use App\Models\Team;

class TeamService
{
    public function store($data)
    {
        Team::create($data);
    }

    public function update($team, $data)
    {
        $team->update($data);
    }

    public function delete ($team){
        $team->delete();
    }

}