<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;

class TeamsController extends Controller
{
    public function index()
    {
        $allTeams = Team::all();

//        $team = Team::find(2);
//        $weekday = Weekday::find(3);
//        dd($team->weekdays);
//        dd($weekday->teams);

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
            'schedule' => '',
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => '',
            'order_by' => '',
        ]);
        $schedule = $data['schedule'];
        unset($data['schedule']);

        $team = Team::create($data);

        $team->schedule()->attach($schedule);   //Способ без логирования даты создания и изменения записи в бд

        //Способ с логированием даты создания и изменения записи в бд
//        teamSchedule::firstOrCreate([
//            'schedule_id' => $schedule,
//            'team_id' => $team->id,
//        ]);
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

    public function destroy(Team $team)
    {
        $team->delete();
        return redirect()->route('team.index');
    }
}
