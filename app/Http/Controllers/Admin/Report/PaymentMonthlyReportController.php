<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\Payment;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
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
     * Страница отчёта "Платежи по месяцам".
     */
    public function index(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $filters = $request->query();

        $totalQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('users.partner_id', $partnerId);
        $this->applyMonthlyReportFilters($totalQuery, $request);

        $totalRaw = $totalQuery->sum('payments.summ');
        $totalPaidPrice = number_format((float) $totalRaw, 0, '', ' ');

        $tbankPs = PaymentSystem::where('partner_id', $partnerId)
            ->where('name', 'tbank')
            ->first();

        $tbankEnabled = (bool) $tbankPs;

        $paymentsFilterUser = $this->resolveMonthlyFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolveMonthlyFilterTeamLabel($partnerId, $filters);

        return view('admin.report.index', [
            'activeTab'          => 'payment-monthly',
            'totalPaidPrice'     => $totalPaidPrice,
            'tbankEnabled'       => $tbankEnabled,
            'filters'            => $filters,
            'paymentsFilterUser' => $paymentsFilterUser,
            'paymentsFilterTeam' => $paymentsFilterTeam,
        ]);
    }

    /**
     * Сводка по месяцам.
     * Режим задаётся параметром ?mode=operation|subscription.
     *
     * mode=operation    -> группируем по payments.operation_date
     * mode=subscription -> группируем по payments.payment_month (varchar YYYY-MM-DD)
     */
    public function getMonths(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        $mode = $request->get('mode', 'subscription');
        if (! in_array($mode, ['operation', 'subscription'], true)) {
            $mode = 'subscription';
        }

        $hasOrder = is_array($request->input('order')) && count($request->input('order')) > 0;

        $monthsQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('users.partner_id', $partnerId);

        $this->applyMonthlyReportFilters($monthsQuery, $request);

        if ($mode === 'subscription') {
            // Группировка по месяцу абонемента (payment_month: varchar 'YYYY-MM-DD')
            $monthsQuery
                ->whereNotNull('payments.payment_month')
                ->where('payments.payment_month', 'LIKE', '____-__-%')
                ->selectRaw("CONCAT(LEFT(payments.payment_month, 7), '-01') as month_start")
                ->selectRaw("LEFT(payments.payment_month, 7)                as month_key") // YYYY-MM
                ->selectRaw('COUNT(*)                                       as payments_count')
                ->selectRaw('SUM(payments.summ)                             as total_sum')
                ->groupBy('month_start', 'month_key');
        } else {
            // Группировка по дате платежа (operation_date)
            $monthsQuery
                ->whereNotNull('payments.operation_date')
                ->selectRaw('DATE_FORMAT(payments.operation_date, "%Y-%m-01") as month_start')
                ->selectRaw('DATE_FORMAT(payments.operation_date, "%Y-%m")    as month_key')
                ->selectRaw('COUNT(*)                                         as payments_count')
                ->selectRaw('SUM(payments.summ)                               as total_sum')
                ->groupBy('month_start', 'month_key');
        }

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
     * Детализация за конкретный месяц.
     * mode=operation    -> фильтр по operation_date в рамках месяца
     * mode=subscription -> фильтр по LEFT(payment_month, 7) = YYYY-MM
     */
    public function getMonthPayments(Request $request, string $yearMonth)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        $mode = $request->get('mode', 'subscription');
        if (! in_array($mode, ['operation', 'subscription'], true)) {
            $mode = 'subscription';
        }

        try {
            $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        } catch (\Exception $e) {
            abort(400, 'Некорректный формат месяца');
        }

        $end = $start->copy()->endOfMonth();

        $paymentsQuery = Payment::query()
            ->with(['user.team'])
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('users.partner_id', $partnerId)
            ->select('payments.*');

        $this->applyMonthlyReportFilters($paymentsQuery, $request);

        if ($mode === 'subscription') {
            // По месяцу абонемента: payment_month LIKE 'YYYY-MM-%'
            $paymentsQuery
                ->whereNotNull('payments.payment_month')
                ->where('payments.payment_month', 'LIKE', $yearMonth . '-%');
        } else {
            // По дате операции
            $paymentsQuery
                ->whereBetween('payments.operation_date', [$start, $end]);
        }

        $payments = $paymentsQuery
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
            'mode'      => $mode,
            'payments'  => $items,
        ]);
    }

    /**
     * Те же правила, что у отчёта «Все платежи», кроме способа оплаты и статуса возврата (нужны join'ы intent/refunds).
     *
     * @param  QueryBuilder|EloquentBuilder  $paymentsQuery
     */
    private function applyMonthlyReportFilters($paymentsQuery, Request $request): void
    {
        $filterUserId = $request->query('filter_user_id');
        if ($filterUserId !== null && $filterUserId !== '' && ctype_digit((string) $filterUserId)) {
            $uid = (int) $filterUserId;
            if ($uid > 0) {
                $paymentsQuery->where('users.id', $uid);
            }
        } elseif ($request->filled('user_name')) {
            $needle = '%'.trim((string) $request->query('user_name')).'%';
            $paymentsQuery->where(function ($q) use ($needle) {
                $q->whereRaw("CONCAT_WS(' ', users.lastname, users.name) LIKE ?", [$needle])
                    ->orWhere('payments.user_name', 'like', $needle);
            });
        }

        $filterTeamId = $request->query('filter_team_id');
        if ($filterTeamId !== null && $filterTeamId !== '' && ctype_digit((string) $filterTeamId)) {
            $tid = (int) $filterTeamId;
            if ($tid > 0) {
                $paymentsQuery->where('users.team_id', $tid);
            }
        } elseif ($request->filled('team_title')) {
            $like = '%'.trim((string) $request->query('team_title')).'%';
            $paymentsQuery->where('teams.title', 'like', $like);
        }

        if ($request->filled('payment_month')) {
            $ym = trim((string) $request->query('payment_month'));
            if (preg_match('/^\d{4}-\d{2}$/', $ym) === 1) {
                $paymentsQuery->where('payments.payment_month', 'like', $ym.'%');
            }
        }

        if ($request->filled('operation_date_from')) {
            $paymentsQuery->whereDate('payments.operation_date', '>=', (string) $request->query('operation_date_from'));
        }
        if ($request->filled('operation_date_to')) {
            $paymentsQuery->whereDate('payments.operation_date', '<=', (string) $request->query('operation_date_to'));
        }

        if ($request->filled('payment_provider')) {
            $p = (string) $request->query('payment_provider');
            if ($p === 'tbank') {
                $paymentsQuery->where(function ($w) {
                    $w->where(function ($x) {
                        $x->whereNotNull('payments.deal_id')->where('payments.deal_id', '<>', '');
                    })->orWhere(function ($x) {
                        $x->whereNotNull('payments.payment_id')->where('payments.payment_id', '<>', '');
                    })->orWhere(function ($x) {
                        $x->whereNotNull('payments.payment_status')->where('payments.payment_status', '<>', '');
                    });
                });
            } elseif ($p === 'robokassa') {
                $paymentsQuery->where(function ($w) {
                    $w->where(function ($x) {
                        $x->whereNull('payments.deal_id')->orWhere('payments.deal_id', '=', '');
                    })->where(function ($x) {
                        $x->whereNull('payments.payment_id')->orWhere('payments.payment_id', '=', '');
                    })->where(function ($x) {
                        $x->whereNull('payments.payment_status')->orWhere('payments.payment_status', '=', '');
                    });
                });
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveMonthlyFilterUserLabel(int $partnerId, array $filters): ?array
    {
        $raw = $filters['filter_user_id'] ?? null;
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }
        $uid = (int) $raw;
        if ($uid <= 0) {
            return null;
        }

        $u = User::query()
            ->where('partner_id', $partnerId)
            ->where('id', $uid)
            ->first(['id', 'name', 'lastname']);

        if (! $u) {
            return null;
        }

        $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));

        return [
            'id'   => $u->id,
            'text' => $text !== '' ? $text : '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveMonthlyFilterTeamLabel(int $partnerId, array $filters): ?array
    {
        $raw = $filters['filter_team_id'] ?? null;
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }
        $tid = (int) $raw;
        if ($tid <= 0) {
            return null;
        }

        $t = Team::query()
            ->where('partner_id', $partnerId)
            ->where('id', $tid)
            ->first(['id', 'title']);

        if (! $t) {
            return null;
        }

        return [
            'id'   => $t->id,
            'text' => (string) ($t->title ?? ''),
        ];
    }
}