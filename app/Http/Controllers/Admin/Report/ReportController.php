<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class ReportController extends Controller
{

    public function __construct()
    {
        $this->middleware('admin');
    }

   public function formatedDate($month)
    {
        // Массив соответствий русских и английских названий месяцев
        $months = [
            'Январь' => 'January',
            'Февраль' => 'February',
            'Март' => 'March',
            'Апрель' => 'April',
            'Май' => 'May',
            'Июнь' => 'June',
            'Июль' => 'July',
            'Август' => 'August',
            'Сентябрь' => 'September',
            'Октябрь' => 'October',
            'Ноябрь' => 'November',
            'Декабрь' => 'December',
        ];

        // Разделение строки на месяц и год
        $parts = explode(' ', $month);
        if (count($parts) === 2 && isset($months[$parts[0]])) {
            $month = $months[$parts[0]] . ' ' . $parts[1]; // Замена русского месяца на английский
        } else {
            return null; // Если формат не соответствует "Месяц Год", возвращаем null
        }

        // Преобразуем строку в объект DateTime
        try {
            $date = \DateTime::createFromFormat('F Y', $month); // F - имя месяца, Y - год
            if ($date) {
                return $date->format('Y-m-01'); // Всегда возвращаем первое число месяца
            }
            return null; // Возвращаем null, если не удалось преобразовать
        } catch (\Exception $e) {
            \Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }


    public function index()
    {
        return view("admin.report");
    }

    public function payments()
    {
        $totalPaidPrice = DB::table('payments')
            ->sum('payments.summ');

        $totalPaidPrice = number_format($totalPaidPrice, 0, '', ' ');

        return view('admin.report.payment', ['activeTab' => 'payments'],
            compact('totalPaidPrice'));
    }
    public function debts()
    {
        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');


            $totalUnpaidPrice = DB::table('users_prices')
                ->leftJoin('users', 'users.id', '=', 'users_prices.user_id')
                ->where('users_prices.is_paid', 0)
                ->where('users.is_enabled', 1)
                ->where('users_prices.price', '>', 0)
                ->where('users_prices.new_month', '<', $currentMonth)
                ->sum('users_prices.price');

        $totalUnpaidPrice = number_format($totalUnpaidPrice, 0, '', ' ');


        return view('admin.report.debt', ['activeTab' => 'debts'],
        compact('totalUnpaidPrice'));
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

        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
//dd($currentMonth)
//         function formatedDate($month)
//    {
//        // Массив соответствий русских и английских названий месяцев
//        $months = [
//            'Январь' => 'January',
//            'Февраль' => 'February',
//            'Март' => 'March',
//            'Апрель' => 'April',
//            'Май' => 'May',
//            'Июнь' => 'June',
//            'Июль' => 'July',
//            'Август' => 'August',
//            'Сентябрь' => 'September',
//            'Октябрь' => 'October',
//            'Ноябрь' => 'November',
//            'Декабрь' => 'December',
//        ];
//
//        // Разделение строки на месяц и год
//        $parts = explode(' ', $month);
//        if (count($parts) === 2 && isset($months[$parts[0]])) {
//            $month = $months[$parts[0]] . ' ' . $parts[1]; // Замена русского месяца на английский
//        } else {
//            return null; // Если формат не соответствует "Месяц Год", возвращаем null
//        }
//
//        // Преобразуем строку в объект DateTime
//        try {
//            $date = \DateTime::createFromFormat('F Y', $month); // F - имя месяца, Y - год
//            if ($date) {
//                return $date->format('Y-m-01'); // Всегда возвращаем первое число месяца
//            }
//            return null; // Возвращаем null, если не удалось преобразовать
//        } catch (\Exception $e) {
//            \Log::error('Ошибка преобразования даты: ' . $e->getMessage());
//            return null;
//        }
//    }

//        $currentMonth = formatedDate($currentMonth);
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');

//         dd($currentMonth);
        if ($request->ajax()) {
            $usersWithUnpaidPrices = DB::table('users_prices')
                ->leftJoin('users', 'users.id', '=', 'users_prices.user_id')
                ->select('users.name as user_name','users.id as user_id' , 'users_prices.new_month', 'users_prices.price')
                ->where('users_prices.is_paid', 0)
                ->where('users.is_enabled', 1)
                ->where('users_prices.price', '>', 0)
                ->where('users_prices.new_month', '<', $currentMonth)
                ->get();

            // Добавляем проверку на наличие данных
            if ($usersWithUnpaidPrices->isEmpty()) {
                // Возвращаем пустую таблицу, но в корректном формате для DataTables
                return response()->json([
                    'draw' => $request->get('draw'), // draw должен быть передан DataTables
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [] // Пустой массив данных
                ]);
            }

            return DataTables::of($usersWithUnpaidPrices)
                ->addIndexColumn()
                ->addColumn('user_name', function($row) {
                    return $row->user_name ? $row->user_name : 'Без имени'; // Проверяем наличие имени пользователя
                })
                ->addColumn('month', function($row) {
                    return $row->new_month; // Месяц
                })
                ->addColumn('price', function($row) {
                    return number_format($row->price, 2) . ' руб'; // Формат цены
                })
                ->make(true);
        }
    }

}