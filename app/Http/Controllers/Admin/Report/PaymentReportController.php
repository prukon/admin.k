<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;


class PaymentReportController extends Controller
{
    //Отчет Платежи

    public function payments()
    {
        // 1) партнёр
        $partnerId = app('current_partner')->id;
        \Log::debug('[payments] Partner ID', ['partnerId' => $partnerId]);

        // 2) включаем лог запросов
        DB::enableQueryLog();

        // 3) считаем сумму
        $totalPaidPrice = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->sum('payments.summ');

        //С учетом удаленных юзеров
//        $totalPaidPriceRaw = DB::table('payments')
//            ->leftJoin('users', 'users.id', '=', 'payments.user_id')// оставляем строки без сопоставленного user
//            ->whereNull('users.id')// user не найден
////            ->where('users.partner_id', $partnerId)
//            ->sum('payments.summ');                                  // суммируем

//            старая реазиация
        $totalPaidPrice = DB::table('payments')
            ->sum('payments.summ');


        \Log::debug('[payments] Raw total', ['totalPaidPriceRaw' => $totalPaidPrice]);

        // 4) SQL‑лог
        \Log::debug('[payments] Executed query', DB::getQueryLog()[0] ?? []);

        // 5) форматируем сумму
        $totalPaidPrice = number_format($totalPaidPrice, 0, '', ' ');
        \Log::debug('[payments] Formatted total', ['totalPaidPrice' => $totalPaidPrice]);

        // 6) представление
        return view(
            'admin.report.index',
            ['activeTab' => 'payment', 'totalPaidPrice' => $totalPaidPrice]
        );
    }

    //Данные для отчета Платежи
    public function getPayments(Request $request)
    {
        $partnerId = app('current_partner')->id;

        if ($request->ajax()) {

            //через свой флаг
//            $payments = Payment::with(['user.team'])
//                ->where('partner_id', $partnerId)
//                ->get();

            //через User
            $payments = Payment::with(['user.team'])
                ->join('users', 'users.id', '=', 'payments.user_id')// связываем payments с users
                ->where('users.partner_id', $partnerId)// берём только партнёра user
                ->get();

//            старая реазиация
            $payments = Payment::with(['user.team'])->get();

            return DataTables::of($payments)
                ->addIndexColumn()
                ->addColumn('user_name', function ($row) {
                    // Проверяем, есть ли пользователь в таблице payments
                    return $row->user_name
                        ? $row->user_name // Возвращаем имя пользователя из payments
                        : ($row->user ? $row->user->name : 'Без пользователя'); // Или из связанной модели, если нет в payments
                })
                ->addColumn('user_id', function ($row) {
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
//                    return number_format($row->summ, 0) . ' руб'; // Формат суммы
                    return (float)$row->summ;

                })
                ->addColumn('operation_date', function ($row) {
                    return $row->operation_date; // Дата операции
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