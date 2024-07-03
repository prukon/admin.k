<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __invoke()
    {
        $data = request()->validate([
            'name' => 'string',
            'birthday' => '',
            'team_id' => 'string',
            'image' => '',
            'email' => 'string',
//            'password' => 'string',
            'is_enabled' => 'string',
        ]);
//        dd($data);
        User::create($data);
        return redirect()->route('user.index');
    }
}
