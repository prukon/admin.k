<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\Location;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\PartnerContext;
use App\Support\UserTeamQuery;
use App\Models\UserTableSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class PaymentMonthlyReportController extends AdminBaseController
{
    private const TABLE_KEY = 'reports_payments_monthly';

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
            ->where('users.partner_id', $partnerId);
        $this->applyMonthlyReportFilters($totalQuery, $request, $partnerId);

        $totalRaw = $totalQuery->sum('payments.summ');
        $totalPaidPrice = number_format((float) $totalRaw, 0, '', ' ');

        $tbankPs = PaymentSystem::where('partner_id', $partnerId)
            ->where('name', 'tbank')
            ->first();

        $tbankEnabled = (bool) $tbankPs;

        $paymentsFilterUser = $this->resolveMonthlyFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolveMonthlyFilterTeamLabel($partnerId, $filters);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canViewTrainers = $authUser?->can('trainers.view') ?? false;
        $paymentsFilterTrainer = $canViewTrainers
            ? $this->resolveMonthlyFilterTrainerLabel($partnerId, $filters)
            : null;
        $canViewLocations = $authUser?->can('locations.view') ?? false;
        $activeLocations = $canViewLocations
            ? Location::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('admin.report.index', [
            'activeTab'          => 'payment-monthly',
            'totalPaidPrice'     => $totalPaidPrice,
            'tbankEnabled'       => $tbankEnabled,
            'filters'            => $filters,
            'paymentsFilterUser' => $paymentsFilterUser,
            'paymentsFilterTeam' => $paymentsFilterTeam,
            'paymentsFilterTrainer' => $paymentsFilterTrainer,
            'canViewTrainers'    => $canViewTrainers,
            'canViewLocations'   => $canViewLocations,
            'activeLocations'    => $activeLocations,
        ]);
    }

    /**
     * Сумма платежей по тем же фильтрам, что и таблица (шапка без перезагрузки страницы).
     */
    public function total(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $totalQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId);

        $this->applyMonthlyReportFilters($totalQuery, $request, $partnerId);

        $raw = $totalQuery->sum('payments.summ');

        return response()->json([
            'total_formatted' => number_format((float) $raw, 0, '', ' '),
            'total_raw'       => (float) $raw,
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
            ->where('users.partner_id', $partnerId);

        $this->applyMonthlyReportFilters($monthsQuery, $request, $partnerId);

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
            Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        } catch (\Exception $e) {
            abort(400, 'Некорректный формат месяца');
        }

        $paymentsQuery = $this->buildMonthPaymentsQuery($request, $partnerId, $yearMonth, $mode);

        if ($request->has('draw')) {
            $stats = DB::query()
                ->fromSub(clone $paymentsQuery, 'monthly_payments')
                ->selectRaw('COUNT(*) as payments_count, COALESCE(SUM(summ), 0) as sum_total')
                ->first();

            return DataTables::of($paymentsQuery)
                ->addColumn('user_name', function ($row) {
                    $full = trim(($row->user_lastname ?? '').' '.($row->user_firstname ?? ''));
                    if ($full !== '') {
                        return $full;
                    }

                    return ! empty($row->payment_user_name) ? (string) $row->payment_user_name : 'Без пользователя';
                })
                ->addColumn('team_title', fn ($row) => $row->team_title ?: 'Без команды')
                ->addColumn('payment_provider', fn ($row) => $this->resolvePaymentProvider($row))
                ->editColumn('summ', fn ($row) => (float) $row->summ)
                ->with('meta_payments_count', (int) ($stats->payments_count ?? 0))
                ->with('meta_sum_total', (float) ($stats->sum_total ?? 0))
                ->make(true);
        }

        $payments = (clone $paymentsQuery)->get();

        $items = $payments->map(function ($row) {
            $userName = trim(($row->user_lastname ?? '').' '.($row->user_firstname ?? ''));
            if ($userName === '' && ! empty($row->payment_user_name)) {
                $userName = (string) $row->payment_user_name;
            }
            if ($userName === '') {
                $userName = 'Без пользователя';
            }

            return [
                'id'               => (int) $row->id,
                'user_name'        => $userName,
                'team_title'       => $row->team_title ?: 'Без команды',
                'summ'             => (float) $row->summ,
                'payment_month'    => $row->payment_month,
                'operation_date'   => $row->operation_date,
                'payment_provider' => $this->resolvePaymentProvider($row),
            ];
        })->all();

        return response()->json([
            'month_key' => $yearMonth,
            'mode'      => $mode,
            'payments'  => $items,
        ]);
    }

    public function getColumnsSettings()
    {
        $this->requirePartnerId();

        $settings = UserTableSetting::query()
            ->where('user_id', (int) Auth::id())
            ->where('table_key', self::TABLE_KEY)
            ->first();

        $columns = $settings?->columns;
        if (! is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(Request $request)
    {
        $this->requirePartnerId();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $normalized = [];
        foreach ((array) $data['columns'] as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[(string) $key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => (int) Auth::id(),
                'table_key' => self::TABLE_KEY,
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildMonthPaymentsQuery(Request $request, int $partnerId, string $yearMonth, string $mode)
    {
        $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $teamTitlesSub = UserTeamQuery::sqlStudentTeamTitlesSubquery($partnerId);

        $paymentsQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->select(
                'payments.id',
                'payments.summ',
                'payments.payment_month',
                'payments.operation_date',
                'payments.deal_id',
                'payments.payment_id',
                'payments.payment_status',
                'payments.user_name as payment_user_name',
                'users.name as user_firstname',
                'users.lastname as user_lastname',
            )
            ->selectRaw("{$teamTitlesSub} as team_title");

        $this->applyMonthlyReportFilters($paymentsQuery, $request, $partnerId);

        if ($mode === 'subscription') {
            $paymentsQuery
                ->whereNotNull('payments.payment_month')
                ->where('payments.payment_month', 'LIKE', $yearMonth.'-%');
        } else {
            $paymentsQuery->whereBetween('payments.operation_date', [$start, $end]);
        }

        return $paymentsQuery->orderBy('payments.operation_date', 'desc');
    }

    private function resolvePaymentProvider(object $row): string
    {
        return (! empty($row->deal_id) || ! empty($row->payment_id) || ! empty($row->payment_status))
            ? 'tbank'
            : 'robokassa';
    }

    /**
     * Те же правила, что у отчёта «Все платежи», кроме способа оплаты и статуса возврата (нужны join'ы intent/refunds).
     *
     * @param  QueryBuilder|EloquentBuilder  $paymentsQuery
     */
    private function applyMonthlyReportFilters($paymentsQuery, Request $request, int $partnerId): void
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

        UserTeamQuery::applyReportTeamFilters(
            $paymentsQuery,
            $partnerId,
            $request->query('filter_team_id'),
            $request->filled('team_title') ? (string) $request->query('team_title') : null,
        );

        UserTeamQuery::applyReportTrainerTeamFilter(
            $paymentsQuery,
            $partnerId,
            $request->query('filter_trainer_profile_id'),
        );

        /** @var \App\Models\User|null $filterActor */
        $filterActor = Auth::user();
        if ($filterActor?->can('locations.view')) {
            $filterLocationId = $request->query('filter_location_id');
            if ($filterLocationId !== null && $filterLocationId !== '') {
                if ($filterLocationId === 'none') {
                    $paymentsQuery->whereNull('payments.location_id');
                } elseif (ctype_digit((string) $filterLocationId)) {
                    $lid = (int) $filterLocationId;
                    if ($lid > 0) {
                        $paymentsQuery->where('payments.location_id', $lid);
                    }
                }
            }
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

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveMonthlyFilterTrainerLabel(int $partnerId, array $filters): ?array
    {
        $raw = $filters['filter_trainer_profile_id'] ?? null;
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }
        $tpid = (int) $raw;
        if ($tpid <= 0) {
            return null;
        }

        $profile = TrainerProfile::query()
            ->where('partner_id', $partnerId)
            ->whereKey($tpid)
            ->with('user:id,name,lastname')
            ->first(['id', 'user_id']);

        if (! $profile || ! $profile->user) {
            return null;
        }

        $u = $profile->user;
        $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));

        return [
            'id' => $profile->id,
            'text' => $text !== '' ? $text : '—',
        ];
    }
}