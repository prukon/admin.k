<?php
//
//namespace App\Http\Controllers;
//
//use App\Models\Team;
//use App\Models\User;
//use Illuminate\Http\Request;
//use Yajra\DataTables\DataTables;
//
//
//class PaymentsController extends Controller
//{
//    public function index()
//    {
//        return view('admin.payments.index');
//    }
//
//    public function getPayments(Request $request)
//    {
//        if ($request->ajax()) {
//            $payments = Payment::with(['user.team'])->get();
//
//            return DataTables::of($payments)
//                ->addIndexColumn()
//                ->editColumn('user_name', function ($row) {
//                    return $row->user->name;
//                })
//                ->editColumn('team_title', function ($row) {
//                    return $row->user->team->title;
//                })
//                ->editColumn('summ', function ($row) {
//                    return number_format($row->summ, 2);
//                })
//                ->editColumn('payment_month', function ($row) {
//                    return \Carbon\Carbon::parse($row->payment_month)->format('F Y');
//                })
//                ->editColumn('operation_date', function ($row) {
//                    return \Carbon\Carbon::parse($row->operation_date)->format('d-m-Y');
//                })
//                ->make(true);
//        }
//    } 
//}