<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreRequest;
use App\Servises\TeamService;
use App\Servises\UserService;

class StoreController extends Controller
{
    public $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(StoreRequest $request)
    {

        $data = $request->validated();
        $this->service->store($data);
        return redirect()->route('admin.user.index');
    }

}