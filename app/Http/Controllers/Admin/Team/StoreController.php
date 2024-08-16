<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreRequest;
use App\Models\Team;
use App\Models\teamWeekday;
use App\Servises\TeamService;

class StoreController extends Controller
{

    public $service;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(StoreRequest $request)
    {

        $data = $request->validated();
        $this->service->store($data);


//        $weekdays = $data['weekdays'];
//        unset($data['weekdays']);
//        $team = Team::create($data);
//
//        //Способ с логированием даты создания и изменения записи в бд
//        foreach ($weekdays as $weekday) {
//            teamWeekday::firstOrCreate([
//                'weekday_id' => $weekday,
//                'team_id' => $team->id,
//            ]);
//        }

        return redirect()->route('admin.team.index');
    }

}
