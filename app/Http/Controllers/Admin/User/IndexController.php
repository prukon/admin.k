<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Filters\UserFilter;
use App\Http\Requests\User\FilterRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(FilterRequest $request)
    {
        $data = $request->validated();
//        dd($data);
        $query = User::query();

if (isset($data['id'])) {
    $query->where('id', $data['id']);
}

        $filter = app()->make(UserFilter::class, ['queryParams'=> array_filter($data)]);

        $allUsers = User::filter($filter)
            ->orderBy('name', 'asc') // сортировка по полю name по возрастанию
            ->paginate(20);
        $allTeams = Team::all();

        return view("admin.user", compact(
            "allUsers" ,
            "allTeams"

        ));
    }

}
