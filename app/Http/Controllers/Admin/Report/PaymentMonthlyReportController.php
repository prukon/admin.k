<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\Payment;
use App\Models\PaymentSystem;
use App\Services\PartnerContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class PaymentMonthlyReportController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    /**
     * Вкладка "Платежи по месяцам".
     * Отрисовываем тот же layout, что и остальные отчёты.
     */
    public function index()
    {
        $partnerId = $this->requirePartnerId();

        DB::enableQueryLog();

        $totalPaidPrice = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->sum('payments.summ');

        $totalPaidPrice = number_format($totalPaidPrice, 0, '', ' ');

        $tbankPs      = PaymentSystem::where('partner_id', $partnerId)
            ->where('name', 'tbank')
            ->first();
        $tbankEnabled = (bool) $tbankPs;

        return view('admin.report.index', [
            'activeTab'      => 'payment-monthly',
            'totalPaidPrice' => $totalPaidPrice,
            'tbankEnabled'   => $tbankEnabled,
        ]);
    }

    /**
     * Данные для таблицы "месяцы".
     * Одна строка = один календарный месяц по operation_date.
     */
    public function getMonths(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();
        $hasOrder  = is_array($request->input('order')) && count($request->input('order')) > 0;

        $monthsQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->whereNotNull('payments.operation_date')
            ->selectRaw('DATE_FORMAT(payments.operation_date, "%Y-%m-01") as month_start')
            ->selectRaw('DATE_FORMAT(payments.operation_date, "%Y-%m") as month_key')
            ->selectRaw('COUNT(*) as payments_count')
            ->selectRaw('SUM(payments.summ) as total_sum')
            ->groupBy('month_start', 'month_key');

        if (! $hasOrder) {
            $monthsQuery->orderBy('month_start', 'desc');
        }

        return DataTables::of($monthsQuery)
            ->addIndexColumn()
            ->addColumn('month_title', function ($row) {
                $date = Carbon::parse($row->month_start);

                $monthNames = [
                    1  => 'Январь',
                    2  => 'Февраль',
                    3  => 'Март',
                    4  => 'Апрель',
                    5  => 'Май',
                    6  => 'Июнь',
                    7  => 'Июль',
                    8  => 'Август',
                    9  => 'Сентябрь',
                    10 => 'Октябрь',
                    11 => 'Ноябрь',
                    12 => 'Декабрь',
                ];

                $monthName = $monthNames[(int) $date->month] ?? $date->format('m');

                return $monthName . ' ' . $date->year;
            })
            ->editColumn('total_sum', function ($row) {
                return (float) $row->total_sum;
            })
            ->orderColumn('month_title', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('month_start', $dir);
            })
            ->orderColumn('payments_count', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('payments_count', $dir);
            })
            ->orderColumn('total_sum', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('total_sum', $dir);
            })
            ->make(true);
    }

    /**
     * Детализация: все платежи за указанный календарный месяц (YYYY-MM).
     */
    public function getMonthPayments(Request $request, string $yearMonth)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        try {
            $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        } catch (\Exception $e) {
            abort(400, 'Некорректный формат месяца');
        }

        $end = $start->copy()->endOfMonth();

        $payments = Payment::query()
            ->with(['user.team'])
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('users.partner_id', $partnerId)
            ->whereBetween('payments.operation_date', [$start, $end])
            ->select('payments.*')
            ->orderBy('payments.operation_date', 'desc')
            ->get();

        $items = $payments->map(function (Payment $row) {
            $userName = 'Без пользователя';

            $user = $row->user;
            if ($user) {
                $full = trim(($user->lastname ?? '') . ' ' . ($user->name ?? ''));
                if ($full !== '') {
                    $userName = $full;
                }
            }

            if ($userName === 'Без пользователя' && ! empty($row->user_name)) {
                $userName = (string) $row->user_name;
            }

            $teamTitle = ($row->user && $row->user->team)
                ? $row->user->team->title
                : 'Без команды';

            $provider = (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status))
                ? 'tbank'
                : 'robokassa';

            return [
                'id'               => (int) $row->id,
                'user_name'        => $userName,
                'team_title'       => $teamTitle,
                'summ'             => (float) $row->summ,
                'payment_month'    => $row->payment_month,
                'operation_date'   => $row->operation_date,
                'payment_provider' => $provider,
            ];
        })->all();

        return response()->json([
            'month_key' => $yearMonth,
            'payments'  => $items,
        ]);
    }
}