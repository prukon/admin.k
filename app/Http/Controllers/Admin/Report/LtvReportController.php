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

class LtvReportController extends Controller
{
    //Отчет LTV
    public function ltv()
    {
        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');

        $ltvPrice = DB::table('users_prices')
            ->leftJoin('users', 'users.id', '=', 'users_prices.user_id')
            ->where('users_prices.is_paid', 0)
            ->where('users.is_enabled', 1)
            ->where('users_prices.price', '>', 0)
            ->where('users_prices.new_month', '<', $currentMonth)
            ->sum('users_prices.price');

        $ltvPrice = number_format($ltvPrice, 0, '', ' ');

        return view('admin.report.index', ['activeTab' => 'ltv'],
            compact('ltvPrice'));


    }

    //Данные для отчета LTV
    public function getLtv(Request $request)
    {

        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');

//         dd($currentMonth);
        if ($request->ajax()) {


            $usersWithTotalUnpaidPrices = DB::table('users_prices')
                ->leftJoin('users', 'users.id', '=', 'users_prices.user_id')
                ->select(
                    'users.name as user_name',
                    'users.id as user_id',
                    DB::raw('SUM(users_prices.price) as total_price'),
                    'users.is_enabled', // Добавляем статус пользователя
                    DB::raw('MIN(users_prices.created_at) as first_payment_date'), // Дата первого платежа
                    DB::raw('MAX(users_prices.created_at) as last_payment_date'),  // Дата последнего платежа
                    DB::raw('COUNT(users_prices.id) as payment_count')             // Количество платежей
                )
                ->where('users_prices.price', '>', 0)
                ->groupBy('users.id', 'users.name', 'users.is_enabled')
                ->get();


            // Добавляем проверку на наличие данных
            if ($usersWithTotalUnpaidPrices->isEmpty()) {
                // Возвращаем пустую таблицу, но в корректном формате для DataTables
                return response()->json([
                    'draw' => $request->get('draw'), // draw должен быть передан DataTables
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [] // Пустой массив данных
                ]);
            }

            return DataTables::of($usersWithTotalUnpaidPrices)
                ->addIndexColumn()
                ->addColumn('user_name', function ($row) {
                    return $row->user_name ? $row->user_name : 'Без имени'; // Проверяем наличие имени пользователя
                })
//                ->addColumn('month', function($row) {
//                    return $row->new_month; // Месяц
//                })
                ->addColumn('total_price', function ($row) {
//                    return number_format($row->total_price, 2) . ' руб'; // Формат цены
                    $totalPrice = (float)$row->total_price; // Приведение к числу
                    return $totalPrice;


                })
                ->make(true);
        }
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
}