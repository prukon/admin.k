<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;


class CompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,superadmin');
    }

    public function index()
    {
        $user = Auth::user();

        return view("admin/company", compact(
            "user"
        ));
    }
}
