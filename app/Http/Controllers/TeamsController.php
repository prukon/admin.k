<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\teamWeekday;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;

class TeamsController extends Controller
{
    public function index()
    {
        $allTeams = Team::all();
        $weekdays = Weekday::all();

//        $team = Team::find(2);
//        $weekday = Weekday::find(3);
//        dd($team->weekdays);
//        dd($weekday->teams);

        return view("team.index", compact("allTeams", 'weekdays'));
    }


    public function create(Team $team)
    {
//        $allUsers = User::all();
        $allTeams = Team::All();
        $weekdays = Weekday::all();
        return view("team.create", compact('team', "allTeams", 'weekdays'));
    }


    public function store()
    {
        $data = request()->validate([
            'title' => 'string',
            'weekdays' => '',
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => '',
            'order_by' => '',
        ]);

//        if ( is_null($data['weekdays'])) {
//
//        } else {
//            $weekdays = $data['weekdays'];
//        }

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

        return redirect()->route('team.index');
    }


    public function edit(Team $team)
    {
        $weekdays = Weekday::all();
        return view('team.edit', compact('team', 'weekdays'));
    }


    public function update(Team $team)
    {
        $data = request()->validate([
            'title' => 'string',
            'weekdays' => '',
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => '',
            'order_by' => '',
        ]);


        $weekdays = $data['weekdays'];
        unset($data['weekdays']);

        $team->update($data);
        $team->weekdays()->sync($weekdays);
//dd($weekdays, $team);
        return redirect()->route('team.index');
    }


    public function destroy(Team $team)
    {
        $team->delete();
        return redirect()->route('team.index');
    }
}
