<?php

namespace App\Http\Controllers\User\Report;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
//use function Termwind\dd;
//use function Termwind\dd;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $curUser; // Определяем свойство для хранения текущего пользователя

//    public function __construct()
//    {
//        $this->middleware(['auth', 'role:user']); // Применяем middleware auth и role:user
//    }

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
            Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }

    public function showUserPayments()
    {
        $this->curUser  = auth()->user();
//        dd($this->curUser->id); // Отладка текущего пользователя


        $totalPaidPrice = DB::table('payments')
            ->where('user_id', $this->curUser->id) // Сначала фильтрация по user_id
            ->sum('payments.summ');      // Затем вычисление суммы



            
//        dd($totalPaidPrice);
        $totalPaidPrice = number_format($totalPaidPrice, 0, '', ' ');


        return view('user.report.payment', ['activeTab' => 'payments'],
            compact('totalPaidPrice'));
    }

    public function getUserPayments(Request $request)
    {
        $this->curUser  = auth()->user();


        if ($request->ajax()) {
            $payments = Payment::with(['user.team'])
                ->where('user_id', $this->curUser->id) // Сначала фильтрация по user_id
                ->get();


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
                    return number_format($row->summ, 0) . ' руб'; // Формат суммы
                })
                ->addColumn('operation_date', function ($row) {
                    return $row->operation_date; // Дата операции
                })
                ->make(true);
        }
    }

}