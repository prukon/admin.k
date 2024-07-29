<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;

class IndexController extends BaseController
{
    public function __invoke()
    {
        $allUsers = User::paginate(30);
        return view("user.index", compact("allUsers")); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
    }
}
