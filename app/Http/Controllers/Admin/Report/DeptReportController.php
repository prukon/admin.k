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

class DeptReportController extends Controller
{
    //Отчет Задолженности
    public function debts()
    {
        $partnerId = app('current_partner')->id;

        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');


        $totalUnpaidPrice = DB::table('users_prices')
            ->join('users', 'users.id', '=', 'users_prices.user_id')   // INNER JOIN с users
            ->where('users_prices.is_paid', 0)                         // счет не оплачен
            ->where('users.is_enabled', 1)                             // пользователь активен
            ->where('users_prices.price', '>', 0)                      // цена положительна
            ->where('users_prices.new_month', '<', $currentMonth)      // месяц в прошлом
            ->where('users.partner_id', $partnerId)                             // партнёр = 1
            ->sum('users_prices.price');                               // суммируем

        $totalUnpaidPrice = number_format($totalUnpaidPrice, 0, '', ' ');

        return view('admin.report.index', ['activeTab' => 'debt'],
            compact("totalUnpaidPrice"));
    }

    //Данные для отчета Задолженности
    public function getDebts(Request $request)
    {
        $partnerId = app('current_partner')->id;

        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');

//         dd($currentMonth);
        if ($request->ajax()) {

            //            старая реазиация
            $usersWithUnpaidPrices = DB::table('users_prices')
                ->leftJoin('users', 'users.id', '=', 'users_prices.user_id')
                ->select('users.name as user_name', 'users.id as user_id', 'users_prices.new_month', 'users_prices.price')
                ->where('users_prices.is_paid', 0)
                ->where('users.is_enabled', 1)
                ->where('users_prices.price', '>', 0)
                ->where('users_prices.new_month', '<', $currentMonth)
                ->get();

            $usersWithUnpaidPrices = DB::table('users_prices')
                ->join('users', 'users.id', '=', 'users_prices.user_id')          // INNER JOIN: берём только записи с найденным user
                ->select(
                    'users.name  as user_name',
                    'users.id    as user_id',
                    'users_prices.new_month',
                    'users_prices.price'
                )
                ->where('users_prices.is_paid', 0)                                // счёт не оплачен
                ->where('users.is_enabled', 1)                                    // пользователь активен
                ->where('users_prices.price', '>', 0)                             // цена положительна
                ->where('users_prices.new_month', '<', $currentMonth)             // месяц в прошлом
                ->where('users.partner_id', $partnerId)                           // у пользователя партнёр = $partnerId
                ->get();


            $usersWithUnpaidPrices = DB::table('users_prices')
                ->join('users', 'users.id', '=', 'users_prices.user_id')
//                ->select(
//                    'users.name  as user_name',
//                    'users.id    as user_id',
//                    'users_prices.new_month',
//                    'users_prices.price'
//                )

                ->selectRaw("TRIM(CONCAT(COALESCE(users.lastname,''),' ',COALESCE(users.name,''))) as user_name")
                ->addSelect('users.id as user_id', 'users_prices.new_month', 'users_prices.price')

                ->where('users_prices.is_paid', 0)
                ->where('users.is_enabled', 1)
                ->where('users_prices.price', '>', 0)
                ->where('users_prices.new_month', '<', $currentMonth)
                ->where('users.partner_id', $partnerId)
                ->get();

            return DataTables::of($usersWithUnpaidPrices)
                ->addIndexColumn()
                ->addColumn('month', fn($row) => $row->new_month)
    ->addColumn('price', fn($row) => (float)$row->price)
    ->make(true);


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
//                ->addColumn('user_name', function ($row) {
//                    return $row->user_name ? $row->user_name : 'Без имени'; // Проверяем наличие имени пользователя
//                })
                ->addColumn('month', function ($row) {
                    return $row->new_month; // Месяц
                })
                ->addColumn('price', function ($row) {
//                    return number_format($row->price, 2) . ' руб'; // Формат цены

                    return (float)$row->price;
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