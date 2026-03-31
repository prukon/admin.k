<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\Team;
use App\Models\User;
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
    public function ltv(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        $filters = $request->query();

        $totalQuery = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('payments.summ', '>', 0)
            ->where('users.partner_id', $partnerId);
        $this->applyLtvReportFilters($totalQuery, $request, false);

        $totalRaw = $totalQuery->sum('payments.summ');
        $totalPaidPrice = number_format((float) $totalRaw, 0, '', ' ');

        $paymentsFilterUser = $this->resolveLtvFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolveLtvFilterTeamLabel($partnerId, $filters);

        return view('admin.report.index', [
            'activeTab'          => 'ltv',
            'totalPaidPrice'     => $totalPaidPrice,
            'filters'            => $filters,
            'paymentsFilterUser' => $paymentsFilterUser,
            'paymentsFilterTeam' => $paymentsFilterTeam,
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
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->where('payments.summ', '>', 0)
            ->where('users.partner_id', $partnerId);

        $this->applyLtvReportFilters($totalQuery, $request, false);

        $raw = $totalQuery->sum('payments.summ');

        return response()->json([
            'total_formatted' => number_format((float) $raw, 0, '', ' '),
            'total_raw'       => (float) $raw,
        ]);
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
            ->where('users.partner_id', $partnerId);

        $this->applyLtvReportFilters($baseQuery, $request, false);

        $baseQuery->selectRaw("
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
            ->where('payments.summ', '>', 0);

        $this->applyLtvReportFilters($payments, $request, true);

        $paymentRows = $payments->selectRaw("
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

        $items = $paymentRows->map(function ($row) {
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

    /**
     * Фильтры отчёта LTV (как у «Платежи по месяцам»). Для детализации по ученику — без фильтра по ученику/группе.
     *
     * @param  \Illuminate\Database\Query\Builder  $paymentsQuery
     */
    private function applyLtvReportFilters($paymentsQuery, Request $request, bool $forSingleUserDetail): void
    {
        if (! $forSingleUserDetail) {
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
    private function resolveLtvFilterUserLabel(int $partnerId, array $filters): ?array
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
    private function resolveLtvFilterTeamLabel(int $partnerId, array $filters): ?array
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