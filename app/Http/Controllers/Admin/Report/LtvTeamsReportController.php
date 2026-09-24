<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\ColumnsSettingsWithPageLengthSaveRequest;
use App\Http\Requests\Admin\Report\LtvTeamsReportPeriodRequest;
use App\Models\Location;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\PartnerContext;
use App\Services\Reports\TeamAverageAttendanceAggregator;
use App\Services\TrainerOwnTeamsScope;
use App\Support\Reports\ReportFilterCatalog;
use App\Support\UserTeamQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class LtvTeamsReportController extends AdminBaseController
{
    private const TABLE_KEY = 'reports_ltv_teams';

    public function __construct(
        PartnerContext $partnerContext,
        private readonly TeamAverageAttendanceAggregator $teamAverageAttendanceAggregator,
    ) {
        parent::__construct($partnerContext);
    }

    /**
     * Страница отчёта «Платежи по группам» (вкладка на admin.report.index).
     */
    public function index(LtvTeamsReportPeriodRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $filters = $request->query();

        $totalQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.summ_cents', '>', 0)
            ->where('users.partner_id', $partnerId);
        $this->applyLtvTeamsReportFilters($totalQuery, $request, $partnerId, null);

        $totalRawCents = (int) $totalQuery->sum('payments.summ_cents');
        $totalPaidPrice = number_format($totalRawCents / 100, 0, '', ' ');

        $paymentsFilterUser = $this->resolveLtvTeamsFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolveLtvTeamsFilterTeamLabel($partnerId, $filters);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canViewTrainers = $authUser?->can('trainers.view') ?? false;
        $paymentsFilterTrainer = $canViewTrainers
            ? $this->resolveLtvTeamsFilterTrainerLabel($partnerId, $filters)
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
            'activeTab'          => 'ltv-teams',
            'totalPaidPrice'     => $totalPaidPrice,
            'filters'            => $filters,
            'paymentsFilterUser' => $paymentsFilterUser,
            'paymentsFilterTeam' => $paymentsFilterTeam,
            'paymentsFilterTrainer' => $paymentsFilterTrainer,
            'canViewTrainers'    => $canViewTrainers,
            'canViewLocations'   => $canViewLocations,
            'activeLocations'    => $activeLocations,
            'filterTeams'        => ReportFilterCatalog::activeTeams($partnerId, $authUser),
            'filterTrainers'     => ReportFilterCatalog::activeTrainers($partnerId, $canViewTrainers),
            'ltvTeamsPageLength' => UserTableSetting::pageLengthForUser(
                Auth::id() !== null ? (int) Auth::id() : null,
                self::TABLE_KEY
            ),
            'ltvTeamsPeriod' => $request->period(),
            'ltvTeamsPeriodLabels' => $request->periodLabels(),
            'ltvTeamsMode' => $request->mode(),
        ]);
    }

    /**
     * Сумма платежей по тем же фильтрам, что и таблица (шапка без перезагрузки страницы).
     */
    public function total(LtvTeamsReportPeriodRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $totalQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.summ_cents', '>', 0)
            ->where('users.partner_id', $partnerId);

        $this->applyLtvTeamsReportFilters($totalQuery, $request, $partnerId, null);

        $rawCents = (int) $totalQuery->sum('payments.summ_cents');
        $raw = $rawCents / 100;

        return response()->json([
            'total_formatted' => number_format($raw, 0, '', ' '),
            'total_raw'       => $raw,
        ]);
    }

    /**
     * Данные основной таблицы: агрегация по оплаченной группе.
     */
    public function getLtvTeams(LtvTeamsReportPeriodRequest $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        $baseQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams as payment_paid_team', function ($join) {
                $join->on('payment_paid_team.id', '=', 'payments.team_id')
                    ->whereNull('payment_paid_team.deleted_at');
            })
            ->where('payments.summ_cents', '>', 0)
            ->where('users.partner_id', $partnerId);

        $this->applyLtvTeamsReportFilters($baseQuery, $request, $partnerId, null);

        $attendanceSub = $this->teamAverageAttendanceAggregator->teamAveragesSubquery(
            $partnerId,
            $this->resolveAttendanceYearMonth($request),
        );
        $baseQuery->leftJoinSub($attendanceSub, 'team_avg_att', function ($join): void {
            $join->on('team_avg_att.team_id', '=', 'payments.team_id');
        });

        $baseQuery->selectRaw("
                COALESCE(payments.team_id, 0) as team_id,
                CASE
                    WHEN payments.team_id IS NULL THEN 'Без группы'
                    ELSE COALESCE(
                        NULLIF(TRIM(MAX(payment_paid_team.title)), ''),
                        NULLIF(TRIM(MAX(payments.team_title)), ''),
                        'Без группы'
                    )
                END as team_title,
                GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) ORDER BY users.lastname, users.name SEPARATOR ', ') as user_names,
                GROUP_CONCAT(DISTINCT CONCAT(users.id, ':::', TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,'')))) ORDER BY users.lastname, users.name SEPARATOR '|||') as user_name_pairs,
                MAX(team_avg_att.avg_attendance) as avg_attendance,
                SUM(payments.summ_cents) as total_price_cents,
                COUNT(payments.id) as payment_count,
                MIN(payments.operation_date) as first_payment_date,
                MAX(payments.operation_date) as last_payment_date,
                MAX(payment_paid_team.is_enabled) as is_enabled
            ")
            ->groupBy('payments.team_id');

        return DataTables::of($baseQuery)
            ->addIndexColumn()
            ->addColumn('team_title', function ($row) {
                return $row->team_title ?: 'Без группы';
            })
            ->addColumn('user_names', function ($row) {
                return implode(', ', $this->splitLtvTeamsUserNames($row->user_names ?? ''));
            })
            ->addColumn('user_names_items', function ($row) {
                return $this->splitLtvTeamsUserNames($row->user_names ?? '');
            })
            ->addColumn('user_name_cards', function ($row) {
                return $this->ltvUserNameCards($row->user_name_pairs ?? '');
            })
            ->addColumn('avg_attendance', function ($row) {
                if ((int) ($row->team_id ?? 0) <= 0) {
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
            ->filter(function ($query) use ($request, $partnerId): void {
                $this->applyLtvTeamsDataTableSearch($query, $request, $partnerId);
            })
            ->make(true);
    }

    /**
     * Детализация: платежи конкретной оплаченной группы (0 = без team_id).
     */
    public function getTeamPayments(LtvTeamsReportPeriodRequest $request, int $team)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();
        $this->assertTeamDetailAllowed($partnerId, $team);

        $payments = $this->buildLtvTeamPaymentsQuery($request, $partnerId, $team);

        if ($request->has('draw')) {
            $stats = DB::query()
                ->fromSub(clone $payments, 'ltv_team_payments')
                ->selectRaw('COUNT(*) as payments_count, COALESCE(SUM(summ_cents), 0) as sum_total_cents')
                ->first();

            return DataTables::of($payments)
                ->addColumn('payment_provider', fn ($row) => $this->resolvePaymentProvider($row))
                ->editColumn('user_name', fn ($row) => $row->user_name ?: 'Без имени')
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
            'team_id'  => $team,
            'payments' => $items,
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
    private function buildLtvTeamPaymentsQuery(LtvTeamsReportPeriodRequest $request, int $partnerId, int $teamId)
    {
        $teamTitleExpr = UserTeamQuery::sqlPaymentLedgerTeamTitleExpr($partnerId);

        $payments = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->where('payments.summ_cents', '>', 0);

        if ($teamId <= 0) {
            $payments->whereNull('payments.team_id');
        } else {
            $payments->where('payments.team_id', $teamId);
        }

        $this->applyLtvTeamsReportFilters($payments, $request, $partnerId, $teamId);

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
                users.id as user_id,
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
     * Глобальный поиск DataTables: название группы и ФИО учеников.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyLtvTeamsDataTableSearch($query, Request $request, int $partnerId): void
    {
        $keyword = trim((string) $request->input('search.value', ''));
        if ($keyword === '') {
            return;
        }

        $like = '%'.addcslashes($keyword, '%_\\').'%';
        $query->where(function ($q) use ($like, $partnerId): void {
            $q->where('users.lastname', 'like', $like)
                ->orWhere('users.name', 'like', $like)
                ->orWhereRaw(
                    "TRIM(CONCAT(COALESCE(users.lastname,''), ' ', COALESCE(users.name,''))) LIKE ?",
                    [$like]
                )
                ->orWhere('payments.team_title', 'like', $like)
                ->orWhereExists(function ($sub) use ($like): void {
                    $sub->selectRaw('1')
                        ->from('teams')
                        ->whereColumn('teams.id', 'payments.team_id')
                        ->where('teams.title', 'like', $like)
                        ->whereNull('teams.deleted_at');
                })
                ->orWhereExists(function ($sub) use ($like, $partnerId): void {
                    $sub->selectRaw('1')
                        ->from('team_user')
                        ->join('teams', 'teams.id', '=', 'team_user.team_id')
                        ->whereColumn('team_user.user_id', 'users.id')
                        ->where('team_user.partner_id', $partnerId)
                        ->where('teams.partner_id', $partnerId)
                        ->whereNull('teams.deleted_at')
                        ->where('teams.title', 'like', $like);
                });
        });
    }

    /**
     * Фильтры как у LTV по ученикам + groups.own.
     * $forTeamDetail: null — сводка; 0 — «Без группы»; >0 — конкретная группа.
     *
     * @param  \Illuminate\Database\Query\Builder  $paymentsQuery
     */
    private function applyLtvTeamsReportFilters($paymentsQuery, LtvTeamsReportPeriodRequest $request, int $partnerId, ?int $forTeamDetail): void
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

        UserTeamQuery::applyScopedPaymentLedgerTeamFilter(
            $paymentsQuery,
            $partnerId,
            $request->query('filter_team_id'),
            $request->filled('team_title') ? (string) $request->query('team_title') : null,
        );

        $trainerRaw = $request->query('filter_trainer_profile_id');
        UserTeamQuery::applyReportTrainerTeamFilter(
            $paymentsQuery,
            $partnerId,
            is_array($trainerRaw) ? UserTeamQuery::positiveIntIds($trainerRaw) : $trainerRaw,
        );

        $this->applyOwnTeamsScope($paymentsQuery, $partnerId, $forTeamDetail);

        /** @var \App\Models\User|null $filterActor */
        $filterActor = Auth::user();
        if ($filterActor?->can('locations.view')) {
            UserTeamQuery::applyPaymentLocationIdsFilter($paymentsQuery, $request->query('filter_location_id'));
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

        $userStatus = $this->resolveLtvTeamsUserStatusFilter($request);
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
    private function applyOwnTeamsScope($paymentsQuery, int $partnerId, ?int $forTeamDetail): void
    {
        $allowed = app(TrainerOwnTeamsScope::class)->allowedTeamIds(Auth::user(), $partnerId);
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            $paymentsQuery->whereRaw('1 = 0');

            return;
        }

        if ($forTeamDetail !== null && $forTeamDetail > 0) {
            if (! in_array($forTeamDetail, $allowed, true)) {
                $paymentsQuery->whereRaw('1 = 0');
            }

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

    private function assertTeamDetailAllowed(int $partnerId, int $teamId): void
    {
        if ($teamId <= 0) {
            return;
        }

        $allowed = app(TrainerOwnTeamsScope::class)->allowedTeamIds(Auth::user(), $partnerId);
        if ($allowed === null) {
            return;
        }

        if (! in_array($teamId, $allowed, true)) {
            abort(403);
        }
    }

    /**
     * Месяц журнала для «Ср. посещаемость»: месяц таба current/previous.
     * Для all — фильтр «Оплаченный месяц», иначе за всё время.
     */
    private function resolveAttendanceYearMonth(LtvTeamsReportPeriodRequest $request): ?string
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

    private function resolveLtvTeamsUserStatusFilter(Request $request): ?string
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
    private function resolveLtvTeamsFilterUserLabel(int $partnerId, array $filters): ?array
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
    private function resolveLtvTeamsFilterTeamLabel(int $partnerId, array $filters): ?array
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
    private function resolveLtvTeamsFilterTrainerLabel(int $partnerId, array $filters): ?array
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
    private function splitLtvTeamsUserNames(?string $concat): array
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

    /**
     * @return list<array{id: int, name: string}>
     */
    private function ltvUserNameCards(?string $pairs): array
    {
        $raw = (string) $pairs;
        if ($raw === '') {
            return [];
        }

        $cards = [];
        foreach (explode('|||', $raw) as $pair) {
            $parts = explode(':::', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $id = (int) $parts[0];
            $name = trim($parts[1]);
            if ($id < 1) {
                continue;
            }
            $cards[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : 'Без имени',
            ];
        }

        return $cards;
    }
}
