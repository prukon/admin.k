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
//    public function getPayments(Request $request)
//    {
//        if ($request->ajax()) {
//            $payments = Payment::with(['user.team'])->get();
//
//            return DataTables::of($payments)
//                ->addIndexColumn()
//                ->addColumn('user_name', function($row) {
//                    return [
//                        'name' => $row->user->name, // Возвращаем имя пользователя
//                        'id' => $row->user->id      // Возвращаем ID пользователя
//                    ];
//                })
//                ->addColumn('team_title', function ($row) {
//                    return $row->user->team->title; // Название команды
//                })
//                ->addColumn('summ', function ($row) {
//                    return number_format($row->summ, 0) . ' руб'; // Формат суммы
//                })
//                ->addColumn('operation_date', function ($row) {
//                    return $row->operation_date; // Дата операции
//                })
//                ->make(true);
//        }
//    }

    public function getPayments(Request $request)
    {
        if ($request->ajax()) {
            $payments = Payment::with(['user.team'])->get();

            return DataTables::of($payments)
                ->addIndexColumn()
                ->addColumn('user_name', function($row) {
                    // Проверяем, есть ли пользователь в таблице payments
                    return $row->user_name
                        ? $row->user_name // Возвращаем имя пользователя из payments
                        : ($row->user ? $row->user->name : 'Без пользователя'); // Или из связанной модели, если нет в payments
                })
                ->addColumn('user_id', function($row) {
                    // Возвращаем user_id, если он существует, иначе null
                    return $row->user ? $row->user->id : null;
                })
                ->addColumn('team_title', function ($row) {
                    // Проверка, существует ли пользователь и его команда
                    return $row->user && $row->user->team
                        ? $row->user->team->title // Возвращаем название команды
                        : 'Без команды'; // Если команды нет
                })
                ->addColumn('summ', function ($row) {
                    return number_format($row->summ, 0) . ' руб'; // Формат суммы
                })
                ->addColumn('operation_date', function ($row) {
                    return $row->operation_date; // Дата операции
                })
                ->make(true);
        }
    }

}