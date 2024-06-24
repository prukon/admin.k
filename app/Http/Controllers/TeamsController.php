<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class TeamsController extends Controller
{
    public function index()
    {
        $allTeams = Team::all();
        return view("team.index", compact("allTeams"));
    }

    public function create()
    {
        $allUsers = User::all();
        $allTeams = Team::All();
        return view("team.create", compact("allTeams"));
    }

    public function store()
    {
        $data = request()->validate([
            'title' => 'string',
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => '',
            'order_by' => '',
        ]);
//        dd($data);
        Team::create($data);
        return redirect()->route('team.index');
    }

    public function edit(Team $team)
    {
        return view('team.edit', compact('team'));
    }

    public function update(Team $team)
    {
        $data = request()->validate([
            'title' => 'string',
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => '',
            'order_by' => '',
        ]);
        $team->update($data);
//              dd($data);
        return redirect()->route('team.index');
    }
    public function destroy(Team $team) {
        $team->delete();
        return redirect()->route('team.index');
    }
}
