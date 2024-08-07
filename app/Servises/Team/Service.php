<?php

namespace App\Servises\Team;

use App\Models\Team;

class Service
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