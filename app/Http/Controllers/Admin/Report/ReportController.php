<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;



class ReportController extends Controller
{

    public function __construct()
    {
        $this->middleware('admin');
    }


    public function index()
    {
        return view("admin.report");
    }


    public function payments()
    {
        return view('admin.report.payment', ['activeTab' => 'payments']);
    }

    public function debts()
    {
        return view('admin.report.debt', ['activeTab' => 'debts']);
    }



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

    public function getDebts(Request $request)
    {
        if ($request->ajax()) {
            $usersWithUnpaidPrices = DB::table('users_prices')
                ->leftJoin('users', 'users.id', '=', 'users_prices.user_id')
                ->select('users.name as user_name','users.id as user_id' , 'users_prices.month', 'users_prices.price')
                ->where('users_prices.is_paid', 0)
                ->where('users_prices.price', '>', 0)
                ->where('users_prices.month', '<', 'Сентябрь 2024')
                ->get();


            // Добавляем проверку на наличие данных
            if ($usersWithUnpaidPrices->isEmpty()) {
                return response()->json(['error' => 'Данные не найдены'], 404);
            }

            return DataTables::of($usersWithUnpaidPrices)
                ->addIndexColumn()
                ->addColumn('user_name', function($row) {
                    return $row->user_name ? $row->user_name : 'Без имени'; // Проверяем наличие имени пользователя
                })
                ->addColumn('month', function($row) {
                    return $row->month; // Месяц
                })
                ->addColumn('price', function($row) {
                    return number_format($row->price, 2) . ' руб'; // Формат цены
                })
                ->make(true);
        }
    }

}