<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\FiscalReceipt;
use App\Models\UserTableSetting;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\Team;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Yajra\DataTables\DataTables;
use App\Services\PartnerContext;
use App\Http\Requests\Admin\Report\PaymentsReportSelect2SearchRequest;


class PaymentReportController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    //Отчет Платежи
    public function payments(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        Log::debug('[payments] Partner ID', ['partnerId' => $partnerId]);

        $aggregates = $this->computePaymentsReportToolbarAggregates($partnerId, $request);
        Log::debug('[payments] Toolbar aggregates (same filters as table)', ['aggregates' => $aggregates]);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canToolbarNetToPartner = $authUser?->can('reports.payments.totals.net_to_partner.view') ?? false;
        $canToolbarPayoutAmount = $authUser?->can('reports.payments.totals.payout_amount.view') ?? false;
        $canToolbarPlatformCommission = $authUser?->can('reports.payments.totals.platform_commission.view') ?? false;

        $paymentsToolbar = $this->formatPaymentsToolbarPayload($aggregates, $canToolbarNetToPartner, $canToolbarPayoutAmount, $canToolbarPlatformCommission);
        $totalPaidPrice = $paymentsToolbar['sum_payments_formatted'];

        $filters = $request->query();
        $paymentsFilterUser = $this->resolvePaymentsFilterUserLabel($partnerId, $filters);
        $paymentsFilterTeam = $this->resolvePaymentsFilterTeamLabel($partnerId, $filters);

        // 7) представление
        return view(
            'admin.report.index',
            [
                'activeTab' => 'payment',
                'totalPaidPrice' => $totalPaidPrice,
                'paymentsToolbar' => $paymentsToolbar,
                'canPaymentsToolbarNetToPartner' => $canToolbarNetToPartner,
                'canPaymentsToolbarPayoutAmount' => $canToolbarPayoutAmount,
                'canPaymentsToolbarPlatformCommission' => $canToolbarPlatformCommission,
                'filters' => $filters,
                'paymentsFilterUser' => $paymentsFilterUser,
                'paymentsFilterTeam' => $paymentsFilterTeam,
            ]
        );
    }

    /**
     * Сумма платежей по тем же фильтрам, что и таблица (для шапки после «Применить» без перезагрузки страницы).
     */
    public function paymentsTotal(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        $aggregates = $this->computePaymentsReportToolbarAggregates($partnerId, $request);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        $payload = [
            'sum_payments_formatted' => number_format(round($aggregates['sum_payments']), 0, '', ' '),
            'sum_payments_raw' => (float) round($aggregates['sum_payments'], 2),
        ];

        if ($user?->can('reports.payments.totals.net_to_partner.view')) {
            $payload['net_to_partner_formatted'] = number_format(round($aggregates['net_to_partner']), 0, '', ' ');
            $payload['net_to_partner_raw'] = (float) round($aggregates['net_to_partner'], 2);
        }
        if ($user?->can('reports.payments.totals.payout_amount.view')) {
            $payload['payout_amount_formatted'] = number_format(round($aggregates['payout_amount']), 0, '', ' ');
            $payload['payout_amount_raw'] = (float) round($aggregates['payout_amount'], 2);
        }
        if ($user?->can('reports.payments.totals.platform_commission.view')) {
            $payload['platform_commission_formatted'] = number_format(round($aggregates['platform_commission']), 0, '', ' ');
            $payload['platform_commission_raw'] = (float) round($aggregates['platform_commission'], 2);
        }

        // Совместимость: раньше total_* = сумма платежей
        $payload['total_formatted'] = $payload['sum_payments_formatted'];
        $payload['total_raw'] = $payload['sum_payments_raw'];

        return response()->json($payload);
    }

    /**
     * Select2: поиск учеников текущего партнёра (имя и фамилия, поиск по обоим полям).
     */
    public function usersSearch(PaymentsReportSelect2SearchRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $q = (string) ($request->validated()['q'] ?? '');

        $users = User::query()
            ->where('users.partner_id', $partnerId)
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->when($q !== '', function ($qq) use ($q) {
                $needle = '%'.$q.'%';
                $qq->where(function ($w) use ($needle) {
                    $w->where('users.name', 'like', $needle)
                        ->orWhere('users.lastname', 'like', $needle);
                });
            })
            ->orderBy('users.lastname')
            ->orderBy('users.name')
            ->limit(50)
            ->get([
                'users.id',
                'users.name',
                'users.lastname',
                'users.team_id',
                'teams.title as team_title',
            ]);

        $results = $users->map(function ($u) {
            $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));

            return [
                'id' => $u->id,
                'text' => $text !== '' ? $text : '—',
                'name' => $u->name,
                'lastname' => $u->lastname,
                'team_id' => $u->team_id,
                'team_title' => $u->team_title,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Select2: поиск групп (команд) текущего партнёра по названию.
     */
    public function teamsSearch(PaymentsReportSelect2SearchRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $q = (string) ($request->validated()['q'] ?? '');

        $teams = Team::query()
            ->where('partner_id', $partnerId)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('title', 'like', '%'.$q.'%');
            })
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title']);

        $results = $teams->map(static function (Team $t) {
            return [
                'id' => $t->id,
                'text' => (string) ($t->title ?? ''),
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolvePaymentsFilterUserLabel(int $partnerId, array $filters): ?array
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
            'id' => $u->id,
            'text' => $text !== '' ? $text : '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolvePaymentsFilterTeamLabel(int $partnerId, array $filters): ?array
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
            'id' => $t->id,
            'text' => (string) ($t->title ?? ''),
        ];
    }

    /**
     * Базовый запрос отчёта «Платежи» (те же join'ы и поля, что и в getPayments).
     * Нужен для DataTables, суммы в шапке и любых агрегатов по тем же правилам фильтрации.
     */
    private function basePaymentsReportQuery(int $partnerId)
    {
        // В интерфейсе "Чек" показываются два независимых чека:
        // - income (оплата) => receipt_url
        // - income_return (возврат) => return_receipt_url
        $latestIncomeReceiptSub = FiscalReceipt::query()
            ->select('payment_id', DB::raw('MAX(id) as latest_id'))
            ->whereNotNull('payment_id')
            ->where('type', FiscalReceipt::TYPE_INCOME)
            ->groupBy('payment_id');

        $latestReturnReceiptSub = FiscalReceipt::query()
            ->select('payment_id', DB::raw('MAX(id) as latest_id'))
            ->whereNotNull('payment_id')
            ->where('type', FiscalReceipt::TYPE_INCOME_RETURN)
            ->groupBy('payment_id');

        return Payment::query()
            ->with(['user.team'])
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->leftJoinSub($latestIncomeReceiptSub, 'latest_income_fiscal_receipts', function ($join) {
                $join->on('latest_income_fiscal_receipts.payment_id', '=', 'payments.id');
            })
            ->leftJoin('fiscal_receipts as fiscal_income_receipt', 'fiscal_income_receipt.id', '=', 'latest_income_fiscal_receipts.latest_id')
            ->leftJoinSub($latestReturnReceiptSub, 'latest_return_fiscal_receipts', function ($join) {
                $join->on('latest_return_fiscal_receipts.payment_id', '=', 'payments.id');
            })
            ->leftJoin('fiscal_receipts as fiscal_return_receipt', 'fiscal_return_receipt.id', '=', 'latest_return_fiscal_receipts.latest_id')
            ->leftJoin('payment_intents as pi_tbank', function ($join) {
                $join->on('payments.partner_id', '=', 'pi_tbank.partner_id')
                    ->where('pi_tbank.provider', '=', 'tbank')
                    ->where(function ($q) {
                        $q->whereNotNull('payments.deal_id')
                            ->orWhereNotNull('payments.payment_id')
                            ->orWhereNotNull('payments.payment_status');
                    })
                    ->whereRaw(
                        'pi_tbank.provider_inv_id = CAST(NULLIF(NULLIF(TRIM(payments.payment_number), ""), "0") AS UNSIGNED)'
                    );
            })
            ->where('users.partner_id', $partnerId)
            ->select(
                'payments.*',
                'fiscal_income_receipt.receipt_url as fiscal_income_receipt_url',
                'fiscal_return_receipt.receipt_url as fiscal_return_receipt_url',
                'fiscal_return_receipt.status as fiscal_return_receipt_status',
                'pi_tbank.payment_method_webhook as intent_payment_method_webhook',
                'pi_tbank.payment_method as intent_payment_method_init'
            )
            ->addSelect([
                'latest_refund_status' => Refund::query()
                    ->select('refunds.status')
                    ->whereColumn('refunds.payment_id', 'payments.id')
                    ->orderByDesc('refunds.id')
                    ->limit(1),
            ]);
    }

    /**
     * Последний по id возврат в pending/succeeded: расчётные комиссии и «к выплате» не показываем.
     */
    private function paymentRowHasBlockingRefund(Payment $row): bool
    {
        $status = null;
        $attrs = $row->getAttributes();
        if (array_key_exists('latest_refund_status', $attrs)) {
            $status = $attrs['latest_refund_status'];
        } else {
            $status = Refund::query()
                ->where('payment_id', $row->id)
                ->orderByDesc('id')
                ->value('status');
        }

        if ($status === null || $status === '') {
            return false;
        }

        return in_array((string) $status, ['pending', 'succeeded'], true);
    }

    /**
     * Успешная выплата по deal_id (как для payout_amount в отчёте).
     */
    private function paymentRowHasCompletedTbankPayout(Payment $row, int $partnerId): bool
    {
        if (empty($row->deal_id)) {
            return false;
        }

        return TinkoffPayout::query()
            ->where('partner_id', (int) $partnerId)
            ->where('deal_id', $row->deal_id)
            ->where('status', 'COMPLETED')
            ->exists();
    }

    /**
     * Платёж относится к T‑Bank (те же признаки, что и в addColumn payment_provider).
     */
    private function paymentRowIsTbankPayment(Payment $row): bool
    {
        return ! empty($row->deal_id) || ! empty($row->payment_id) || ! empty($row->payment_status);
    }

    /**
     * SQL: последний по id возврат в pending/succeeded (как paymentRowHasBlockingRefund).
     */
    private function sqlPaymentsReportHasBlockingRefund(): string
    {
        return 'EXISTS (
            SELECT 1 FROM refunds rf
            WHERE rf.payment_id = payments.id
              AND rf.status IN (\'pending\', \'succeeded\')
              AND rf.id = (SELECT MAX(rf2.id) FROM refunds rf2 WHERE rf2.payment_id = payments.id)
        )';
    }

    /**
     * SQL: строка отчёта — T‑Bank.
     */
    private function sqlPaymentsReportIsTbankRow(): string
    {
        return '(
            (payments.deal_id IS NOT NULL AND TRIM(payments.deal_id) <> \'\')
            OR (payments.payment_id IS NOT NULL AND TRIM(CAST(payments.payment_id AS CHAR)) <> \'\')
            OR (payments.payment_status IS NOT NULL AND TRIM(CAST(payments.payment_status AS CHAR)) <> \'\')
        )';
    }

    /**
     * SQL: комиссия за оплату (эквайринг), копейки; NULL если строка не T‑Bank / блокирующий возврат.
     */
    private function sqlPaymentsReportBankAcceptFeeCentsNullable(int $partnerId): string
    {
        $pid = (int) $partnerId;
        $isTbank = $this->sqlPaymentsReportIsTbankRow();
        $blocking = $this->sqlPaymentsReportHasBlockingRefund();

        return "(
            CASE
                WHEN {$isTbank} AND NOT ({$blocking}) THEN (
                    SELECT GREATEST(
                        ROUND(ROUND(payments.summ * 100) * COALESCE(r.acquiring_percent, 2.49) / 100),
                        ROUND(COALESCE(r.acquiring_min_fixed, 3.49) * 100)
                    )
                    FROM tinkoff_commission_rules r
                    WHERE r.is_enabled = 1
                      AND (r.partner_id IS NULL OR r.partner_id = {$pid})
                      AND (
                        r.method IS NULL OR r.method = (
                            SELECT tp.method FROM tinkoff_payments tp
                            WHERE tp.deal_id = payments.deal_id AND tp.partner_id = payments.partner_id
                            ORDER BY tp.id DESC LIMIT 1
                        )
                      )
                    ORDER BY (r.partner_id IS NOT NULL) DESC, (r.method IS NOT NULL) DESC, r.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END
        )";
    }

    /**
     * SQL: комиссия за выплату, копейки; NULL если нет завершённой выплаты / не T‑Bank / блокирующий возврат.
     */
    private function sqlPaymentsReportBankPayoutFeeCentsNullable(int $partnerId): string
    {
        $pid = (int) $partnerId;
        $isTbank = $this->sqlPaymentsReportIsTbankRow();
        $blocking = $this->sqlPaymentsReportHasBlockingRefund();
        $hasPayout = "EXISTS (
            SELECT 1 FROM tinkoff_payouts tpayout
            WHERE tpayout.partner_id = {$pid}
              AND tpayout.deal_id = payments.deal_id
              AND tpayout.status = 'COMPLETED'
        )";

        return "(
            CASE
                WHEN {$isTbank} AND NOT ({$blocking}) AND ({$hasPayout}) THEN (
                    SELECT GREATEST(
                        ROUND(ROUND(payments.summ * 100) * COALESCE(r.payout_percent, 0.10) / 100),
                        ROUND(COALESCE(r.payout_min_fixed, 0.00) * 100)
                    )
                    FROM tinkoff_commission_rules r
                    WHERE r.is_enabled = 1
                      AND (r.partner_id IS NULL OR r.partner_id = {$pid})
                      AND (
                        r.method IS NULL OR r.method = (
                            SELECT tp.method FROM tinkoff_payments tp
                            WHERE tp.deal_id = payments.deal_id AND tp.partner_id = payments.partner_id
                            ORDER BY tp.id DESC LIMIT 1
                        )
                      )
                    ORDER BY (r.partner_id IS NOT NULL) DESC, (r.method IS NOT NULL) DESC, r.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END
        )";
    }

    //Данные для отчета Платежи
    public function getPayments(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();

        $hasOrder = is_array($request->input('order')) && count($request->input('order')) > 0;

        $paymentsQuery = $this->basePaymentsReportQuery($partnerId);

        $this->applyPaymentsReportFilters($paymentsQuery, $request, $partnerId);

        // Дефолтная сортировка, если фронт не передал order (например, после кастомизаций таблицы)
        if (! $hasOrder) {
            $paymentsQuery->orderBy('payments.operation_date', 'desc');
        }

        // Общие правила комиссий (не зависят от конкретного платежа)
        $commissionRules = TinkoffCommissionRule::query()
            ->where('is_enabled', true)
            ->orderByRaw('partner_id is null, method is null')
            ->get();

        $calcFeeCents = static function (int $amountCents, float $percent, float $minFixedRub): int {
            $fee = (int) round($amountCents * ($percent / 100));
            $min = (int) round($minFixedRub * 100);
            return max($fee, $min);
        };

        $pickCommissionRule = static function (int $pid, ?string $method) use ($commissionRules): TinkoffCommissionRule {
            /** @var TinkoffCommissionRule|null $chosen */
            $chosen = $commissionRules->first(function (TinkoffCommissionRule $r) use ($pid, $method) {
                $partnerOk = ($r->partner_id === null) || ((int) $r->partner_id === $pid);
                $methodOk  = ($r->method === null) || ((string) $r->method === (string) $method);
                return $partnerOk && $methodOk;
            });

            return $chosen ?: new TinkoffCommissionRule([
                'acquiring_percent'    => 2.49,
                'acquiring_min_fixed'  => 3.49,
                'payout_percent'       => 0.10,
                'payout_min_fixed'     => 0.00,
                'platform_percent'     => 0.00,
                'platform_min_fixed'   => 0.00,
            ]);
        };

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canViewTbankHistory = $authUser?->can('viewing.all.logs') ?? false;
        $canAdditional = $authUser?->can('reports.additional.value.view') ?? false;

        return DataTables::of($paymentsQuery)
            ->addIndexColumn()
            ->addColumn('user_name', function (Payment $row) {
                $user = $row->user;
                if ($user) {
                    $full = trim(($user->lastname ?? '') . ' ' . ($user->name ?? ''));
                    if ($full !== '') {
                        return $full;
                    }
                }

                if (! empty($row->user_name)) {
                    return (string) $row->user_name;
                }

                return 'Без пользователя';
            })
            ->addColumn('user_id', function (Payment $row) {
                return $row->user ? $row->user->id : null;
            })
            ->addColumn('team_title', function (Payment $row) {
                return $row->user && $row->user->team
                    ? $row->user->team->title
                    : 'Без команды';
            })
            ->addColumn('summ', function (Payment $row) {
                return (float) $row->summ;
            })
            ->addColumn('operation_date', function (Payment $row) {
                return $row->operation_date;
            })
            ->addColumn('payment_provider', function (Payment $row) {
                return (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status))
                    ? 'tbank'
                    : 'robokassa';
            })
            ->addColumn('payment_method_label', function (Payment $row) {
                $code = (string) ($row->intent_payment_method_webhook ?? $row->intent_payment_method_init ?? '');
                if ($code === '') {
                    return '';
                }

                return match ($code) {
                    'card' => 'Карта',
                    'sbp_qr' => 'QR (СБП)',
                    'tpay' => 'T‑Pay',
                    default => $code,
                };
            })
            ->addColumn('receipt_url', function (Payment $row) {
                $receiptUrl = trim((string) ($row->fiscal_income_receipt_url ?? ''));
                if ($receiptUrl === '' || !str_starts_with($receiptUrl, 'https://receipts.ru/')) {
                    return null;
                }

                return $receiptUrl;
            })
            ->addColumn('has_receipt', function (Payment $row) {
                $receiptUrl = trim((string) ($row->fiscal_income_receipt_url ?? ''));
                return $receiptUrl !== '' && str_starts_with($receiptUrl, 'https://receipts.ru/');
            })
            ->addColumn('return_receipt_url', function (Payment $row) {
                $receiptUrl = trim((string) ($row->fiscal_return_receipt_url ?? ''));
                if ($receiptUrl === '' || !str_starts_with($receiptUrl, 'https://receipts.ru/')) {
                    return null;
                }

                return $receiptUrl;
            })
            ->addColumn('has_return_receipt', function (Payment $row) {
                $receiptUrl = trim((string) ($row->fiscal_return_receipt_url ?? ''));
                return $receiptUrl !== '' && str_starts_with($receiptUrl, 'https://receipts.ru/');
            })
            ->addColumn('return_receipt_status', function (Payment $row) {
                $status = trim((string) ($row->fiscal_return_receipt_status ?? ''));
                return $status !== '' ? $status : '';
            })
            ->orderColumn('summ', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('payments.summ', $dir);
            })
            ->orderColumn('operation_date', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('payments.operation_date', $dir);
            })
            ->orderColumn('team_title', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';

                // "Без команды" (NULL/empty) в конце при ASC и при DESC (стабильно)
                $query->orderByRaw(
                    "CASE WHEN teams.title IS NULL OR teams.title = '' THEN 1 ELSE 0 END asc"
                );
                $query->orderBy('teams.title', $dir);
            })
            ->orderColumn('user_name', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';

                // Приоритет сортировки: Фамилия+Имя (users) -> затем payments.user_name
                // Пустые значения в конец (стабильно)
                $expr = "NULLIF(TRIM(CONCAT_WS(' ', users.lastname, users.name)), '')";
                $expr2 = "NULLIF(TRIM(payments.user_name), '')";

                $query->orderByRaw("CASE WHEN COALESCE($expr, $expr2) IS NULL THEN 1 ELSE 0 END asc");
                $query->orderByRaw("COALESCE($expr, $expr2) $dir");
            })
            ->orderColumn('bank_commission_acquiring', function ($query, $order) use ($partnerId) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $expr = $this->sqlPaymentsReportBankAcceptFeeCentsNullable($partnerId);
                $query->orderByRaw("({$expr}) IS NULL ASC");
                $query->orderByRaw("({$expr}) {$dir}");
            })
            ->orderColumn('bank_commission_payout', function ($query, $order) use ($partnerId) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $expr = $this->sqlPaymentsReportBankPayoutFeeCentsNullable($partnerId);
                $query->orderByRaw("({$expr}) IS NULL ASC");
                $query->orderByRaw("({$expr}) {$dir}");
            })
            ->addColumn('payout_amount', function (Payment $row) use ($partnerId) {
                // Только T-Bank, только после успешной выплаты (COMPLETED)
                if (empty($row->deal_id)) {
                    return null;
                }

                $payout = TinkoffPayout::query()
                    ->where('partner_id', (int) $partnerId)
                    ->where('deal_id', $row->deal_id)
                    ->where('status', 'COMPLETED')
                    ->orderByDesc('id')
                    ->first();

                if (! $payout) {
                    return null;
                }

                return round(((int) $payout->amount) / 100, 2);
            })
            ->addColumn('bank_commission_acquiring', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents, $canAdditional) {
                if (! $canAdditional) {
                    return null;
                }

                if (! $this->paymentRowIsTbankPayment($row)) {
                    return null;
                }

                if ($this->paymentRowHasBlockingRefund($row)) {
                    return null;
                }

                $grossCents = (int) round(((float) $row->summ) * 100);

                $method = null;
                if (! empty($row->deal_id)) {
                    $tp = TinkoffPayment::query()
                        ->where('partner_id', (int) $partnerId)
                        ->where('deal_id', $row->deal_id)
                        ->orderByDesc('id')
                        ->first();

                    $method = $tp ? (string) ($tp->method ?? null) : null;
                }

                $rule = $pickCommissionRule((int) $partnerId, $method);

                $bankAcceptFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->acquiring_percent ?? 2.49),
                    (float) ($rule->acquiring_min_fixed ?? 3.49)
                );

                return round($bankAcceptFee / 100, 2);
            })
            ->addColumn('bank_commission_payout', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents, $canAdditional) {
                if (! $canAdditional) {
                    return null;
                }

                if (! $this->paymentRowIsTbankPayment($row)) {
                    return null;
                }

                if ($this->paymentRowHasBlockingRefund($row)) {
                    return null;
                }

                if (! $this->paymentRowHasCompletedTbankPayout($row, $partnerId)) {
                    return null;
                }

                $grossCents = (int) round(((float) $row->summ) * 100);

                $method = null;
                if (! empty($row->deal_id)) {
                    $tp = TinkoffPayment::query()
                        ->where('partner_id', (int) $partnerId)
                        ->where('deal_id', $row->deal_id)
                        ->orderByDesc('id')
                        ->first();

                    $method = $tp ? (string) ($tp->method ?? null) : null;
                }

                $rule = $pickCommissionRule((int) $partnerId, $method);

                $bankPayoutFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->payout_percent ?? 0.10),
                    (float) ($rule->payout_min_fixed ?? 0.00)
                );

                return round($bankPayoutFee / 100, 2);
            })
            ->addColumn('platform_commission', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents, $canAdditional) {
                if (! $canAdditional) {
                    return null;
                }

                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                    return null;
                }

                if ($this->paymentRowHasBlockingRefund($row)) {
                    return null;
                }

                $grossCents = (int) round(((float) $row->summ) * 100);

                $method = null;
                if (! empty($row->deal_id)) {
                    $tp = TinkoffPayment::query()
                        ->where('partner_id', (int) $partnerId)
                        ->where('deal_id', $row->deal_id)
                        ->orderByDesc('id')
                        ->first();

                    $method = $tp ? (string) ($tp->method ?? null) : null;
                }

                $rule = $pickCommissionRule((int) $partnerId, $method);

