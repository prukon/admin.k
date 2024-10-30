<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\teamWeekday;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;

class EditController extends Controller
{

    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(Team $team)
    {
        $weekdays = Weekday::all();
        return view('admin.team.edit', compact('team',
            'weekdays',
        ));
    }

    public function edit($id)
    {
        $team = Team::with('weekdays')->findOrFail($id);
        $weekdays = Weekday::all(); // Получаем все дни недели
        return response()->json([
            'id' => $team->id,
            'title' => $team->title,
            'order_by' => $team->order_by,
            'is_enabled' => $team->is_enabled,
            'team_weekdays' => $team->weekdays, // Дни недели, связанные с командой
            'weekdays' => $weekdays // Все дни недели
        ]);
    }


}
