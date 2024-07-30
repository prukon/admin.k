<?php

namespace App\Http\Controllers\User;

use App\Http\Filters\UserFilter;
use App\Http\Requests\User\FilterRequest;
use App\Models\User;

class IndexController extends BaseController
{
    public function __invoke(FilterRequest $request)
    {
        $data = $request->validated();

        $filter = app()->make(UserFilter::class, ['queryParams'=> array_filter($data)]);

        $allUsers = User::filter($filter)->paginate(30);

        return view("user.index", compact("allUsers")); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
    }
}
