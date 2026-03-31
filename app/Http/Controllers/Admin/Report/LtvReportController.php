<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Services\PartnerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class LtvReportController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    /**
     * Страница отчёта LTV (подключается через admin.report.index, вкладка LTV).
     */
    public function ltv()
    {
        return view('admin.report.index', ['activeTab' => 'ltv']);
    }

    /**
     * Данные для основной таблицы LTV (агрегация по ученикам).
     *
     * Формат строк:
     * - user_id
     * - user_name
     * - team_title
     * - total_price (LTV)
     * - payment_count
     * - first_payment_date
     * - last_payment_date
     * - is_enabled
     */
    public function getLtv(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        // Агрегация по таблице payments
        $baseQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('payments.summ', '>', 0)
            ->where('users.partner_id', $partnerId)
            ->selectRaw("
                users.id as user_id,
                TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) as user_name,
                teams.title as team_title,
                SUM(payments.summ) as total_price,
                COUNT(payments.id) as payment_count,
                MIN(payments.operation_date) as first_payment_date,
                MAX(payments.operation_date) as last_payment_date,
                users.is_enabled
            ")
            ->groupBy(
                'users.id',
                'users.lastname',
                'users.name',
                'users.is_enabled',
                'teams.title'
            );

        return DataTables::of($baseQuery)
            ->addIndexColumn()
            ->addColumn('user_name', function ($row) {
                return $row->user_name ?: 'Без имени';
            })
            ->addColumn('team_title', function ($row) {
                return $row->team_title ?: 'Без команды';
            })
            ->addColumn('total_price', function ($row) {
                return (float) $row->total_price;
            })
            // на всякий случай явно укажем сортировки по числовым полям
            ->orderColumn('total_price', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('total_price', $dir);
            })
            ->orderColumn('payment_count', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('payment_count', $dir);
            })
            ->make(true);
    }

    /**
     * Детализация LTV: все платежи конкретного ученика.
     * Используется при раскрытии строки в отчёте LTV.
     */
    public function getUserPayments(Request $request, int $userId)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        $payments = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('users.partner_id', $partnerId)
            ->where('users.id', $userId)
            ->where('payments.summ', '>', 0)
            ->selectRaw("
                payments.id,
                payments.summ,
                payments.payment_month,
                payments.operation_date,
                payments.payment_number,
                payments.deal_id,
                payments.payment_id,
                payments.payment_status,
                TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) as user_name,
                teams.title as team_title
            ")
            ->orderBy('payments.operation_date', 'desc')
            ->get();

        $items = $payments->map(function ($row) {
            $provider = (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status))
                ? 'tbank'
                : 'robokassa';

            return [
                'id'               => (int) $row->id,
                'user_name'        => $row->user_name ?: 'Без имени',
                'team_title'       => $row->team_title ?: 'Без команды',
                'summ'             => (float) $row->summ,
                'payment_month'    => $row->payment_month,
                'operation_date'   => $row->operation_date,
                'payment_provider' => $provider,
            ];
        })->all();

        return response()->json([
            'user_id'  => $userId,
            'payments' => $items,
        ]);
    }

    /**
     * Старый вспомогательный метод (если где-то используется для парсинга "Март 2026" → "2026-03-01").
     * Если он больше нигде не нужен, можно смело удалить.
     */
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
            $date = \DateTime::createFromFormat('!F Y', $month);
            if ($date) {
                return $date->format('Y-m-01');
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }
}