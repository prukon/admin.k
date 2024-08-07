<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Filters\UserFilter;
use App\Http\Requests\User\FilterRequest;
use App\Models\Team;
use App\Models\User;

class IndexController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(FilterRequest $request)
    {
        $data = $request->validated();

        $filter = app()->make(UserFilter::class, ['queryParams'=> array_filter($data)]);

        $allUsers = User::filter($filter)->paginate(20);
        $allTeams = User::filter($filter)->paginate(20);
        $allTeamsCount = Team::all()->count();
        $allUsersCount  = User::all()->count();

        return view("admin.user.index", compact("allUsers" , "allTeams", 'allUsersCount', 'allTeamsCount')); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
    }
}
