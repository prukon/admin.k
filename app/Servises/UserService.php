<?php

namespace App\Servises;

use App\Models\User;

class UserService
{
    public function store($data)
    {
        User::create($data);
    }

    public function update($user, $data)
    {
        $user->update($data);
    }

    public function delete($user)
    {
        $user->delete();
    }

}