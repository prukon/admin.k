<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Requests\User\StoreRequest;

class StoreController extends BaseController
{
    public function __invoke(StoreRequest $request)
    {
        $data = $request->validated();
        $this->service->store($data);
        return redirect()->route('admin.user.index');
    }
}
