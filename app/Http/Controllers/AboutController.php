<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class AboutController extends Controller
{

    public function index()
    {
        $user = Auth::user();

        return view("about", compact(
            "user"
        ));
    }

    public function terms()
    {
        $user = Auth::user();

        return view("terms", compact(
            "user"
        ));
    }
}