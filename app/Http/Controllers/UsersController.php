<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function index()
    {
        $allUsers = User::all();

        return view("user.index", compact("allUsers")); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
//        dd($user);
    }

    public function create()
    {
        $allUsers = User::all();
        $allTeams = Team::All();
        return view("user.create", compact("allTeams")); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
    }

    public function store()
    {
        $data = request()->validate([
            'name' => 'string',
            'birthday' => 'string',
            'team_id' => '',
            'image' => '',
            'email' => 'string',
            'password' => 'string',
            'is_enabled' => '',
        ]);
//        dd($data);
        User::create($data);
        return redirect()->route('user.index');
    }

//    public function show(User $user)
//    {
//        $allTeams = Team::All();
//        return view('user.show', compact('user', 'allTeams'));
//    }

    public function edit(User $user)
    {
        $allTeams = Team::All();
        return view('user.edit', compact('user', 'allTeams'));
    }

    public function update(User $user)
    {
        $data = request()->validate([
            'name' => 'string',
            'birthday' => 'string',
            'team_id' => '',
//            'image' => '',
            'email' => 'string',
            'password' => 'string',
            'is_enabled' => '',
        ]);
        $user->update($data);
//              dd($data);
        return redirect()->route('user.index');

    }
    public function destroy(User $user) {
        $user->delete();
        return redirect()->route('user.index');
    }

}
