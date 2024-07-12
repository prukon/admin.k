<?php

namespace App\Servises\User;

use App\Models\Team;
use App\Models\User;

class Service
{
    public function store($data)
    {
//        dd($data);
        User::create($data);
    }

    public function update($user, $data)
    {
        $user->update($data);
    }

    public function delete ($user){
        $user->delete();
    }

//    public function edit($user) {
//        $allTeams = Team::All();
//
//    }
}