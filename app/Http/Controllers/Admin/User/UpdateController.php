<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Requests\User\UpdateRequest;
use App\Models\User;

class UpdateController extends BaseController
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(UpdateRequest $request, User $user)
    {
        $data = $request->validated();
        $this->service->update($user, $data);
        return redirect()->route('admin.user.index');
    }
}
