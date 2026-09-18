<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\ColumnsSettingsWithPageLengthSaveRequest;
use App\Http\Requests\Admin\Report\LtvLocationsReportPeriodRequest;
use App\Models\Location;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\PartnerContext;
use App\Services\Reports\LocationAverageAttendanceAggregator;
use App\Services\TrainerOwnTeamsScope;
use App\Support\UserTeamQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class LtvLocationsReportController extends AdminBaseController
{
    private const TABLE_KEY = 'reports_ltv_locations';

    public function __construct(
        PartnerContext $partnerContext,
        private readonly LocationAverageAttendanceAggregator $locationAverageAttendanceAggregator,
    ) {
        parent::__construct($partnerContext);
    }

    /**
     * Страница отчёта «Платежи по объектам» (вкладка на admin.report.index).
     */
    public function index(LtvLocationsReportPeriodRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $filters = $request->query();

        $totalQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.summ_cents', '>', 0)
            ->where('users.partner_id', $partnerId);
        $this->applyLtvLocationsReportFilters($totalQuery, $request, $partnerId);

        $totalRawCents = (int) $totalQuery->sum('payments.summ_cents');
        $totalPaidPrice = number_format($totalRawCents / 100, 0, '', ' ');

        $paymentsFilterUser = $this->resolveLtvLocationsFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolveLtvLocationsFilterTeamLabel($partnerId, $filters);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canViewTrainers = $authUser?->can('trainers.view') ?? false;
        $paymentsFilterTrainer = $canViewTrainers
            ? $this->resolveLtvLocationsFilterTrainerLabel($partnerId, $filters)
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
            'activeTab'          => 'ltv-locations',
            'totalPaidPrice'     => $totalPaidPrice,
            'filters'            => $filters,
            'paymentsFilterUser' => $paymentsFilterUser,
            'paymentsFilterTeam' => $paymentsFilterTeam,
            'paymentsFilterTrainer' => $paymentsFilterTrainer,
            'canViewTrainers'    => $canViewTrainers,
            'canViewLocations'   => $canViewLocations,
            'activeLocations'    => $activeLocations,
            'ltvLocationsPageLength' => UserTableSetting::pageLengthForUser(
                Auth::id() !== null ? (int) Auth::id() : null,
                self::TABLE_KEY
            ),
            'ltvLocationsPeriod' => $request->period(),
            'ltvLocationsPeriodLabels' => $request->periodLabels(),
            'ltvLocationsMode' => $request->mode(),
        ]);
    }

    /**
     * Сумма платежей по тем же фильтрам, что и таблица (шапка без перезагрузки страницы).
     */
    public function total(LtvLocationsReportPeriodRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $totalQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.summ_cents', '>', 0)
            ->where('users.partner_id', $partnerId);

        $this->applyLtvLocationsReportFilters($totalQuery, $request, $partnerId);

        $rawCents = (int) $totalQuery->sum('payments.summ_cents');
        $raw = $rawCents / 100;

        return response()->json([
            'total_formatted' => number_format($raw, 0, '', ' '),
            'total_raw'       => $raw,
        ]);
    }

    /**
     * Данные основной таблицы: агрегация по снимку объекта.
     */
    public function getLtvLocations(LtvLocationsReportPeriodRequest $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        $baseQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('locations as payment_location', function ($join) {
                $join->on('payment_location.id', '=', 'payments.location_id');
            })
            ->where('payments.summ_cents', '>', 0)
            ->where('users.partner_id', $partnerId);

        $this->applyLtvLocationsReportFilters($baseQuery, $request, $partnerId);

        $attendanceSub = $this->locationAverageAttendanceAggregator->locationAveragesSubquery(
            $partnerId,
            $this->resolveAttendanceYearMonth($request),
        );
        $baseQuery->leftJoinSub($attendanceSub, 'location_avg_att', function ($join): void {
            $join->on('location_avg_att.location_id', '=', 'payments.location_id');
        });

        $baseQuery->selectRaw("
                COALESCE(payments.location_id, 0) as location_id,
                CASE
                    WHEN payments.location_id IS NULL THEN 'Без объекта'
                    ELSE COALESCE(
                        NULLIF(TRIM(MAX(payment_location.name)), ''),
                        'Без объекта'
                    )
                END as location_name,
                GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) ORDER BY users.lastname, users.name SEPARATOR ', ') as user_names,
                MAX(location_avg_att.avg_attendance) as avg_attendance,
                SUM(payments.summ_cents) as total_price_cents,
                COUNT(payments.id) as payment_count,
                MIN(payments.operation_date) as first_payment_date,
                MAX(payments.operation_date) as last_payment_date,
                MAX(payment_location.is_enabled) as is_enabled
            ")
            ->groupBy('payments.location_id');

        return DataTables::of($baseQuery)
            ->addIndexColumn()
            ->addColumn('location_name', function ($row) {
                return $row->location_name ?: 'Без объекта';
            })
            ->addColumn('user_names', function ($row) {
                return implode(', ', $this->splitLtvLocationsUserNames($row->user_names ?? ''));
            })
            ->addColumn('user_names_items', function ($row) {
                return $this->splitLtvLocationsUserNames($row->user_names ?? '');
            })
            ->addColumn('avg_attendance', function ($row) {
                if ((int) ($row->location_id ?? 0) <= 0) {
                    return null;
                }
                if ($row->avg_attendance === null || $row->avg_attendance === '') {
                    return null;
                }

                return (int) $row->avg_attendance;
            })
            ->addColumn('total_price', function ($row) {
                return round(((int) $row->total_price_cents) / 100, 2);
            })
            ->orderColumn('avg_attendance', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderByRaw('avg_attendance IS NULL, avg_attendance '.$dir);
            })
            ->orderColumn('total_price', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('total_price_cents', $dir);
            })
            ->orderColumn('payment_count', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('payment_count', $dir);
            })
            ->filter(function ($query) use ($request): void {
                $this->applyLtvLocationsDataTableSearch($query, $request);
            })
            ->make(true);
    }

    /**
     * Детализация: платежи конкретного объекта (0 = без location_id).
     */
    public function getLocationPayments(LtvLocationsReportPeriodRequest $request, int $location)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        $payments = $this->buildLtvLocationPaymentsQuery($request, $partnerId, $location);

        if ($request->has('draw')) {
            $stats = DB::query()
                ->fromSub(clone $payments, 'ltv_location_payments')
                ->selectRaw('COUNT(*) as payments_count, COALESCE(SUM(summ_cents), 0) as sum_total_cents')
                ->first();

            return DataTables::of($payments)
                ->addColumn('payment_provider', fn ($row) => $this->resolvePaymentProvider($row))
                ->editColumn('user_name', fn ($row) => $row->user_name ?: 'Без имени')
                ->editColumn('team_title', fn ($row) => $row->team_title ?: 'Без группы')
                ->editColumn('summ', fn ($row) => round(((int) $row->summ_cents) / 100, 2))
                ->with('meta_payments_count', (int) ($stats->payments_count ?? 0))
                ->with('meta_sum_total', round(((int) ($stats->sum_total_cents ?? 0)) / 100, 2))
                ->make(true);
        }

        $paymentRows = (clone $payments)->get();

        $items = $paymentRows->map(function ($row) {
            return [
                'id'               => (int) $row->id,
                'user_name'        => $row->user_name ?: 'Без имени',
                'team_title'       => $row->team_title ?: 'Без группы',
                'summ'             => round(((int) $row->summ_cents) / 100, 2),
                'payment_month'    => $row->payment_month,
                'operation_date'   => $row->operation_date,
                'payment_provider' => $this->resolvePaymentProvider($row),
            ];
        })->all();

        return response()->json([
            'location_id' => $location,
            'payments'    => $items,
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

    public function saveColumnsSettings(ColumnsSettingsWithPageLengthSaveRequest $request)
    {
        $this->requirePartnerId();

        $payload = $request->persistPayload();
        if ($payload === []) {
            return response()->json(['success' => true]);
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => (int) Auth::id(),
                'table_key' => self::TABLE_KEY,
            ],
            $payload
        );

        return response()->json(['success' => true]);
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildLtvLocationPaymentsQuery(LtvLocationsReportPeriodRequest $request, int $partnerId, int $locationId)
    {
        $teamTitleExpr = UserTeamQuery::sqlPaymentLedgerTeamTitleExpr($partnerId);

        $payments = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->where('payments.summ_cents', '>', 0);

        if ($locationId <= 0) {
            $payments->whereNull('payments.location_id');
        } else {
            $payments->where('payments.location_id', $locationId);
        }

        $this->applyLtvLocationsReportFilters($payments, $request, $partnerId);

        return $payments
            ->selectRaw("
                payments.id,
                payments.summ_cents,
                payments.payment_month,
                payments.operation_date,
                payments.payment_number,
                payments.deal_id,
                payments.payment_id,
                payments.payment_status,
                TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) as user_name,
                {$teamTitleExpr} as team_title
            ")
            ->orderBy('payments.operation_date', 'desc');
    }

    private function resolvePaymentProvider(object $row): string
    {
        return (! empty($row->deal_id) || ! empty($row->payment_id) || ! empty($row->payment_status))
            ? 'tbank'
            : 'robokassa';
    }

    /**
     * Глобальный поиск DataTables: название объекта и ФИО учеников.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyLtvLocationsDataTableSearch($query, Request $request): void
    {
        $keyword = trim((string) $request->input('search.value', ''));
        if ($keyword === '') {
            return;
        }

        $like = '%'.addcslashes($keyword, '%_\\').'%';
        $query->where(function ($q) use ($like): void {
            $q->where('users.lastname', 'like', $like)
                ->orWhere('users.name', 'like', $like)
                ->orWhereRaw(
                    "TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) LIKE ?",
                    [$like]
                )
                ->orWhereExists(function ($sub) use ($like): void {
                    $sub->selectRaw('1')
                        ->from('locations')
                        ->whereColumn('locations.id', 'payments.location_id')
                        ->where('locations.name', 'like', $like);
                });
        });
    }

    /**
     * Фильтры как у LTV по группам + groups.own.
     *
     * @param  \Illuminate\Database\Query\Builder  $paymentsQuery
     */
    private function applyLtvLocationsReportFilters($paymentsQuery, LtvLocationsReportPeriodRequest $request, int $partnerId): void
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

        UserTeamQuery::applyPaymentLedgerTeamFilters(
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

        $this->applyOwnTeamsScope($paymentsQuery, $partnerId);

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

        if ($request->mode() === 'subscription') {
            $ym = $request->periodSubscriptionYearMonth();
            if ($ym !== null) {
                $paymentsQuery->where('payments.payment_month', 'like', $ym.'%');
            }
        } else {
            $periodRange = $request->periodDateRange();
            if ($periodRange !== null) {
                $paymentsQuery->whereDate('payments.operation_date', '>=', $periodRange['from']);
                $paymentsQuery->whereDate('payments.operation_date', '<=', $periodRange['to']);
            }
        }

        if ($request->filled('operation_date_from')) {
            $paymentsQuery->whereDate('payments.operation_date', '>=', (string) $request->query('operation_date_from'));
        }
        if ($request->filled('operation_date_to')) {
            $paymentsQuery->whereDate('payments.operation_date', '<=', (string) $request->query('operation_date_to'));
        }

        $userStatus = $this->resolveLtvLocationsUserStatusFilter($request);
        if ($userStatus === 'active') {
            $paymentsQuery->where('users.is_enabled', 1);
        } elseif ($userStatus === 'inactive') {
            $paymentsQuery->where('users.is_enabled', 0);
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
     * @param  \Illuminate\Database\Query\Builder  $paymentsQuery
     */
    private function applyOwnTeamsScope($paymentsQuery, int $partnerId): void
    {
        $allowed = app(TrainerOwnTeamsScope::class)->allowedTeamIds(Auth::user(), $partnerId);
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            $paymentsQuery->whereRaw('1 = 0');

            return;
        }

        $paymentsQuery->where(function ($q) use ($allowed, $partnerId) {
            $q->whereIn('payments.team_id', $allowed)
                ->orWhere(function ($q2) use ($allowed, $partnerId) {
                    $q2->whereNull('payments.team_id');
                    UserTeamQuery::applyStudentInAnyTeamExists($q2, $partnerId, $allowed);
                });
        });
    }

    /**
     * Месяц журнала для «Ср. посещаемость»: месяц таба current/previous.
     * Для all — фильтр «Оплаченный месяц», иначе за всё время.
     */
    private function resolveAttendanceYearMonth(LtvLocationsReportPeriodRequest $request): ?string
    {
        $period = $request->period();
        if ($period === 'current') {
            return now()->format('Y-m');
        }
        if ($period === 'previous') {
            return now()->copy()->startOfMonth()->subMonth()->format('Y-m');
        }

        if (! $request->filled('payment_month')) {
            return null;
        }

        $ym = trim((string) $request->query('payment_month'));
        if (preg_match('/^\d{4}-\d{2}$/', $ym) === 1) {
            return $ym;
        }

        return null;
    }

    private function resolveLtvLocationsUserStatusFilter(Request $request): ?string
    {
        if (! $request->has('status')) {
            return 'active';
        }

        $status = (string) $request->query('status', '');
        if ($status === '') {
            return null;
        }

        if ($status === 'active' || $status === 'inactive') {
            return $status;
        }

        return 'active';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveLtvLocationsFilterUserLabel(int $partnerId, array $filters): ?array
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
    private function resolveLtvLocationsFilterTeamLabel(int $partnerId, array $filters): ?array
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
        if ($t && ! app(TrainerOwnTeamsScope::class)->allowsTeamId(auth()->user(), $partnerId, (int) $t->id)) {
            return null;
        }

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
    private function resolveLtvLocationsFilterTrainerLabel(int $partnerId, array $filters): ?array
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

    /**
     * ФИО из GROUP_CONCAT через «, » → список для колонки type=list.
     *
     * @return list<string>
     */
    private function splitLtvLocationsUserNames(?string $concat): array
    {
        $names = trim((string) $concat);
        if ($names === '') {
            return ['Без имени'];
        }

        $items = [];
        foreach (explode(', ', $names) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[] = $part;
            }
        }

        return $items !== [] ? $items : ['Без имени'];
    }
}
