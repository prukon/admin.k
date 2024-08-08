<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateRequest;
use App\Models\User;
use App\Servises\UserService;

class UpdateController extends Controller
{
    public $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }


    public function __invoke(UpdateRequest $request, User $user)
    {
        $data = $request->validated();
        $this->service->update($user, $data);
        return redirect()->route('admin.user.index');
    }
}
