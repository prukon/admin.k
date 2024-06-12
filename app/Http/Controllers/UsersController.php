<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function index() {
        $allUsers  = User::all();
return view("user.index", compact("allUsers")); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
//        dd($user);
    }

    public function create() {
       $allUsers  = User::all();
        $AllTeams  = Team::All();
        return view("user.create", compact("AllTeams")); //означает, что мы обращаемся к папке post, в которой файл index.blade.php
    }

    public function store() {
        dd("111");
    }

}
