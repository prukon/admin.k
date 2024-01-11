<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function index() {

return view('payments');
//        dd($user);
    }
}
