<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class LtvReportController extends Controller
{
    // Отчёт LTV
    public function ltv()
    {
        return view('admin.report.index', ['activeTab' => 'ltv']);
    }

    // Данные для отчёта LTV
    public function getLtv(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = app('current_partner')->id;

        // LTV по финальным платежам: таблица payments
        $usersWithTotalPaid = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->selectRaw("
                TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) as user_name,
                users.id as user_id,
                SUM(payments.summ) as total_price,
                users.is_enabled,
                MIN(payments.operation_date) as first_payment_date,
                MAX(payments.operation_date) as last_payment_date,
                COUNT(payments.id) as payment_count
            ")
            ->where('payments.summ', '>', 0)
        ->where('users.partner_id', $partnerId)
        ->groupBy('users.id', 'users.lastname', 'users.name', 'users.is_enabled')
        ->get();

        // Если нет данных — отдаём корректный формат для DataTables
        if ($usersWithTotalPaid->isEmpty()) {
            return response()->json([
                'draw' => $request->get('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        return DataTables::of($usersWithTotalPaid)
            ->addIndexColumn()
            ->addColumn('user_name', function ($row) {
                return $row->user_name ?: 'Без имени';
            })
            ->addColumn('total_price', function ($row) {
                // LTV в виде числа, форматирование — на фронте
                return (float) $row->total_price;
            })
            ->make(true);
    }

    public function formatedDate($month)
    {
        $months = [
            'Январь'   => 'January',
            'Февраль'  => 'February',
            'Март'     => 'March',
            'Апрель'   => 'April',
            'Май'      => 'May',
            'Июнь'     => 'June',
            'Июль'     => 'July',
            'Август'   => 'August',
            'Сентябрь' => 'September',
            'Октябрь'  => 'October',
            'Ноябрь'   => 'November',
            'Декабрь'  => 'December',
        ];

        $parts = explode(' ', $month);
        if (count($parts) === 2 && isset($months[$parts[0]])) {
            $month = $months[$parts[0]] . ' ' . $parts[1];
        } else {
            return null;
        }

        try {
            $date = \DateTime::createFromFormat('F Y', $month);
            if ($date) {
                return $date->format('Y-m-01');
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }
}