<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\User;

class DestroyController extends BaseController
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(User $user) {

        $this->service->delete($user);
        return redirect()->route('admin.user.index');
    }
}