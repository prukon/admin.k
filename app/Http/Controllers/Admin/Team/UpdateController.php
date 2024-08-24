<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateRequest;
use App\Models\Team;
use App\Servises\TeamService;

class UpdateController extends Controller
{

    public function __construct(TeamService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(UpdateRequest $request, Team $team)
    {
//       Валидация входных данных с фронта
        $data = $request->validated();
//        Обновление данных
        $this->service->update($team,$data);

        return redirect()->route('admin.team.index');
    }
}
