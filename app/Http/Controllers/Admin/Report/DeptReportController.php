<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\Location;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use App\Services\PartnerContext;
use App\Services\TeamLocationAvailabilityService;
use App\Models\UserCustomPayment;
use App\Support\UserTeamQuery;
use App\Models\UserTableSetting;

class DeptReportController extends AdminBaseController
{
    private const TABLE_KEY = 'reports_debts';

    public function __construct(
        PartnerContext $partnerContext,
        private readonly TeamLocationAvailabilityService $teamLocationAvailability,
    ) {
        parent::__construct($partnerContext);
    }

    //Отчет Задолженности
    public function debts(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        $filters = $request->query();

        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');

        $totalMonthly = DB::table('users_prices')
            ->join('users', 'users.id', '=', 'users_prices.user_id')
            ->where('users_prices.is_paid', 0)
            ->where('users_prices.price', '>', 0)
            ->where('users_prices.new_month', '<', $currentMonth)
            ->where('users.partner_id', $partnerId);

        $this->applyDebtReportFilters($totalMonthly, $request, $partnerId);

        $today = Carbon::now()->format('Y-m-d');

        $totalPeriods = DB::table('user_custom_payment')
            ->join('users', 'users.id', '=', 'user_custom_payment.user_id')
            ->where('user_custom_payment.partner_id', $partnerId)
            ->where('user_custom_payment.is_paid', 0)
            ->where('user_custom_payment.amount', '>', 0)
            ->where('user_custom_payment.date_end', '<', $today);

        $this->applyDebtReportFiltersForPeriodPrices($totalPeriods, $request, $partnerId);

        $totalRaw = (float) $totalMonthly->sum('users_prices.price') + (float) $totalPeriods->sum('user_custom_payment.amount');
        $totalUnpaidPrice = number_format($totalRaw, 0, '', ' ');

        $paymentsFilterUser = $this->resolveDebtFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolveDebtFilterTeamLabel($partnerId, $filters);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canViewTrainers = $authUser?->can('trainers.view') ?? false;
        $paymentsFilterTrainer = $canViewTrainers
            ? $this->resolveDebtFilterTrainerLabel($partnerId, $filters)
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
            'activeTab'          => 'debt',
            'totalUnpaidPrice'   => $totalUnpaidPrice,
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
     * Сумма задолженности по тем же фильтрам, что и таблица (шапка без перезагрузки страницы).
     */
    public function debtsTotal(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');

        $totalMonthly = DB::table('users_prices')
            ->join('users', 'users.id', '=', 'users_prices.user_id')
            ->where('users_prices.is_paid', 0)
            ->where('users_prices.price', '>', 0)
            ->where('users_prices.new_month', '<', $currentMonth)
            ->where('users.partner_id', $partnerId);

        $this->applyDebtReportFilters($totalMonthly, $request, $partnerId);

        $today = Carbon::now()->format('Y-m-d');
        $totalPeriods = DB::table('user_custom_payment')
            ->join('users', 'users.id', '=', 'user_custom_payment.user_id')
            ->where('user_custom_payment.partner_id', $partnerId)
            ->where('user_custom_payment.is_paid', 0)
            ->where('user_custom_payment.amount', '>', 0)
            ->where('user_custom_payment.date_end', '<', $today);

        $this->applyDebtReportFiltersForPeriodPrices($totalPeriods, $request, $partnerId);

        $raw = (float) $totalMonthly->sum('users_prices.price') + (float) $totalPeriods->sum('user_custom_payment.amount');

        return response()->json([
            'total_formatted' => number_format((float) $raw, 0, '', ' '),
            'total_raw'       => (float) $raw,
        ]);
    }

    //Данные для отчета Задолженности
    public function getDebts(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $currentMonth = Carbon::now()->locale('ru')->isoFormat('MMMM YYYY');
        $currentMonth = $this->formatedDate($currentMonth) ?? Carbon::now()->format('Y-m-01');
        $today = Carbon::now()->format('Y-m-d');

        if ($request->ajax()) {
            $monthly = DB::table('users_prices')
                ->join('users', 'users.id', '=', 'users_prices.user_id')
                ->selectRaw("TRIM(CONCAT(COALESCE(users.lastname,''),' ',COALESCE(users.name,''))) as user_name")
                ->addSelect(
                    'users.id as user_id',
                    DB::raw('users_prices.id as row_id'),
                    DB::raw('users_prices.new_month as month'),
                    DB::raw('users_prices.price as price'),
                    DB::raw('0 as is_period')
                )
                ->where('users_prices.is_paid', 0)
                ->where('users_prices.price', '>', 0)
                ->where('users_prices.new_month', '<', $currentMonth)
                ->where('users.partner_id', $partnerId);

            $this->applyDebtReportFilters($monthly, $request, $partnerId);

            $periods = DB::table('user_custom_payment')
                ->join('users', 'users.id', '=', 'user_custom_payment.user_id')
                ->selectRaw("TRIM(CONCAT(COALESCE(users.lastname,''),' ',COALESCE(users.name,''))) as user_name")
                ->addSelect(
                    'users.id as user_id',
                    DB::raw('user_custom_payment.id as row_id'),
                    DB::raw("CONCAT(user_custom_payment.date_start, ' — ', user_custom_payment.date_end) as month"),
                    DB::raw('user_custom_payment.amount as price'),
                    DB::raw('1 as is_period')
                )
                ->where('user_custom_payment.partner_id', $partnerId)
                ->where('user_custom_payment.is_paid', 0)
                ->where('user_custom_payment.amount', '>', 0)
                ->where('user_custom_payment.date_end', '<', $today);

            $this->applyDebtReportFiltersForPeriodPrices($periods, $request, $partnerId);

            $union = $monthly->unionAll($periods);
            $base = DB::query()->fromSub($union, 'debts');

            // Стабильная пагинация: после сортировки по колонке — уникальный tie-breaker
            // (is_period + row_id), иначе при одинаковом month LIMIT/OFFSET может дублировать строки.
            $appendStableOrder = static function ($query): void {
                $query->orderBy('is_period')->orderBy('row_id');
            };

            return DataTables::of($base)
                ->addIndexColumn()
                ->editColumn('month', fn ($row) => self::formatMonthForDebtReport($row->month))
                ->addColumn('price', fn ($row) => (float) $row->price)
                ->orderColumn('user_name', function ($query, $order) use ($appendStableOrder) {
                    $dir = strtolower((string) $order) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy('user_name', $dir);
                    $appendStableOrder($query);
                })
                ->orderColumn('month', function ($query, $order) use ($appendStableOrder) {
                    $dir = strtolower((string) $order) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy('month', $dir);
                    $appendStableOrder($query);
                })
                ->orderColumn('price', function ($query, $order) use ($appendStableOrder) {
                    $dir = strtolower((string) $order) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy('price', $dir);
                    $appendStableOrder($query);
                })
                ->removeColumn('row_id', 'is_period')
                ->make(true);
        }

        abort(404);
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
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDebtReportFilters($query, Request $request, int $partnerId): void
    {
        $filterUserId = $request->query('filter_user_id');
        if ($filterUserId !== null && $filterUserId !== '' && ctype_digit((string) $filterUserId)) {
            $uid = (int) $filterUserId;
            if ($uid > 0) {
                $query->where('users.id', $uid);
            }
        } elseif ($request->filled('user_name')) {
            $needle = '%'.trim((string) $request->query('user_name')).'%';
            $query->whereRaw("CONCAT_WS(' ', users.lastname, users.name) LIKE ?", [$needle]);
        }

        $filterTeamId = $request->query('filter_team_id');
        if ($filterTeamId !== null && $filterTeamId !== '' && ctype_digit((string) $filterTeamId)) {
            $tid = (int) $filterTeamId;
            if ($tid > 0) {
                $query->where('users_prices.team_id', $tid);
            }
        } elseif ($request->filled('team_title')) {
            UserTeamQuery::applyStudentTeamTitleLikeExists(
                $query,
                $partnerId,
                '%'.trim((string) $request->query('team_title')).'%',
            );
        }

        $this->applyDebtReportTrainerFilter($query, $request, $partnerId);
        $this->applyDebtReportLocationFilter($query, $request, $partnerId);
        $this->applyDebtReportUserStatusFilter($query, $request);

        if ($request->filled('debt_month')) {
            $ym = trim((string) $request->query('debt_month'));
            if (preg_match('/^\d{4}-\d{2}$/', $ym) === 1) {
                $query->where('users_prices.new_month', 'like', $ym.'%');
            }
        }
    }

    /**
     * Фильтры отчёта задолженности для user_custom_payment.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDebtReportFiltersForPeriodPrices($query, Request $request, int $partnerId): void
    {
        $filterUserId = $request->query('filter_user_id');
        if ($filterUserId !== null && $filterUserId !== '' && ctype_digit((string) $filterUserId)) {
            $uid = (int) $filterUserId;
            if ($uid > 0) {
                $query->where('users.id', $uid);
            }
        } elseif ($request->filled('user_name')) {
            $needle = '%'.trim((string) $request->query('user_name')).'%';
            $query->whereRaw("CONCAT_WS(' ', users.lastname, users.name) LIKE ?", [$needle]);
        }

        $filterTeamId = $request->query('filter_team_id');
        if ($filterTeamId !== null && $filterTeamId !== '' && ctype_digit((string) $filterTeamId)) {
            $tid = (int) $filterTeamId;
            if ($tid > 0) {
                UserTeamQuery::applyStudentInTeamExists($query, $partnerId, $tid);
            }
        } elseif ($request->filled('team_title')) {
            UserTeamQuery::applyStudentTeamTitleLikeExists(
                $query,
                $partnerId,
                '%'.trim((string) $request->query('team_title')).'%',
            );
        }

        $this->applyDebtReportTrainerFilter($query, $request, $partnerId);
        $this->applyDebtReportLocationFilter($query, $request, $partnerId);
        $this->applyDebtReportUserStatusFilter($query, $request);

        // debt_month применяем по start/end месяцу
        if ($request->filled('debt_month')) {
            $ym = trim((string) $request->query('debt_month'));
            if (preg_match('/^\d{4}-\d{2}$/', $ym) === 1) {
                $query->where(function ($q) use ($ym) {
                    $q->where('user_custom_payment.date_start', 'like', $ym.'%')
                        ->orWhere('user_custom_payment.date_end', 'like', $ym.'%');
                });
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDebtReportUserStatusFilter($query, Request $request): void
    {
        $userStatus = $this->resolveDebtUserStatusFilter($request);
        if ($userStatus === 'active') {
            $query->where('users.is_enabled', 1);
        } elseif ($userStatus === 'inactive') {
            $query->where('users.is_enabled', 0);
        }
    }

    /**
     * active / inactive — фильтр по users.is_enabled; null — все ученики.
     * Без параметра status в запросе по умолчанию только активные.
     */
    private function resolveDebtUserStatusFilter(Request $request): ?string
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
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDebtReportTrainerFilter($query, Request $request, int $partnerId): void
    {
        $filterTrainerProfileId = $request->query('filter_trainer_profile_id');
        if ($filterTrainerProfileId === null || $filterTrainerProfileId === '' || ! ctype_digit((string) $filterTrainerProfileId)) {
            return;
        }

        $tpid = (int) $filterTrainerProfileId;
        if ($tpid <= 0) {
            return;
        }

        $trainerTeamIds = DB::table('team_trainer')
            ->where('partner_id', $partnerId)
            ->where('trainer_profile_id', $tpid)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($trainerTeamIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        UserTeamQuery::applyStudentInAnyTeamExists($query, $partnerId, $trainerTeamIds);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDebtReportLocationFilter($query, Request $request, int $partnerId): void
    {
        /** @var \App\Models\User|null $filterActor */
        $filterActor = Auth::user();
        if (! $filterActor?->can('locations.view')) {
            return;
        }

        $this->teamLocationAvailability->applyDebtUserTeamLocationFilter(
            $query,
            $partnerId,
            $request->query('filter_location_id')
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveDebtFilterUserLabel(int $partnerId, array $filters): ?array
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
    private function resolveDebtFilterTeamLabel(int $partnerId, array $filters): ?array
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
    private function resolveDebtFilterTrainerLabel(int $partnerId, array $filters): ?array
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
     * Человекочитаемый месяц для отчёта задолженностей: YYYY-MM-DD → «Январь 2026».
     * Периоды «дата — дата» и прочие строки возвращаются как есть.
     */
    private static function formatMonthForDebtReport(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, ' — ')) {
            return $raw;
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches)) {
            return $raw;
        }

        $month = (int) $matches[2];
        $year = (int) $matches[1];

        if ($month < 1 || $month > 12) {
            return $raw;
        }

        static $monthNames = [
            1 => 'Январь',
            2 => 'Февраль',
            3 => 'Март',
            4 => 'Апрель',
            5 => 'Май',
            6 => 'Июнь',
            7 => 'Июль',
            8 => 'Август',
            9 => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь',
        ];

        return $monthNames[$month].' '.$year;
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

        // !F Y — без «!» PHP подставляет текущий день и ломает короткие месяцы (февраль → март).
        try {
            $date = \DateTime::createFromFormat('!F Y', $month);
            if ($date) {
                return $date->format('Y-m-01');
            }
            return null; // Возвращаем null, если не удалось преобразовать
        } catch (\Exception $e) {
            Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }
}