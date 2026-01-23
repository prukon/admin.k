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

        //С учетом удаленных юзеров
//        $totalPaidPriceRaw = DB::table('payments')
//            ->leftJoin('users', 'users.id', '=', 'payments.user_id')// оставляем строки без сопоставленного user
//            ->whereNull('users.id')// user не найден
////            ->where('users.partner_id', $partnerId)
//            ->sum('payments.summ');                                  // суммируем

//            старая реазиация
        $totalPaidPrice = DB::table('payments')
            ->sum('payments.summ');


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

    // Отчёт -> "Платежные запросы" (payment_intents)
    public function paymentIntents(Request $request)
    {
        return view('admin.report.index', [
            'activeTab' => 'payment-intents',
            'filters' => $request->query(),
        ]);
    }

    // Данные для отчёта "Платежные запросы" (DataTables, server-side)
    public function getPaymentIntents(Request $request)
    {
        $q = PaymentIntent::query()
            ->with(['user', 'partner'])
            ->select('payment_intents.*');

        // По умолчанию ограничиваем текущим партнёром (если контекст выбран)
        $currentPartner = app()->bound('current_partner') ? app('current_partner') : null;
        if ($currentPartner && isset($currentPartner->id)) {
            $q->where('partner_id', (int) $currentPartner->id);
        }

        // Доп. фильтры из формы
        if ($request->filled('inv_id') && ctype_digit((string) $request->query('inv_id'))) {
            $inv = (int) $request->query('inv_id');
            // inv_id может быть как внутренним intent.id, так и внешним provider_inv_id
            $q->where(function ($sub) use ($inv) {
                $sub->where('id', $inv)->orWhere('provider_inv_id', $inv);
            });
        }

        if ($request->filled('status')) {
            $q->where('status', (string) $request->query('status'));
        }

        if ($request->filled('provider')) {
            $q->where('provider', (string) $request->query('provider'));
        }

        if ($request->filled('partner_id') && ctype_digit((string) $request->query('partner_id'))) {
            $q->where('partner_id', (int) $request->query('partner_id'));
        }

        if ($request->filled('user_id') && ctype_digit((string) $request->query('user_id'))) {
            $q->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('created_from')) {
            $q->whereDate('created_at', '>=', (string) $request->query('created_from'));
        }
        if ($request->filled('created_to')) {
            $q->whereDate('created_at', '<=', (string) $request->query('created_to'));
        }

        if ($request->filled('paid_from')) {
            $q->whereDate('paid_at', '>=', (string) $request->query('paid_from'));
        }
        if ($request->filled('paid_to')) {
            $q->whereDate('paid_at', '<=', (string) $request->query('paid_to'));
        }

        // Важно: базовая сортировка по id desc (если DataTables не прислал order)
        if (!$request->has('order')) {
            $q->orderByDesc('id');
        }

        return DataTables::of($q)
            ->addColumn('partner_title', function (PaymentIntent $intent) {
                return (string) ($intent->partner->title ?? ($intent->partner->name ?? ''));
            })
            ->addColumn('user_name', function (PaymentIntent $intent) {
                return (string) ($intent->user->full_name ?? ($intent->user->name ?? ''));
            })
            ->editColumn('created_at', function (PaymentIntent $intent) {
                return $intent->created_at ? $intent->created_at->format('Y-m-d H:i:s') : '';
            })
            ->editColumn('paid_at', function (PaymentIntent $intent) {
                return $intent->paid_at ? $intent->paid_at->format('Y-m-d H:i:s') : '';
            })
            ->toJson();
    }

    //Данные для отчета Платежи
    public function getPayments(Request $request)
    {
        $partnerId = app('current_partner')->id;

        if ($request->ajax()) {

            //через свой флаг
//            $payments = Payment::with(['user.team'])
//                ->where('partner_id', $partnerId)
//                ->get();

            //            старая реазиация
            $payments = Payment::with(['user.team'])->get();


            //через User
            $payments = Payment::with(['user.team'])
                ->join('users', 'users.id', '=', 'payments.user_id')// связываем payments с users
                ->where('users.partner_id', $partnerId)// берём только партнёра user
                ->select('payments.*')
                ->get();

            $paymentIds = $payments->pluck('id')->filter()->values();
            $refundByPaymentId = Refund::whereIn('payment_id', $paymentIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('payment_id')
                ->map(fn ($g) => $g->first());

            // ---- Intents: Robokassa ----
            $robokassaInvIds = $payments
                ->filter(fn ($p) => empty($p->deal_id) && empty($p->payment_id) && empty($p->payment_status))
                ->pluck('payment_number')
                ->filter(fn ($v) => is_string($v) || is_numeric($v))
                ->map(fn ($v) => (string) $v)
                ->filter(fn ($v) => ctype_digit($v))
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values();

            $robokassaIntentByInvId = PaymentIntent::where('provider', 'robokassa')
                ->where('partner_id', (int) $partnerId)
                ->whereIn('provider_inv_id', $robokassaInvIds)
                ->get()
                ->keyBy('provider_inv_id');

            // ---- Intents: T-Bank ----
            $tbankPaymentIds = $payments
                ->filter(fn ($p) => !empty($p->deal_id) || !empty($p->payment_id) || !empty($p->payment_status))
                ->map(function ($p) {
                    $pid = (is_string($p->payment_id) || is_numeric($p->payment_id)) ? (string) $p->payment_id : '';
                    if ($pid !== '' && ctype_digit($pid)) return (int) $pid;
                    $pn = (is_string($p->payment_number) || is_numeric($p->payment_number)) ? (string) $p->payment_number : '';
                    if ($pn !== '' && ctype_digit($pn)) return (int) $pn;
                    return null;
                })
                ->filter()
                ->unique()
                ->values();

            $tbankIntentByPaymentId = $tbankPaymentIds->isEmpty()
                ? collect()
                : PaymentIntent::query()
                    ->where('provider', 'tbank')
                    ->where('partner_id', (int) $partnerId)
                    ->where(function ($q) use ($tbankPaymentIds) {
                        $q->whereIn('provider_inv_id', $tbankPaymentIds)
                          ->orWhereIn('tbank_payment_id', $tbankPaymentIds);
                    })
                    ->get()
                    ->keyBy(function (PaymentIntent $i) {
                        return (int) ($i->tbank_payment_id ?: $i->provider_inv_id);
                    });

            // ---- Payouts: запрет возврата, если была выплата партнёру (по deal_id) ----
            $dealIds = $payments->pluck('deal_id')
                ->filter(fn ($v) => is_string($v) && $v !== '')
                ->unique()
                ->values();

            $payoutByDealId = $dealIds->isEmpty()
                ? collect()
                : TinkoffPayout::query()
                    ->where('partner_id', (int) $partnerId)
                    ->whereIn('deal_id', $dealIds)
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('deal_id')
                    ->map(fn ($g) => $g->first());

            // ---- Payouts: успешные выплаты (для колонки "Выплата") ----
            // Показываем сумму только после фактического зачисления денег (Status=COMPLETED).
            $completedPayoutByDealId = $dealIds->isEmpty()
                ? collect()
                : TinkoffPayout::query()
                    ->where('partner_id', (int) $partnerId)
                    ->whereIn('deal_id', $dealIds)
                    ->where('status', 'COMPLETED')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('deal_id')
                    ->map(fn ($g) => $g->first());

            // ---- T-Bank payments: метод оплаты (card/sbp/tpay) для выбора правил комиссии ----
            $tbankPaymentByDealId = $dealIds->isEmpty()
                ? collect()
                : TinkoffPayment::query()
                    ->where('partner_id', (int) $partnerId)
                    ->whereIn('deal_id', $dealIds)
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('deal_id')
                    ->map(fn ($g) => $g->first());

            // ---- Commission rules (T-Bank): эквайринг + выплата + платформа ----
            $commissionRules = TinkoffCommissionRule::query()
                ->where('is_enabled', true)
                // приоритет: конкретный партнер/метод (как в сервисе)
                ->orderByRaw('partner_id is null, method is null')
                ->get();

            $calcFeeCents = static function (int $amountCents, float $percent, float $minFixedRub): int {
                $fee = (int) round($amountCents * ($percent / 100));
                $min = (int) round($minFixedRub * 100);
                return max($fee, $min);
            };

            $pickCommissionRule = static function (int $pid, ?string $method) use ($commissionRules): TinkoffCommissionRule {
                $chosen = $commissionRules->first(function (TinkoffCommissionRule $r) use ($pid, $method) {
                    $partnerOk = ($r->partner_id === null) || ((int) $r->partner_id === (int) $pid);
                    $methodOk = ($r->method === null) || ((string) $r->method === (string) $method);
                    return $partnerOk && $methodOk;
                });

                return $chosen ?: new TinkoffCommissionRule([
                    'acquiring_percent'   => 2.49,
                    'acquiring_min_fixed' => 3.49,
                    'payout_percent'      => 0.10,
                    'payout_min_fixed'    => 0.00,
                    'platform_percent'    => 0.00,
                    'platform_min_fixed'  => 0.00,
                ]);
            };



            return DataTables::of($payments)
                ->addIndexColumn()
                    ->addColumn('user_name', function ($row) {
                        // Проверяем, есть ли пользователь в таблице payments
                        return $row->user_name
                            ? $row->user_name // Возвращаем имя пользователя из payments
                            : ($row->user ? $row->user->name : 'Без пользователя'); // Или из связанной модели, если нет в payments
                    })
                ->addColumn('user_id', function ($row) {
                    // Возвращаем user_id, если он существует, иначе null
                    return $row->user ? $row->user->id : null;
                })
                ->addColumn('team_title', function ($row) {
                    // Проверка, существует ли пользователь и его команда
                    return $row->user && $row->user->team
                        ? $row->user->team->title // Возвращаем название команды
                        : 'Без команды'; // Если команды нет
                })
                ->addColumn('summ', function ($row) {
//                    return number_format($row->summ, 0) . ' руб'; // Формат суммы
                    return (float)$row->summ;

                })
                ->addColumn('operation_date', function ($row) {
                    return $row->operation_date; // Дата операции
                })
                ->addColumn('payment_provider', function ($row) {
                    return (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status)) ? 'tbank' : 'robokassa';
                })
                ->addColumn('payout_amount', function ($row) use ($completedPayoutByDealId) {
                    // Только для T-Bank. Заполняем только после успешного зачисления денег (COMPLETED).
                    if (empty($row->deal_id)) {
                        return null;
                    }

                    $payout = $completedPayoutByDealId->get($row->deal_id);
                    if (!$payout) {
                        return null;
                    }

                    return round(((int) $payout->amount) / 100, 2);
                })
                ->addColumn('bank_commission_total', function ($row) use ($partnerId, $tbankPaymentByDealId, $pickCommissionRule, $calcFeeCents) {
                    // Только для T-Bank. Заполняем всегда (даже если выплаты ещё не было).
                    if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                        return null;
                    }

                    $grossCents = (int) round(((float) $row->summ) * 100);
                    $method = null;
                    if (!empty($row->deal_id)) {
                        $tp = $tbankPaymentByDealId->get($row->deal_id);
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
                ->addColumn('bank_commission_acquiring', function ($row) use ($partnerId, $tbankPaymentByDealId, $pickCommissionRule, $calcFeeCents) {
                    // Только для hover-расшифровки. Только для T-Bank.
                    if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                        return null;
                    }

                    $grossCents = (int) round(((float) $row->summ) * 100);
                    $method = null;
                    if (!empty($row->deal_id)) {
                        $tp = $tbankPaymentByDealId->get($row->deal_id);
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
                ->addColumn('bank_commission_payout', function ($row) use ($partnerId, $tbankPaymentByDealId, $pickCommissionRule, $calcFeeCents) {
                    // Только для hover-расшифровки. Только для T-Bank.
                    if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                        return null;
                    }

                    $grossCents = (int) round(((float) $row->summ) * 100);
                    $method = null;
                    if (!empty($row->deal_id)) {
                        $tp = $tbankPaymentByDealId->get($row->deal_id);
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
                ->addColumn('platform_commission', function ($row) use ($partnerId, $tbankPaymentByDealId, $pickCommissionRule, $calcFeeCents) {
                    // Только для T-Bank. Заполняем всегда (даже если выплаты ещё не было).
                    if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                        return null;
                    }

                    $grossCents = (int) round(((float) $row->summ) * 100);
                    $method = null;
                    if (!empty($row->deal_id)) {
                        $tp = $tbankPaymentByDealId->get($row->deal_id);
                        $method = $tp ? (string) ($tp->method ?? null) : null;
                    }
                    $rule = $pickCommissionRule((int) $partnerId, $method);

                    $platformFee = $calcFeeCents(
                        $grossCents,
                        (float) ($rule->platform_percent ?? $rule->percent ?? 0.00),
                        (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
                    );

                    return round($platformFee / 100, 2);
                })
                ->addColumn('commission_total', function ($row) use ($partnerId, $tbankPaymentByDealId, $pickCommissionRule, $calcFeeCents) {
                    // Только для T-Bank. Суммарные удержания: банк(эквайринг+выплата) + платформа.
                    if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                        return null;
                    }

                    $grossCents = (int) round(((float) $row->summ) * 100);
                    $method = null;
                    if (!empty($row->deal_id)) {
                        $tp = $tbankPaymentByDealId->get($row->deal_id);
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
                    $platformFee = $calcFeeCents(
                        $grossCents,
                        (float) ($rule->platform_percent ?? $rule->percent ?? 0.00),
                        (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
                    );

                    return round(($bankAcceptFee + $bankPayoutFee + $platformFee) / 100, 2);
                })
                ->addColumn('net_to_partner', function ($row) use ($partnerId, $tbankPaymentByDealId, $pickCommissionRule, $calcFeeCents) {
                    // Только для T-Bank. "К выплате" = сумма - все удержания (не факт выплаты).
                    if (empty($row->deal_id) && empty($row->payment_id) && empty($row->payment_status)) {
                        return null;
                    }

                    $grossCents = (int) round(((float) $row->summ) * 100);
                    $method = null;
                    if (!empty($row->deal_id)) {
                        $tp = $tbankPaymentByDealId->get($row->deal_id);
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
                    $platformFee = $calcFeeCents(
                        $grossCents,
                        (float) ($rule->platform_percent ?? $rule->percent ?? 0.00),
                        (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
                    );

                    $net = $grossCents - $bankAcceptFee - $bankPayoutFee - $platformFee;
                    return round(max(0, (int) $net) / 100, 2);
                })
                ->addColumn('refund_status', function ($row) use ($refundByPaymentId) {
                    $r = $refundByPaymentId->get($row->id);
                    return $r ? (string) $r->status : '';
                })
                ->addColumn('refund_action', function ($row) use ($refundByPaymentId, $robokassaIntentByInvId, $tbankIntentByPaymentId, $payoutByDealId) {
                    $refund = $refundByPaymentId->get($row->id);
                    // Определяем провайдера по полям платежа (как в колонке "Провайдер" и в PaymentRefundController),
                    // а не по наличию intent. Иначе возможна ситуация: платёж T-Bank, но intent не найден → показываем Robokassa.
                    $provider = (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status)) ? 'tbank' : 'robokassa';

                    $robokassaInvIdStr = (is_string($row->payment_number) || is_numeric($row->payment_number)) ? (string) $row->payment_number : '';
                    $robokassaIntent = (ctype_digit($robokassaInvIdStr)) ? $robokassaIntentByInvId->get((int) $robokassaInvIdStr) : null;

                    $tbankPidStr = (is_string($row->payment_id) || is_numeric($row->payment_id)) ? (string) $row->payment_id : '';
                    if ($tbankPidStr === '' || !ctype_digit($tbankPidStr)) {
                        $tbankPidStr = $robokassaInvIdStr; // fallback: иногда PaymentId может лежать в payment_number
                    }
                    $tbankIntent = (ctype_digit($tbankPidStr)) ? $tbankIntentByPaymentId->get((int) $tbankPidStr) : null;

                    $intent = $provider === 'tbank' ? $tbankIntent : $robokassaIntent;

                    $disabled = false;
                    $title = '';

                    if ($refund && in_array((string) $refund->status, ['pending', 'succeeded'], true)) {
                        $disabled = true;
                        $title = (string) $refund->status === 'pending' ? 'Возврат уже в обработке' : 'Платёж уже возвращён';
                    }

                    if (!$disabled) {
                        if (!$intent) {
                            $disabled = true;
                            $title = $provider === 'tbank'
                                ? 'Нет данных T-Bank (intent не найден)'
                                : 'Нет данных Robokassa (intent не найден)';
                        } elseif ((string) $intent->status !== 'paid') {
                            $disabled = true;
                            $title = 'Платёж не в статусе paid';
                        }
                    }

                    // Robokassa: лимит 7 дней (как было)
                    if (!$disabled && $provider === 'robokassa') {
                        $paidAt = $intent->paid_at ? \Carbon\Carbon::parse($intent->paid_at) : null;
                        if (!$paidAt && !empty($row->operation_date)) {
                            $paidAt = \Carbon\Carbon::parse($row->operation_date);
                        }
                        if ($paidAt && $paidAt->copy()->addDays(7)->lt(now())) {
                            $disabled = true;
                            $title = 'Прошло больше 7 дней';
                        }
                    }

                    // T-Bank: запретить возврат, если была выплата партнёру (по deal_id)
                    if (!$disabled && $provider === 'tbank' && !empty($row->deal_id)) {
                        $payout = $payoutByDealId->get($row->deal_id);
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
                        'data-provider="' . htmlspecialchars((string) $provider, ENT_QUOTES) . '"',
                        'data-amount="' . htmlspecialchars((string) $amount, ENT_QUOTES) . '"',
                        'data-user="' . htmlspecialchars((string) ($row->user_name ?? ''), ENT_QUOTES) . '"',
                        'data-month="' . htmlspecialchars((string) ($row->payment_month ?? ''), ENT_QUOTES) . '"',
                    ];

                    if ($disabled) {
                        $btnAttrs[] = 'disabled';
                    }

                    $buttonHtml = '<button ' . implode(' ', $btnAttrs) . '>Возврат</button>';

                    // Важно: disabled-кнопки часто не показывают title при ховере.
                    // Поэтому оборачиваем в <span title="...">, чтобы причина была видна.
                    if ($disabled) {
                        $wrapTitle = $title !== '' ? $title : 'Возврат недоступен';
                        return '<span title="' . htmlspecialchars($wrapTitle, ENT_QUOTES) . '" style="cursor:not-allowed;">' . $buttonHtml . '</span>';
                    }

                    return $buttonHtml;
                })
                ->rawColumns(['refund_action'])
                ->make(true);
        }
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

        // Преобразуем строку в объект DateTime
        try {
            $date = \DateTime::createFromFormat('F Y', $month); // F - имя месяца, Y - год
            if ($date) {
                return $date->format('Y-m-01'); // Всегда возвращаем первое число месяца
            }
            return null; // Возвращаем null, если не удалось преобразовать
        } catch (\Exception $e) {
            Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }
}