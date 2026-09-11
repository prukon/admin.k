<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\Report\PaymentsReportSelect2SearchRequest;
use App\Http\Requests\Admin\Report\TbankPaymentsColumnsSettingsSaveRequest;
use App\Http\Requests\Admin\Report\TbankPaymentsReportFilterRequest;
use App\Models\Partner;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\UserTableSetting;
use App\Services\PartnerContext;
use App\Services\Tinkoff\TinkoffPaymentFiscalReceiptResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class TbankPaymentReportController extends AdminBaseController
{
    public const TABLE_KEY = 'reports_tbank_payments';

    public const TABLE_KEY_BY_DAY = 'reports_tbank_payments_by_day';

    public const TABLE_KEY_BY_MONTH = 'reports_tbank_payments_by_month';

    public function __construct(
        PartnerContext $partnerContext,
        private readonly TinkoffPaymentFiscalReceiptResolver $fiscalReceiptResolver,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(TbankPaymentsReportFilterRequest $request)
    {
        $filters = $request->filters();
        $canFilterPartner = $this->canFilterByPartner();

        $totalQuery = TinkoffPayment::query();
        $this->applyReportFilters($totalQuery, $request);
        $totalRawCents = (int) $totalQuery->sum('amount');
        $totalPaidPrice = number_format($totalRawCents / 100, 0, '', ' ');

        $tpFilterPartner = $canFilterPartner ? $this->resolvePartnerLabel($filters) : null;

        return view('admin.report.index', [
            'activeTab' => 'tbank-payments',
            'filters' => $filters,
            'totalPaidPrice' => $totalPaidPrice,
            'tpFilterPartner' => $tpFilterPartner,
            'tpCanFilterPartner' => $canFilterPartner,
            'tpHasActiveFilters' => $this->hasActiveFilters($filters, $canFilterPartner),
            'tpView' => $request->view(),
            'tbankPaymentsPageLength' => UserTableSetting::pageLengthForUser(
                Auth::id() !== null ? (int) Auth::id() : null,
                self::TABLE_KEY
            ),
        ]);
    }

    public function total(TbankPaymentsReportFilterRequest $request)
    {
        $totalQuery = TinkoffPayment::query();
        $this->applyReportFilters($totalQuery, $request);
        $rawCents = (int) $totalQuery->sum('amount');
        $raw = $rawCents / 100;

        return response()->json([
            'total_formatted' => number_format($raw, 0, '', ' '),
            'total_raw' => $raw,
        ]);
    }

    public function data(TbankPaymentsReportFilterRequest $request)
    {
        $view = $request->view();
        if ($view === 'days' || $view === 'months') {
            return $this->summaryData($request, $view);
        }

        $query = TinkoffPayment::query()
            ->with('partner')
            ->select('tinkoff_payments.*')
            ->addSelect([
                'payout_amount_cents' => TinkoffPayout::query()
                    ->selectRaw('COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount)')
                    ->whereColumn('tinkoff_payouts.payment_id', 'tinkoff_payments.id')
                    ->where('tinkoff_payouts.status', '<>', 'REJECTED')
                    ->orderByDesc('tinkoff_payouts.id')
                    ->limit(1),
            ]);

        $this->applyReportFilters($query, $request);

        if (! $request->has('order')) {
            $query->orderByDesc('id');
        }

        $commissionRules = TinkoffCommissionRule::query()
            ->where('is_enabled', true)
            ->orderByRaw('partner_id is null, method is null')
            ->get();

        $receiptCache = [];

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                $this->applyDataTableSearch($query, $request);
            })
            ->addColumn('partner_title', function (TinkoffPayment $payment) {
                $title = (string) ($payment->partner->title ?? '');
                if ($title !== '') {
                    return $title;
                }

                return '#'.$payment->partner_id;
            })
            ->addColumn('method_label', function (TinkoffPayment $payment) {
                return TinkoffPayment::methodLabel($payment->method);
            })
            ->orderColumn('method', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('tinkoff_payments.method', $dir);
            })
            ->addColumn('amount', function (TinkoffPayment $payment) {
                return round(((int) $payment->amount) / 100, 2);
            })
            ->orderColumn('amount', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('tinkoff_payments.amount', $dir);
            })
            ->addColumn('platform_commission', function (TinkoffPayment $payment) use ($commissionRules) {
                return $this->platformCommissionRub($payment, $commissionRules);
            })
            ->addColumn('payout_amount', function (TinkoffPayment $payment) {
                $cents = $payment->getAttribute('payout_amount_cents');
                if ($cents === null || $cents === '') {
                    return null;
                }

                return round(((int) $cents) / 100, 2);
            })
            ->orderColumn('payout_amount', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderByRaw(
                    '(SELECT COALESCE(tp.net_amount, tp.amount) FROM tinkoff_payouts AS tp WHERE tp.payment_id = tinkoff_payments.id AND tp.status <> ? ORDER BY tp.id DESC LIMIT 1) '.$dir,
                    ['REJECTED']
                );
            })
            ->addColumn('show_url', function (TinkoffPayment $payment) {
                return url('/admin/tinkoff/payments/'.$payment->id);
            })
            ->addColumn('receipt_url', function (TinkoffPayment $payment) use (&$receiptCache) {
                return $this->receiptFields($payment, $receiptCache)['receipt_url'];
            })
            ->addColumn('has_receipt', function (TinkoffPayment $payment) use (&$receiptCache) {
                return $this->receiptFields($payment, $receiptCache)['has_receipt'];
            })
            ->addColumn('receipt_hint', function (TinkoffPayment $payment) use (&$receiptCache) {
                return $this->receiptFields($payment, $receiptCache)['receipt_hint'];
            })
            ->addColumn('return_receipt_url', function (TinkoffPayment $payment) use (&$receiptCache) {
                return $this->receiptFields($payment, $receiptCache)['return_receipt_url'];
            })
            ->addColumn('has_return_receipt', function (TinkoffPayment $payment) use (&$receiptCache) {
                return $this->receiptFields($payment, $receiptCache)['has_return_receipt'];
            })
            ->addColumn('return_receipt_hint', function (TinkoffPayment $payment) use (&$receiptCache) {
                return $this->receiptFields($payment, $receiptCache)['return_receipt_hint'];
            })
            ->addColumn('return_receipt_status', function (TinkoffPayment $payment) use (&$receiptCache) {
                return $this->receiptFields($payment, $receiptCache)['return_receipt_status'];
            })
            ->editColumn('created_at', function (TinkoffPayment $payment) {
                return self::formatReportDateTime($payment->created_at);
            })
            ->removeColumn('payout_amount_cents')
            ->toJson();
    }

    public function partnersSearch(PaymentsReportSelect2SearchRequest $request)
    {
        $q = (string) ($request->validated()['q'] ?? '');

        $partners = Partner::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('title', 'like', '%'.$q.'%');
            })
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title']);

        $results = $partners->map(static function (Partner $p) {
            return [
                'id' => $p->id,
                'text' => (string) ($p->title ?? ''),
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function getColumnsSettings(TbankPaymentsReportFilterRequest $request)
    {
        $settings = UserTableSetting::query()
            ->where('user_id', (int) Auth::id())
            ->where('table_key', $this->tableKeyForView($request->view()))
            ->first();

        $columns = $settings?->columns;
        if (! is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(TbankPaymentsColumnsSettingsSaveRequest $request)
    {
        $payload = $request->persistPayload();
        if ($payload === []) {
            return response()->json(['success' => true]);
        }

        $userId = (int) Auth::id();

        if (array_key_exists('columns', $payload)) {
            $row = UserTableSetting::query()->firstOrNew([
                'user_id' => $userId,
                'table_key' => $this->tableKeyForView($request->view()),
            ]);
            $row->columns = $payload['columns'];
            $row->save();
        }

        if (array_key_exists('page_length', $payload)) {
            $row = UserTableSetting::query()->firstOrNew([
                'user_id' => $userId,
                'table_key' => self::TABLE_KEY,
            ]);
            $row->page_length = $payload['page_length'];
            $row->save();
        }

        return response()->json(['success' => true]);
    }

    private function tableKeyForView(string $view): string
    {
        return match ($view) {
            'days' => self::TABLE_KEY_BY_DAY,
            'months' => self::TABLE_KEY_BY_MONTH,
            default => self::TABLE_KEY,
        };
    }

    /**
     * Сводка по дням или календарным месяцам created_at. Без детализации строк.
     *
     * @param  'days'|'months'  $grain
     */
    private function summaryData(TbankPaymentsReportFilterRequest $request, string $grain)
    {
        $periodSql = $this->summaryPeriodSql($grain);
        $payoutSql = '(SELECT COALESCE(tp.net_amount, tp.amount) FROM tinkoff_payouts AS tp'
            .' WHERE tp.payment_id = tinkoff_payments.id AND tp.status <> \'REJECTED\''
            .' ORDER BY tp.id DESC LIMIT 1)';

        $query = DB::table('tinkoff_payments')
            ->selectRaw($periodSql.' as period_key')
            ->selectRaw('COUNT(*) as payments_count')
            ->selectRaw('SUM(tinkoff_payments.amount) as amount_cents')
            ->selectRaw('SUM('.$payoutSql.') as payout_amount_cents')
            ->whereNotNull('tinkoff_payments.created_at')
            ->groupByRaw($periodSql);

        $this->applyReportFilters($query, $request);

        if (! $request->has('order')) {
            $query->orderByDesc('period_key');
        }

        $commissionRules = TinkoffCommissionRule::query()
            ->where('is_enabled', true)
            ->orderByRaw('partner_id is null, method is null')
            ->get();

        $commissionByPeriod = null;
        $loadCommissions = function () use (&$commissionByPeriod, $request, $grain, $commissionRules): void {
            if ($commissionByPeriod !== null) {
                return;
            }

            $commissionByPeriod = [];
            $paymentsQuery = TinkoffPayment::query()->select([
                'id',
                'partner_id',
                'method',
                'amount',
                'created_at',
            ]);
            $this->applyReportFilters($paymentsQuery, $request);
            $paymentsQuery->whereNotNull('created_at');

            $paymentsQuery->orderBy('id')->chunk(500, function ($payments) use (&$commissionByPeriod, $grain, $commissionRules): void {
                foreach ($payments as $payment) {
                    /** @var TinkoffPayment $payment */
                    $key = $this->periodKeyFromDate($payment->created_at, $grain);
                    if ($key === '') {
                        continue;
                    }
                    $cents = (int) round($this->platformCommissionRub($payment, $commissionRules) * 100);
                    $commissionByPeriod[$key] = ($commissionByPeriod[$key] ?? 0) + $cents;
                }
            });
        };

        return DataTables::of($query)
            ->filter(function ($query) use ($request, $grain): void {
                $this->applySummaryDataTableSearch($query, $request, $grain);
            })
            ->addColumn('period_title', function ($row) use ($grain) {
                return $this->formatPeriodTitle((string) ($row->period_key ?? ''), $grain);
            })
            ->orderColumn('period_title', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('period_key', $dir);
            })
            ->orderColumn('payments_count', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('payments_count', $dir);
            })
            ->addColumn('amount', function ($row) {
                return round(((int) ($row->amount_cents ?? 0)) / 100, 2);
            })
            ->orderColumn('amount', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('amount_cents', $dir);
            })
            ->addColumn('platform_commission', function ($row) use ($loadCommissions, &$commissionByPeriod) {
                $loadCommissions();
                $key = (string) ($row->period_key ?? '');
                $cents = (int) ($commissionByPeriod[$key] ?? 0);

                return round($cents / 100, 2);
            })
            ->addColumn('payout_amount', function ($row) {
                $cents = $row->payout_amount_cents ?? null;
                if ($cents === null || $cents === '') {
                    return null;
                }

                return round(((int) $cents) / 100, 2);
            })
            ->orderColumn('payout_amount', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('payout_amount_cents', $dir);
            })
            ->removeColumn('amount_cents')
            ->removeColumn('payout_amount_cents')
            ->toJson();
    }

    /**
     * @param  'days'|'months'  $grain
     */
    private function summaryPeriodSql(string $grain): string
    {
        if ($grain === 'days') {
            return 'DATE(tinkoff_payments.created_at)';
        }

        return 'DATE_FORMAT(tinkoff_payments.created_at, \'%Y-%m\')';
    }

    /**
     * @param  'days'|'months'  $grain
     */
    private function periodKeyFromDate(mixed $value, string $grain): string
    {
        if (! $value) {
            return '';
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return '';
        }

        return $grain === 'days' ? $date->format('Y-m-d') : $date->format('Y-m');
    }

    /**
     * @param  'days'|'months'  $grain
     */
    private function formatPeriodTitle(string $periodKey, string $grain): string
    {
        if ($periodKey === '') {
            return '';
        }

        try {
            if ($grain === 'days') {
                return Carbon::parse($periodKey)->format('d.m.Y');
            }

            $date = Carbon::createFromFormat('Y-m', $periodKey)->startOfMonth();
        } catch (\Throwable) {
            return $periodKey;
        }

        $monthNames = [
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

        $monthName = $monthNames[(int) $date->month] ?? $date->format('m');

        return $monthName.' '.$date->year;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  'days'|'months'  $grain
     */
    private function applySummaryDataTableSearch($query, Request $request, string $grain): void
    {
        $keyword = trim((string) $request->input('search.value', ''));
        if ($keyword === '') {
            return;
        }

        if ($grain === 'days') {
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $keyword, $m) === 1) {
                $query->whereRaw('DATE(tinkoff_payments.created_at) = ?', [$m[3].'-'.$m[2].'-'.$m[1]]);

                return;
            }

            $like = '%'.addcslashes($keyword, '%_\\').'%';
            $query->whereRaw('DATE(tinkoff_payments.created_at) LIKE ?', [$like]);

            return;
        }

        $monthKey = $this->parseMonthSearchKeyword($keyword);
        if ($monthKey !== null) {
            $query->whereRaw('DATE_FORMAT(tinkoff_payments.created_at, \'%Y-%m\') = ?', [$monthKey]);

            return;
        }

        $like = '%'.addcslashes($keyword, '%_\\').'%';
        $query->whereRaw('DATE_FORMAT(tinkoff_payments.created_at, \'%Y-%m\') LIKE ?', [$like]);
    }

    private function parseMonthSearchKeyword(string $keyword): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $keyword, $m) === 1) {
            return $m[1].'-'.$m[2];
        }

        $monthNames = [
            'январь' => '01',
            'февраль' => '02',
            'март' => '03',
            'апрель' => '04',
            'май' => '05',
            'июнь' => '06',
            'июль' => '07',
            'август' => '08',
            'сентябрь' => '09',
            'октябрь' => '10',
            'ноябрь' => '11',
            'декабрь' => '12',
        ];

        if (preg_match('/^([^\s]+)\s+(\d{4})$/u', mb_strtolower($keyword), $m) !== 1) {
            return null;
        }

        $month = $monthNames[$m[1]] ?? null;
        if ($month === null) {
            return null;
        }

        return $m[2].'-'.$month;
    }

    private function canFilterByPartner(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyReportFilters($query, TbankPaymentsReportFilterRequest $request): void
    {
        $filters = $request->filters();

        if ($this->isSuperAdmin()) {
            if ($filters['partner_id'] !== null && $filters['partner_id'] > 0) {
                $query->where('partner_id', $filters['partner_id']);
            }
        } else {
            $partnerId = $this->partnerId();
            if ($partnerId) {
                $query->where('partner_id', (int) $partnerId);
            }
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['method'] !== null) {
            $query->where('tinkoff_payments.method', $filters['method']);
        }

        if ($filters['created_from'] !== null) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if ($filters['created_to'] !== null) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if ($filters['without_payout']) {
            $query->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('tinkoff_payouts')
                    ->whereColumn('tinkoff_payouts.payment_id', 'tinkoff_payments.id')
                    ->where('tinkoff_payouts.status', '<>', 'REJECTED');
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyDataTableSearch($query, Request $request): void
    {
        $keyword = trim((string) $request->input('search.value', ''));
        if ($keyword === '') {
            return;
        }

        $like = '%'.addcslashes($keyword, '%_\\').'%';
        $query->where(function ($q) use ($like): void {
            $q->where('tinkoff_payments.order_id', 'like', $like)
                ->orWhere('tinkoff_payments.deal_id', 'like', $like)
                ->orWhereRaw('CAST(tinkoff_payments.id AS CHAR) LIKE ?', [$like])
                ->orWhereHas('partner', function ($p) use ($like): void {
                    $p->where('partners.title', 'like', $like);
                });
        });
    }

    private static function formatReportDateTime(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        return $value->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasActiveFilters(array $filters, bool $canFilterPartner): bool
    {
        if (! empty($filters['without_payout'])) {
            return true;
        }

        $keys = ['status', 'method', 'created_from', 'created_to'];
        if ($canFilterPartner) {
            $keys[] = 'partner_id';
        }
        foreach ($keys as $k) {
            $v = $filters[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== 'all') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{id: int, text: string}|null
     */
    private function resolvePartnerLabel(array $filters): ?array
    {
        $raw = $filters['partner_id'] ?? null;
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }
        $pid = (int) $raw;
        if ($pid <= 0) {
            return null;
        }

        $p = Partner::query()->where('id', $pid)->first(['id', 'title']);
        if (! $p) {
            return null;
        }

        return [
            'id' => $p->id,
            'text' => (string) ($p->title ?? ''),
        ];
    }

    /**
     * @param  Collection<int, TinkoffCommissionRule>  $rules
     */
    private function platformCommissionRub(TinkoffPayment $payment, Collection $rules): float
    {
        $partnerId = (int) $payment->partner_id;
        $method = $payment->method !== null && $payment->method !== '' ? (string) $payment->method : null;

        /** @var TinkoffCommissionRule|null $chosen */
        $chosen = $rules->first(function (TinkoffCommissionRule $rule) use ($partnerId, $method) {
            $partnerOk = ($rule->partner_id === null) || ((int) $rule->partner_id === $partnerId);
            $methodOk = ($rule->method === null) || ((string) $rule->method === (string) $method);

            return $partnerOk && $methodOk;
        });

        $rule = $chosen ?: new TinkoffCommissionRule([
            'platform_percent' => 0.00,
            'platform_min_fixed' => 0.00,
        ]);

        $grossCents = (int) $payment->amount;
        $percent = (float) ($rule->platform_percent ?? 0.00);
        $minFixedRub = (float) ($rule->platform_min_fixed ?? 0.00);
        $fee = (int) round($grossCents * ($percent / 100));
        $min = (int) round($minFixedRub * 100);
        $feeCents = max($fee, $min);

        return round($feeCents / 100, 2);
    }

    /**
     * @param  array<int, array{receipt_url: ?string, has_receipt: bool, receipt_hint: string, return_receipt_url: ?string, has_return_receipt: bool, return_receipt_hint: string, return_receipt_status: string}>  $cache
     * @return array{receipt_url: ?string, has_receipt: bool, receipt_hint: string, return_receipt_url: ?string, has_return_receipt: bool, return_receipt_hint: string, return_receipt_status: string}
     */
    private function receiptFields(TinkoffPayment $payment, array &$cache): array
    {
        $id = (int) $payment->id;
        if (! isset($cache[$id])) {
            $resolved = $this->fiscalReceiptResolver->resolve($payment);
            $income = $resolved['income'];
            $return = $resolved['return'];

            $cache[$id] = [
                'receipt_url' => $income['url'],
                'has_receipt' => (bool) $income['has_url'],
                'receipt_hint' => (string) $income['hint'],
                'return_receipt_url' => $return['url'],
                'has_return_receipt' => (bool) $return['has_url'],
                'return_receipt_hint' => (string) $return['hint'],
                'return_receipt_status' => (string) ($return['status'] ?? ''),
            ];
        }

        return $cache[$id];
    }
}
