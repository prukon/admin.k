<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __invoke(User $user)
    {
        $data = request()->validate([
            'name' => 'string',
            'birthday' => '',
            'team_id' => 'string',
//            'image' => '',
            'email' => 'string',
//            'password' => 'string',
            'is_enabled' => 'string',
        ]);
        $user->update($data);
//              dd($data);
        return redirect()->route('user.index');
    }
}
