<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;

class IndexController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
   }

    public function __invoke(FilterRequest $request)
    {

        $data = $request->validated();

        $filter = app()->make(TeamFilter::class, ['queryParams'=> array_filter($data)]);

//        $allTeams = Team::filter($filter)->paginate(10);
        $allTeams = Team::filter($filter)
            ->orderBy('order_by', 'asc') // 'asc' для сортировки по возрастанию, 'desc' для сортировки по убыванию
            ->paginate(10);
        $allUsers = User::filter($filter)->paginate(20);
        $weekdays = Weekday::all();

        return view("admin/team/index", compact("allTeams",
            'allUsers',
            'weekdays'));
    }
}
