<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;


class PaymentsController extends Controller
{

    public function __construct()
    {
        $this->middleware('admin');
    }


    public function index()
    {
        return view("admin.payments.index");
    }

    //Страница Платежи (вывод все платежей)
    public function getPayments(Request $request)
    {
        if ($request->ajax()) {
            $payments = Payment::with(['user.team'])->get();

            return DataTables::of($payments)
                ->addIndexColumn()
                ->editColumn('user_name', function ($row) {
//                    return $row->user->name;
//                    return $row->user->name;
                    return $row->user_name;
                })
                ->editColumn('team_title', function ($row) {
//                    return $row->user->team->title;
                    return $row->team_title;
                })
                ->editColumn('summ', function ($row) {
                    return number_format($row->summ, 0) . ' руб';
                })
//                ->editColumn('payment_month', function ($row) {
//                    return \Carbon\Carbon::parse($row->payment_month)->format('F Y');
//                })
                ->editColumn('operation_date', function ($row) {
//                    return \Carbon\Carbon::parse($row->operation_date)->format('d-m-Y H:m:s');
                    return $row->operation_date;
                })
                ->make(true);
        }
    }
}