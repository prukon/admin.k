<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MainController extends Controller
{
    public function __invoke() {


//        dd(1);
        return view('main');
    }
}