//                $platformFee = $calcFeeCents(
//                    $grossCents,
//                    (float) ($rule->platform_percent ?? $rule->percent ?? 0.00),
//                    (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
//                );

                $platformFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->platform_percent ?? 0.00),
                    (float) ($rule->platform_min_fixed ?? 0.00)
                );

                return round($platformFee / 100, 2);
            })
            ->addColumn('commission_total', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents) {
                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                    return null;
                }

                if ($this->paymentRowHasBlockingRefund($row)) {
                    return null;
                }

                $grossCents = (int) round(((float) $row->summ) * 100);

                $method = null;
                if (! empty($row->deal_id)) {
                    $tp = TinkoffPayment::query()
                        ->where('partner_id', (int) $partnerId)
                        ->where('deal_id', $row->deal_id)
                        ->orderByDesc('id')
                        ->first();

                    $method = $tp ? (string) ($tp->method ?? null) : null;
                }

                $rule = $pickCommissionRule((int) $partnerId, $method);

                $bankAcceptFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->acquiring_percent ?? 2.49),
                    (float) ($rule->acquiring_min_fixed ?? 3.49)
                );
                $bankPayoutFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->payout_percent ?? 0.10),
                    (float) ($rule->payout_min_fixed ?? 0.00)
                );
//                $platformFee = $calcFeeCents(
//                    $grossCents,
//                    (float) ($rule->platform_percent ?? $rule->percent ?? 0.00),
//                    (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
//                );

                $platformFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->platform_percent ?? 0.00),
                    (float) ($rule->platform_min_fixed ?? 0.00)
                );

                return round(($bankAcceptFee + $bankPayoutFee + $platformFee) / 100, 2);
            })
            ->addColumn('net_to_partner', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents, $canAdditional) {
                if (! $canAdditional) {
                    return null;
                }

                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                    return null;
                }

                if ($this->paymentRowHasBlockingRefund($row)) {
                    return null;
                }

                $grossCents = (int) round(((float) $row->summ) * 100);

                $method = null;
                if (! empty($row->deal_id)) {
                    $tp = TinkoffPayment::query()
                        ->where('partner_id', (int) $partnerId)
                        ->where('deal_id', $row->deal_id)
                        ->orderByDesc('id')
                        ->first();

                    $method = $tp ? (string) ($tp->method ?? null) : null;
                }

                $rule = $pickCommissionRule((int) $partnerId, $method);

                $bankAcceptFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->acquiring_percent ?? 2.49),
                    (float) ($rule->acquiring_min_fixed ?? 3.49)
                );
                $bankPayoutFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->payout_percent ?? 0.10),
                    (float) ($rule->payout_min_fixed ?? 0.00)
                );
