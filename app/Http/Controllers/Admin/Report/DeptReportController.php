<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use App\Services\PartnerContext;
use App\Models\UserCustomPayment;

class DeptReportController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
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
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('users_prices.is_paid', 0)
            ->where('users.is_enabled', 1)
            ->where('users_prices.price', '>', 0)
            ->where('users_prices.new_month', '<', $currentMonth)
            ->where('users.partner_id', $partnerId);

        $this->applyDebtReportFilters($totalMonthly, $request);

        $today = Carbon::now()->format('Y-m-d');

        $totalPeriods = DB::table('user_custom_payment')
            ->join('users', 'users.id', '=', 'user_custom_payment.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('user_custom_payment.partner_id', $partnerId)
            ->where('user_custom_payment.is_paid', 0)
            ->where('users.is_enabled', 1)
            ->where('user_custom_payment.amount', '>', 0)
            ->where('user_custom_payment.date_end', '<', $today);

        $this->applyDebtReportFiltersForPeriodPrices($totalPeriods, $request);

        $totalRaw = (float) $totalMonthly->sum('users_prices.price') + (float) $totalPeriods->sum('user_custom_payment.amount');
        $totalUnpaidPrice = number_format($totalRaw, 0, '', ' ');

        $paymentsFilterUser = $this->resolveDebtFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolveDebtFilterTeamLabel($partnerId, $filters);

        return view('admin.report.index', [
            'activeTab'          => 'debt',
            'totalUnpaidPrice'   => $totalUnpaidPrice,
            'filters'            => $filters,
            'paymentsFilterUser' => $paymentsFilterUser,
            'paymentsFilterTeam' => $paymentsFilterTeam,
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
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('users_prices.is_paid', 0)
            ->where('users.is_enabled', 1)
            ->where('users_prices.price', '>', 0)
            ->where('users_prices.new_month', '<', $currentMonth)
            ->where('users.partner_id', $partnerId);

        $this->applyDebtReportFilters($totalMonthly, $request);

        $today = Carbon::now()->format('Y-m-d');
        $totalPeriods = DB::table('user_custom_payment')
            ->join('users', 'users.id', '=', 'user_custom_payment.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('user_custom_payment.partner_id', $partnerId)
            ->where('user_custom_payment.is_paid', 0)
            ->where('users.is_enabled', 1)
            ->where('user_custom_payment.amount', '>', 0)
            ->where('user_custom_payment.date_end', '<', $today);

        $this->applyDebtReportFiltersForPeriodPrices($totalPeriods, $request);

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
                ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
                ->selectRaw("TRIM(CONCAT(COALESCE(users.lastname,''),' ',COALESCE(users.name,''))) as user_name")
                ->addSelect(
                    'users.id as user_id',
                    DB::raw('users_prices.new_month as month'),
                    DB::raw('users_prices.price as price'),
                    DB::raw('0 as is_period')
                )
                ->where('users_prices.is_paid', 0)
                ->where('users.is_enabled', 1)
                ->where('users_prices.price', '>', 0)
                ->where('users_prices.new_month', '<', $currentMonth)
                ->where('users.partner_id', $partnerId);

            $this->applyDebtReportFilters($monthly, $request);

            $periods = DB::table('user_custom_payment')
                ->join('users', 'users.id', '=', 'user_custom_payment.user_id')
                ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
                ->selectRaw("TRIM(CONCAT(COALESCE(users.lastname,''),' ',COALESCE(users.name,''))) as user_name")
                ->addSelect(
                    'users.id as user_id',
                    DB::raw("CONCAT(user_custom_payment.date_start, ' — ', user_custom_payment.date_end) as month"),
                    DB::raw('user_custom_payment.amount as price'),
                    DB::raw('1 as is_period')
                )
                ->where('user_custom_payment.partner_id', $partnerId)
                ->where('user_custom_payment.is_paid', 0)
                ->where('users.is_enabled', 1)
                ->where('user_custom_payment.amount', '>', 0)
                ->where('user_custom_payment.date_end', '<', $today);

            $this->applyDebtReportFiltersForPeriodPrices($periods, $request);

            $union = $monthly->unionAll($periods);
            $base = DB::query()->fromSub($union, 'debts');

            return DataTables::of($base)
                ->addIndexColumn()
                ->addColumn('month', fn ($row) => $row->month)
                ->addColumn('price', fn ($row) => (float) $row->price)
                ->make(true);
        }

        abort(404);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDebtReportFilters($query, Request $request): void
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
                $query->where('users.team_id', $tid);
            }
        } elseif ($request->filled('team_title')) {
            $like = '%'.trim((string) $request->query('team_title')).'%';
            $query->where('teams.title', 'like', $like);
        }

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
    private function applyDebtReportFiltersForPeriodPrices($query, Request $request): void
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
                $query->where('users.team_id', $tid);
            }
        } elseif ($request->filled('team_title')) {
            $like = '%'.trim((string) $request->query('team_title')).'%';
            $query->where('teams.title', 'like', $like);
        }

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