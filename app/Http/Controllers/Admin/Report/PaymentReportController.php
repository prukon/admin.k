<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\UserTableSetting;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
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
use Yajra\DataTables\DataTables;
use Carbon\Carbon;


class PaymentReportController extends Controller
{
    //Отчет Платежи
    public function payments()
    {
        // 1) партнёр
        $partnerId = app('current_partner')->id;
        Log::debug('[payments] Partner ID', ['partnerId' => $partnerId]);

        // 2) включаем лог запросов
        DB::enableQueryLog();

        // 3) считаем сумму

        $totalPaidPrice = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->sum('payments.summ');

        Log::debug('[payments] Raw total', ['totalPaidPriceRaw' => $totalPaidPrice]);

        // 4) SQL‑лог
        Log::debug('[payments] Executed query', DB::getQueryLog()[0] ?? []);

        // 5) форматируем сумму
        $totalPaidPrice = number_format($totalPaidPrice, 0, '', ' ');
        Log::debug('[payments] Formatted total', ['totalPaidPrice' => $totalPaidPrice]);

        // 6) проверяем, включена ли оплата T-Bank
        $tbankPs = PaymentSystem::where('partner_id', $partnerId)->where('name', 'tbank')->first();
        $tbankEnabled = $tbankPs ? true : false;

        // 7) представление
        return view(
            'admin.report.index',
            [
                'activeTab' => 'payment',
                'totalPaidPrice' => $totalPaidPrice,
                'tbankEnabled' => $tbankEnabled
            ]
        );
    }

    //Данные для отчета Платежи
    public function getPayments(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $partnerId = app('current_partner')->id;

        // Базовый запрос: только нужный партнёр, без get()
        $paymentsQuery = Payment::query()
            ->with(['user.team'])
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('users.partner_id', $partnerId)
            ->select('payments.*');

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

        return DataTables::of($paymentsQuery)
            ->addIndexColumn()
            ->addColumn('user_name', function (Payment $row) {
                if (!empty($row->user_name)) {
                    return $row->user_name;
                }

                $user = $row->user;
                if ($user) {
                    $full = trim(($user->lastname ?? '') . ' ' . ($user->name ?? ''));
                    return $full !== '' ? $full : 'Без пользователя';
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
            ->addColumn('bank_commission_total', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents) {
                // Только T-Bank. Если платёж явно не T-Bank — null.
                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                    return null;
                }

                $grossCents = (int) round(((float) $row->summ) * 100);

                // method из TinkoffPayment
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

                return round(($bankAcceptFee + $bankPayoutFee) / 100, 2);
            })
            ->addColumn('bank_commission_acquiring', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents) {
                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
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
            ->addColumn('bank_commission_payout', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents) {
                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
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
            ->addColumn('platform_commission', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents) {
                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
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
            ->addColumn('net_to_partner', function (Payment $row) use ($partnerId, $pickCommissionRule, $calcFeeCents) {
                if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
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
            ->addColumn('refund_status', function (Payment $row) {
                $refund = Refund::query()
                    ->where('payment_id', $row->id)
                    ->orderByDesc('id')
                    ->first();

                return $refund ? (string) $refund->status : '';
            })
            ->addColumn('refund_action', function (Payment $row) use ($partnerId) {
                // Определяем провайдера
                $provider = (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status))
                    ? 'tbank'
                    : 'robokassa';

                // Последний рефанд по этому платежу
                $refund = Refund::query()
                    ->where('payment_id', $row->id)
                    ->orderByDesc('id')
                    ->first();

                // Находим intent для провайдера
                $robokassaIntent = null;
                $tbankIntent = null;

                if ($provider === 'robokassa') {
                    $invStr = (is_string($row->payment_number) || is_numeric($row->payment_number))
                        ? (string) $row->payment_number
                        : '';

                    if ($invStr !== '' && ctype_digit($invStr)) {
                        $robokassaIntent = PaymentIntent::query()
                            ->where('provider', 'robokassa')
                            ->where('partner_id', (int) $partnerId)
                            ->where('provider_inv_id', (int) $invStr)
                            ->orderByDesc('id')
                            ->first();
                    }
                } else {
                    // T-Bank
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
                }

                $intent = $provider === 'tbank' ? $tbankIntent : $robokassaIntent;

                $disabled = false;
                $title = '';

                if ($refund && in_array((string) $refund->status, ['pending', 'succeeded'], true)) {
                    $disabled = true;
                    $title = (string) $refund->status === 'pending'
                        ? 'Возврат уже в обработке'
                        : 'Платёж уже возвращён';
                }

                if (! $disabled) {
                    if (! $intent) {
                        $disabled = true;
                        $title = $provider === 'tbank'
                            ? 'Нет данных T-Bank (intent не найден)'
                            : 'Нет данных Robokassa (intent не найден)';
                    } elseif ((string) $intent->status !== 'paid') {
                        $disabled = true;
                        $title = 'Платёж не в статусе paid';
                    }
                }

                // Robokassa: лимит 7 дней
                if (! $disabled && $provider === 'robokassa') {
                    $paidAt = $intent && $intent->paid_at
                        ? Carbon::parse($intent->paid_at)
                        : ($row->operation_date ? Carbon::parse($row->operation_date) : null);

                    if ($paidAt && $paidAt->copy()->addDays(7)->lt(now())) {
                        $disabled = true;
                        $title = 'Прошло больше 7 дней';
                    }
                }

                // T-Bank: запрет возврата, если есть выплата партнёру
                if (! $disabled && $provider === 'tbank' && ! empty($row->deal_id)) {
                    $payout = TinkoffPayout::query()
                        ->where('partner_id', (int) $partnerId)
                        ->where('deal_id', $row->deal_id)
                        ->orderByDesc('id')
                        ->first();

                    if ($payout && (string) $payout->status !== 'REJECTED') {
                        $disabled = true;
                        $title = 'Возврат запрещён: есть выплата партнёру (статус: ' . (string) $payout->status . ')';
                    }
                }

                $amount = (float) $row->summ;

                $btnAttrs = [
                    'type="button"',
                    'class="btn btn-sm btn-outline-danger js-refund-btn"',
                    'data-payment-id="' . (int) $row->id . '"',
                    'data-provider="' . e((string) $provider) . '"',
                    'data-amount="' . e((string) $amount) . '"',
                    'data-user="' . e((string) ($row->user_name ?? '')) . '"',
                    'data-month="' . e((string) ($row->payment_month ?? '')) . '"',
                ];

                if ($disabled) {
                    $btnAttrs[] = 'disabled';
                }

                $buttonHtml = '<button ' . implode(' ', $btnAttrs) . '>Возврат</button>';

                if ($disabled) {
                    $wrapTitle = $title !== '' ? $title : 'Возврат недоступен';
                    return '<span title="' . e($wrapTitle) . '" style="cursor:not-allowed;">' . $buttonHtml . '</span>';
                }

                return $buttonHtml;
            })
            ->rawColumns(['refund_action'])
            ->make(true);
    }

    /**
     * Вернуть настройки колонок для текущего пользователя
     * для таблицы "reports_payments".
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'reports_payments')
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
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