//                $platformFee = $calcFeeCents(
//                    $grossCents,
//                    (float) ($rule->platform_percent ?? $rule->percent ?? 0.00),
//                    (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
//                );

                $platformFee = $calcFeeCents(
                    $grossCents,
                    (float) ($rule->platform_percent ?? 0.00),
                    (float) ($rule->platform_min_fixed ?? 0.00)
                );

                $net = $grossCents - $bankAcceptFee - $bankPayoutFee - $platformFee;
                return round(max(0, $net) / 100, 2);
            })
            ->addColumn('refund_status', function (Payment $row) use ($canAdditional) {
                if (! $canAdditional) {
                    return '';
                }

                $attrs = $row->getAttributes();
                if (array_key_exists('latest_refund_status', $attrs)) {
                    $v = $attrs['latest_refund_status'];

                    return ($v !== null && $v !== '') ? (string) $v : '';
                }

                $refund = Refund::query()
                    ->where('payment_id', $row->id)
                    ->orderByDesc('id')
                    ->first();

                return $refund ? (string) $refund->status : '';
            })
            ->addColumn('refund_action', function (Payment $row) use ($partnerId, $canViewTbankHistory) {
                // Определяем провайдера
                $provider = (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status))
                    ? 'tbank'
                    : 'robokassa';

                // В отчёте возврат только для T-Bank (Robokassa — без кнопки в UI)
                if ($provider === 'robokassa') {
                    return '';
                }

                $tbankIntent = null;
                $pidStr = (is_string($row->payment_id) || is_numeric($row->payment_id))
                    ? (string) $row->payment_id
                    : '';

                if ($pidStr === '' || !ctype_digit($pidStr)) {
                    $pidStr = (is_string($row->payment_number) || is_numeric($row->payment_number))
                        ? (string) $row->payment_number
                        : '';
                }

                if ($pidStr !== '' && ctype_digit($pidStr)) {
                    $tbankIntent = PaymentIntent::query()
                        ->where('provider', 'tbank')
                        ->where('partner_id', (int) $partnerId)
                        ->where(function ($q) use ($pidStr) {
                            $pid = (int) $pidStr;
                            $q->where('provider_inv_id', $pid)
                                ->orWhere('tbank_payment_id', $pid);
                        })
                        ->orderByDesc('id')
                        ->first();
                }

                $intent = $tbankIntent;

                $disabled = false;
                $title = '';

                if ($this->paymentRowHasBlockingRefund($row)) {
                    $disabled = true;
                    $attrs = $row->getAttributes();
                    $st = (array_key_exists('latest_refund_status', $attrs) && $attrs['latest_refund_status'] !== null && $attrs['latest_refund_status'] !== '')
                        ? (string) $attrs['latest_refund_status']
                        : (string) (Refund::query()
                            ->where('payment_id', $row->id)
                            ->orderByDesc('id')
                            ->value('status') ?? '');
                    $title = $st === 'pending'
                        ? 'Возврат уже в обработке'
                        : 'Платёж уже возвращён';
                }

                if (! $disabled) {
                    if (! $intent) {
                        $disabled = true;
                        $title = 'Нет данных T-Bank (intent не найден)';
                    } elseif ((string) $intent->status !== 'paid') {
                        $disabled = true;
                        $title = 'Платёж не в статусе paid';
                    }
                }

                // T-Bank: запрет возврата, если по этой оплате выплата уже ушла в банк (только через tinkoff_payments).
                if (! $disabled && $tbankIntent) {
                    $tpPidStr = (is_string($row->payment_id) || is_numeric($row->payment_id))
                        ? (string) $row->payment_id
                        : '';
                    if ($tpPidStr === '' || !ctype_digit($tpPidStr)) {
                        $tpPidStr = (is_string($row->payment_number) || is_numeric($row->payment_number))
                            ? (string) $row->payment_number
                            : '';
                    }
                    if ($tpPidStr !== '' && ctype_digit($tpPidStr)) {
                        $tinkoffRowIds = TinkoffPayment::query()
                            ->where('partner_id', (int) $partnerId)
                            ->where('tinkoff_payment_id', $tpPidStr)
                            ->pluck('id');
                        $tbOrderId = trim((string) ($tbankIntent->tbank_order_id ?? ''));
                        if ($tbOrderId !== '') {
                            $tinkoffRowIds = $tinkoffRowIds->merge(
                                TinkoffPayment::query()
                                    ->where('partner_id', (int) $partnerId)
                                    ->where('order_id', $tbOrderId)
                                    ->pluck('id')
                            );
                        }
                        $tinkoffRowIds = $tinkoffRowIds->unique()->filter()->values()->all();
                        if ($tinkoffRowIds !== []) {
                            $hasBlockingPayout = TinkoffPayout::query()
                                ->where('partner_id', (int) $partnerId)
                                ->whereIn('payment_id', $tinkoffRowIds)
                                ->whereNotIn('status', ['REJECTED'])
                                ->whereNotNull('tinkoff_payout_payment_id')
                                ->where('tinkoff_payout_payment_id', '!=', '')
                                ->exists();
                            if ($hasBlockingPayout) {
                                $disabled = true;
                                $title = 'Возврат запрещён: выплата уже отправлена в банк (есть PaymentId).';
                            }
                        }
                    }
                }

                $amount = (float) $row->summ;

                $btnAttrs = [
                    'type="button"',
                    'class="btn btn-sm btn-outline-danger js-refund-btn"',
                    'data-payment-id="' . (int) $row->id . '"',
                    'data-provider="tbank"',
                    'data-amount="' . e((string) $amount) . '"',
                    'data-user="' . e((string) ($row->user_name ?? '')) . '"',
                    'data-month="' . e((string) ($row->payment_month ?? '')) . '"',
                ];

                if ($disabled) {
                    $btnAttrs[] = 'disabled';
                }

                $buttonHtml = '<button ' . implode(' ', $btnAttrs) . '>Возврат</button>';

                $historyButtonHtml = '';
                if ($canViewTbankHistory) {
                    $historyButtonHtml =
                        '<button type="button" class="btn btn-sm btn-outline-secondary ms-1 js-tbank-history-btn" ' .
                        'data-payment-id="' . (int) $row->id . '" ' .
                        'data-deal-id="' . e((string) ($row->deal_id ?? '')) . '" ' .
                        'data-bank-payment-id="' . e((string) ($row->payment_id ?? $row->payment_number ?? '')) . '"' .
                        '>История</button>';
                }

                if ($disabled) {
                    $wrapTitle = $title !== '' ? $title : 'Возврат недоступен';
                    return '<span title="' . e($wrapTitle) . '" style="cursor:not-allowed;">' . $buttonHtml . '</span>' . $historyButtonHtml;
                }

                return $buttonHtml . $historyButtonHtml;
            })
            ->rawColumns(['refund_action'])
            ->make(true);
    }

    private function applyPaymentsReportFilters($paymentsQuery, Request $request, int $partnerId): void
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

        if ($request->filled('payment_method')) {
            $m = (string) $request->query('payment_method');
            $paymentsQuery->where(function ($q) use ($m) {
                $q->where('pi_tbank.payment_method_webhook', $m)
                    ->orWhere(function ($q2) use ($m) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('pi_tbank.payment_method_webhook')
                                ->orWhere('pi_tbank.payment_method_webhook', '=', '');
                        })->where('pi_tbank.payment_method', $m);
                    });
            });
        }

        if ($request->filled('payment_refund_status')) {
            $s = (string) $request->query('payment_refund_status');
            if ($s === 'refunded') {
                $paymentsQuery->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('refunds')
                        ->whereColumn('refunds.payment_id', 'payments.id')
                        ->where('refunds.status', 'succeeded');
                });
            } elseif ($s === 'refund_pending') {
                $paymentsQuery->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('refunds')
                        ->whereColumn('refunds.payment_id', 'payments.id')
                        ->where('refunds.status', 'pending');
                })->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('refunds')
                        ->whereColumn('refunds.payment_id', 'payments.id')
                        ->where('refunds.status', 'succeeded');
                });
            } elseif ($s === 'no_refund') {
                $paymentsQuery->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('refunds')
                        ->whereColumn('refunds.payment_id', 'payments.id')
                        ->whereIn('refunds.status', ['pending', 'succeeded']);
                });
            }
        }

        $acqMin = $request->query('bank_commission_acquiring_min');
        if ($acqMin !== null && $acqMin !== '' && is_numeric($acqMin)) {
            $minCents = (int) round(((float) $acqMin) * 100);
            $expr = $this->sqlPaymentsReportBankAcceptFeeCentsNullable($partnerId);
            $paymentsQuery->whereRaw('('.$expr.') >= ?', [$minCents]);
        }
        $acqMax = $request->query('bank_commission_acquiring_max');
        if ($acqMax !== null && $acqMax !== '' && is_numeric($acqMax)) {
            $maxCents = (int) round(((float) $acqMax) * 100);
            $expr = $this->sqlPaymentsReportBankAcceptFeeCentsNullable($partnerId);
            $paymentsQuery->whereRaw('('.$expr.') <= ?', [$maxCents]);
        }

        $payMin = $request->query('bank_commission_payout_min');
        if ($payMin !== null && $payMin !== '' && is_numeric($payMin)) {
            $minCents = (int) round(((float) $payMin) * 100);
            $expr = $this->sqlPaymentsReportBankPayoutFeeCentsNullable($partnerId);
            $paymentsQuery->whereRaw('('.$expr.') >= ?', [$minCents]);
        }
        $payMax = $request->query('bank_commission_payout_max');
        if ($payMax !== null && $payMax !== '' && is_numeric($payMax)) {
            $maxCents = (int) round(((float) $payMax) * 100);
            $expr = $this->sqlPaymentsReportBankPayoutFeeCentsNullable($partnerId);
            $paymentsQuery->whereRaw('('.$expr.') <= ?', [$maxCents]);
        }
    }

    /**
     * Агрегаты для шапки отчёта «Платежи» (те же фильтры и строки, что и таблица).
     * net_to_partner / platform_commission — та же формула, что в getPayments (строки с возвратом pending/succeeded не суммируются); payout_amount — из tinkoff_payouts COMPLETED.
     *
     * @return array{sum_payments: float, net_to_partner: float, platform_commission: float, payout_amount: float}
     */
    private function computePaymentsReportToolbarAggregates(int $partnerId, Request $request): array
    {
        $q = $this->basePaymentsReportQuery($partnerId);
        $this->applyPaymentsReportFilters($q, $request, $partnerId);

        $sumPayments = (float) (clone $q)->sum('payments.summ');

        $commissionRules = $this->tinkoffCommissionRulesForPaymentsReport();

        $netSum = 0.0;
        $platformSum = 0.0;
        $payoutSum = 0.0;

        $rowQuery = clone $q;
        $rowQuery->orderBy('payments.id');

        $rowQuery->chunk(500, function ($payments) use ($partnerId, $commissionRules, &$netSum, &$platformSum, &$payoutSum): void {
            foreach ($payments as $payment) {
                /** @var Payment $payment */
                $net = $this->paymentRowNetToPartnerAmount($payment, $partnerId, $commissionRules);
                if ($net !== null) {
                    $netSum += $net;
                }
                $plat = $this->paymentRowPlatformCommissionAmount($payment, $partnerId, $commissionRules);
                if ($plat !== null) {
                    $platformSum += $plat;
                }
                $payout = $this->paymentRowPayoutAmountRub($payment, $partnerId);
                if ($payout !== null) {
                    $payoutSum += $payout;
                }
            }
        });

        return [
            'sum_payments' => $sumPayments,
            'net_to_partner' => $netSum,
            'platform_commission' => $platformSum,
            'payout_amount' => $payoutSum,
        ];
    }

    /**
     * @param  array{sum_payments: float, net_to_partner: float, platform_commission: float, payout_amount: float}  $aggregates
     * @return array<string, string>
     */
    private function formatPaymentsToolbarPayload(array $aggregates, bool $canNet, bool $canPayout, bool $canPlatform): array
    {
        $out = [
            'sum_payments_formatted' => number_format(round($aggregates['sum_payments']), 0, '', ' '),
            'sum_payments_raw' => (float) round($aggregates['sum_payments'], 2),
        ];

        if ($canNet) {
            $out['net_to_partner_formatted'] = number_format(round($aggregates['net_to_partner']), 0, '', ' ');
            $out['net_to_partner_raw'] = (float) round($aggregates['net_to_partner'], 2);
        }
        if ($canPayout) {
            $out['payout_amount_formatted'] = number_format(round($aggregates['payout_amount']), 0, '', ' ');
            $out['payout_amount_raw'] = (float) round($aggregates['payout_amount'], 2);
        }
        if ($canPlatform) {
            $out['platform_commission_formatted'] = number_format(round($aggregates['platform_commission']), 0, '', ' ');
            $out['platform_commission_raw'] = (float) round($aggregates['platform_commission'], 2);
        }

        return $out;
    }

    private function tinkoffCommissionRulesForPaymentsReport(): Collection
    {
        return TinkoffCommissionRule::query()
            ->where('is_enabled', true)
            ->orderByRaw('partner_id is null, method is null')
            ->get();
    }

    private function calcFeeCentsToolbar(int $amountCents, float $percent, float $minFixedRub): int
    {
        $fee = (int) round($amountCents * ($percent / 100));
        $min = (int) round($minFixedRub * 100);

        return max($fee, $min);
    }

    private function pickCommissionRuleForToolbar(Collection $commissionRules, int $pid, ?string $method): TinkoffCommissionRule
    {
        /** @var TinkoffCommissionRule|null $chosen */
        $chosen = $commissionRules->first(function (TinkoffCommissionRule $r) use ($pid, $method) {
            $partnerOk = ($r->partner_id === null) || ((int) $r->partner_id === $pid);
            $methodOk = ($r->method === null) || ((string) $r->method === (string) $method);

            return $partnerOk && $methodOk;
        });

        return $chosen ?: new TinkoffCommissionRule([
            'acquiring_percent' => 2.49,
            'acquiring_min_fixed' => 3.49,
            'payout_percent' => 0.10,
            'payout_min_fixed' => 0.00,
            'platform_percent' => 0.00,
            'platform_min_fixed' => 0.00,
        ]);
    }

    private function paymentRowTbankMethod(Payment $row, int $partnerId): ?string
    {
        if (empty($row->deal_id)) {
            return null;
        }

        $tp = TinkoffPayment::query()
            ->where('partner_id', (int) $partnerId)
            ->where('deal_id', $row->deal_id)
            ->orderByDesc('id')
            ->first();

        return $tp ? (string) ($tp->method ?? null) : null;
    }

    /**
     * Синхронно с addColumn net_to_partner в getPayments.
     */
    private function paymentRowNetToPartnerAmount(Payment $row, int $partnerId, Collection $commissionRules): ?float
    {
        if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
            return null;
        }

        if ($this->paymentRowHasBlockingRefund($row)) {
            return null;
        }

        $grossCents = (int) round(((float) $row->summ) * 100);
        $method = $this->paymentRowTbankMethod($row, $partnerId);
        $rule = $this->pickCommissionRuleForToolbar($commissionRules, (int) $partnerId, $method);

        $bankAcceptFee = $this->calcFeeCentsToolbar(
            $grossCents,
            (float) ($rule->acquiring_percent ?? 2.49),
            (float) ($rule->acquiring_min_fixed ?? 3.49)
        );
        $bankPayoutFee = $this->calcFeeCentsToolbar(
            $grossCents,
            (float) ($rule->payout_percent ?? 0.10),
            (float) ($rule->payout_min_fixed ?? 0.00)
        );
        $platformFee = $this->calcFeeCentsToolbar(
            $grossCents,
            (float) ($rule->platform_percent ?? 0.00),
            (float) ($rule->platform_min_fixed ?? 0.00)
        );

        $net = $grossCents - $bankAcceptFee - $bankPayoutFee - $platformFee;

        return round(max(0, $net) / 100, 2);
    }

    /**
     * Синхронно с addColumn platform_commission в getPayments.
     */
    private function paymentRowPlatformCommissionAmount(Payment $row, int $partnerId, Collection $commissionRules): ?float
    {
        if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
            return null;
        }

        if ($this->paymentRowHasBlockingRefund($row)) {
            return null;
        }

        $grossCents = (int) round(((float) $row->summ) * 100);
        $method = $this->paymentRowTbankMethod($row, $partnerId);
        $rule = $this->pickCommissionRuleForToolbar($commissionRules, (int) $partnerId, $method);

        $platformFee = $this->calcFeeCentsToolbar(
            $grossCents,
            (float) ($rule->platform_percent ?? 0.00),
            (float) ($rule->platform_min_fixed ?? 0.00)
        );

        return round($platformFee / 100, 2);
    }

    /**
     * Синхронно с addColumn payout_amount в getPayments.
     */
    private function paymentRowPayoutAmountRub(Payment $row, int $partnerId): ?float
    {
        if (empty($row->deal_id)) {
            return null;
        }

        $payout = TinkoffPayout::query()
            ->where('partner_id', (int) $partnerId)
            ->where('deal_id', $row->deal_id)
            ->where('status', 'COMPLETED')
            ->orderByDesc('id')
            ->first();

        if (! $payout) {
            return null;
        }

        return round(((int) $payout->amount) / 100, 2);
    }

    /**
     * История статусов T‑Bank по платежу из отчёта "Платежи".
     *
     * Возвращает объединённый таймлайн:
     * - tinkoff_payment_status_logs (webhook/прочие источники)
     * - tinkoff_payout_status_logs (история выплат по deal_id)
     */
    public function tbankHistory(Request $request, Payment $payment)
    {
        $partnerId = $this->requirePartnerId();

        // Жёсткий tenant-scope: платёж обязан принадлежать текущему партнёру.
        if ((int) ($payment->partner_id ?? 0) !== (int) $partnerId) {
            abort(403);
        }

        $provider = (!empty($payment->deal_id) || !empty($payment->payment_id) || !empty($payment->payment_status))
            ? 'tbank'
            : 'robokassa';

        if ($provider !== 'tbank') {
            abort(404);
        }

        $events = [];

        // ---- Payment status logs ----
        $bankPaymentIdStr = null;
        $candidate = (is_string($payment->payment_id) || is_numeric($payment->payment_id))
            ? (string) $payment->payment_id
            : '';

        if ($candidate !== '' && ctype_digit($candidate)) {
            $bankPaymentIdStr = $candidate;
        } else {
            $candidate = (is_string($payment->payment_number) || is_numeric($payment->payment_number))
                ? (string) $payment->payment_number
                : '';
            if ($candidate !== '' && ctype_digit($candidate)) {
                $bankPaymentIdStr = $candidate;
            }
        }

        $payLogQuery = DB::table('tinkoff_payment_status_logs')
            ->where('partner_id', (int) $partnerId);

        if ($bankPaymentIdStr !== null) {
            $payLogQuery->where('bank_payment_id', $bankPaymentIdStr);
        } else {
            // fallback: по deal_id -> tinkoff_payments -> logs by tinkoff_payment_id/order_id
            $tpIds = [];
            $tpOrderIds = [];
            if (!empty($payment->deal_id)) {
                $tps = TinkoffPayment::query()
                    ->where('partner_id', (int) $partnerId)
                    ->where('deal_id', (string) $payment->deal_id)
                    ->get(['id', 'order_id']);

                $tpIds = $tps->pluck('id')->filter()->values()->all();
                $tpOrderIds = $tps->pluck('order_id')->filter()->values()->all();
            }

            $payLogQuery->where(function ($q) use ($tpIds, $tpOrderIds) {
                if (!empty($tpIds)) {
                    $q->whereIn('tinkoff_payment_id', $tpIds);
                }
                if (!empty($tpOrderIds)) {
                    $q->orWhereIn('order_id', $tpOrderIds);
                }
                if (empty($tpIds) && empty($tpOrderIds)) {
                    // чтобы не утекли чужие логи при пустом фильтре — гарантированно пустая выборка
                    $q->whereRaw('1=0');
                }
            });
        }

        $payLogs = $payLogQuery
            ->orderBy('created_at')
            ->get();

        foreach ($payLogs as $l) {
            $events[] = [
                'at' => (string) ($l->created_at ?? ''),
                'kind' => 'payment',
                'source' => (string) ($l->event_source ?? 'webhook'),
                'from_status' => $l->from_status !== null ? (string) $l->from_status : null,
                'to_status' => $l->to_status !== null ? (string) $l->to_status : null,
                'bank_status' => $l->bank_status !== null ? (string) $l->bank_status : null,
                'bank_payment_id' => $l->bank_payment_id !== null ? (string) $l->bank_payment_id : null,
                'order_id' => $l->order_id !== null ? (string) $l->order_id : null,
                'payload' => $l->payload ? json_decode((string) $l->payload, true) : null,
            ];
        }

        // ---- Payout status logs ----
        $payouts = collect();
        if (!empty($payment->deal_id)) {
            $payouts = TinkoffPayout::query()
                ->where('partner_id', (int) $partnerId)
                ->where('deal_id', (string) $payment->deal_id)
                ->orderBy('id')
                ->get();
        }

        $payoutIds = $payouts->pluck('id')->filter()->values()->all();
        if (!empty($payoutIds)) {
            $payoutLogs = DB::table('tinkoff_payout_status_logs')
                ->whereIn('payout_id', $payoutIds)
                ->orderBy('created_at')
                ->get();

            foreach ($payoutLogs as $l) {
                $events[] = [
                    'at' => (string) ($l->created_at ?? ''),
                    'kind' => 'payout',
                    'source' => 'poll',
                    'from_status' => $l->from_status !== null ? (string) $l->from_status : null,
                    'to_status' => $l->to_status !== null ? (string) $l->to_status : null,
                    'bank_status' => null,
                    'bank_payment_id' => null,
                    'order_id' => null,
                    'payload' => $l->payload ? json_decode((string) $l->payload, true) : null,
                    'payout_id' => (int) ($l->payout_id ?? 0),
                ];
            }
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });

        return response()->json([
            'payment' => [
                'id' => (int) $payment->id,
                'summ' => (float) ($payment->summ ?? 0),
                'operation_date' => $payment->operation_date,
                'deal_id' => $payment->deal_id,
                'bank_payment_id' => $bankPaymentIdStr,
            ],
            'events' => $events,
            'payouts' => $payouts->map(function (TinkoffPayout $p) {
                return [
                    'id' => (int) $p->id,
                    'status' => (string) ($p->status ?? ''),
                    'amount' => (int) ($p->amount ?? 0),
                    'when_to_run' => $p->when_to_run,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ];
            })->values(),
        ]);
    }

    /**
     * Вернуть настройки колонок для текущего пользователя
     * для таблицы "reports_payments".
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canAdditional = $authUser?->can('reports.additional.value.view') ?? false;

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'reports_payments')
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        if (array_key_exists('bank_commission_total', $columns)) {
            $v = $columns['bank_commission_total'];
            $columns['bank_commission_acquiring'] = $v;
            $columns['bank_commission_payout'] = $v;
            unset($columns['bank_commission_total']);
        }

        if (! $canAdditional) {
            foreach (['bank_commission_acquiring', 'bank_commission_payout', 'platform_commission', 'net_to_partner', 'refund_status'] as $k) {
                unset($columns[$k]);
            }
        } else {
            unset($columns['commission_total']);
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок для текущего пользователя.
     * Ожидает: columns: { user_name: true, team_title: false, ... }
     */
    public function saveColumnsSettings(Request $request)
    {
        $userId = Auth::id();
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canAdditional = $authUser?->can('reports.additional.value.view') ?? false;

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $rawColumns = $data['columns'];
        $normalized = [];

        foreach ($rawColumns as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[$key] = $bool;
        }

        if (! $canAdditional) {
            foreach (['bank_commission_acquiring', 'bank_commission_payout', 'platform_commission', 'net_to_partner', 'refund_status'] as $k) {
                $normalized[$k] = false;
            }
        }

        unset($normalized['bank_commission_total']);

        if ($canAdditional) {
            unset($normalized['commission_total']);
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => 'reports_payments',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

}