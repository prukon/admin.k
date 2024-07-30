<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;

use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Team;

use App\Models\Weekday;

class IndexController extends Controller
{
    public function __invoke(FilterRequest $request)
    {

        $data = $request->validated();

        $filter = app()->make(TeamFilter::class, ['queryParams'=> array_filter($data)]);

        $allTeams = Team::filter($filter)->paginate(10);

        $weekdays = Weekday::all();
        return view("team.index", compact("allTeams", 'weekdays'));
    }
}
