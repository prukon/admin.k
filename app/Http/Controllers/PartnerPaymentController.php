<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\CreatePartnerServicePaymentRequest;
use App\Http\Requests\Partner\CreatePartnerWalletTopupRequest;
use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\PartnerPayment;
use App\Models\PartnerWalletTransaction;
use App\Services\Tinkoff\TbankAcquiringTerminalConfig;
use App\Services\Tinkoff\TinkoffAcquiringPaymentsService;
use App\Support\Money;
use App\Support\PlatformPaymentMethods;


use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
//use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables;

use YooKassa\Client;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class PartnerPaymentController extends AdminBaseController
{

//    Страница Пополнить счет
    public function showRecharge()
    {
        return view('payment.paymentPartner', array_merge([
            'activeTab' => 'recharge',
            'partner' => $this->currentUserPartnerOrFail(),
        ], PlatformPaymentMethods::viewState(auth()->user())));
    }

    //    Страница История платежей
    public function showHistory()
    {
        return view('payment.paymentPartner', array_merge([
            'activeTab' => 'history',
            'partner' => $this->currentUserPartnerOrFail(),
        ], PlatformPaymentMethods::viewState(auth()->user())));
    }

//    Формирование таблицы для Истории платежей
    public function getPaymentsData(Request $request)
    {
        $partner = $this->currentUserPartnerOrFail();

        $query = PartnerPayment::with(['partner', 'user'])
            ->where('partner_payments.partner_id', $partner->id)
            ->leftJoin('partner_accesses', 'partner_payments.id', '=', 'partner_accesses.partner_payment_id')
            ->select(
                'partner_payments.*',
                'partner_accesses.start_date as access_start_date',
                'partner_accesses.end_date as access_end_date'
            );

        return DataTables::of($query)
            ->addColumn('partner_name', function ($payment) {
                return optional($payment->partner)->title ?? 'N/A';
            })
            ->addColumn('user_name', function ($payment) {
                return optional($payment->user)->name ?? 'N/A';
            })
            ->editColumn('amount', function ($payment) {
//                return number_format($payment->amount_cents / 100, 2, ',', ' ') . ' ₽';
                return round(((int) $payment->amount_cents) / 100, 2);

            })

            ->editColumn('payment_method', function ($payment) {
                return $payment->payment_method ?? 'N/A';
            })
            ->editColumn('payment_date', function ($payment) {
                return $payment->payment_date
                    ? \Carbon\Carbon::parse($payment->payment_date)->format('d.m.y H:i')
                    : 'N/A';
            })
            ->addColumn('payment_period', function ($payment) {
                if ($payment->access_start_date && $payment->access_end_date) {
                    $startDate = \Carbon\Carbon::parse($payment->access_start_date)->format('d.m.y'); // Формат с двумя цифрами года
                    $endDate = \Carbon\Carbon::parse($payment->access_end_date)->format('d.m.y');
                    return "$startDate - $endDate";
                }
                return 'N/A';
            })
            ->editColumn('payment_status', function ($payment) {
                $status = match ($payment->payment_status) {
                'succeeded' => 'Успешно',
                'pending' => 'В ожидании',
                'canceled' => 'Отменён',
                default => 'Неизвестно',
            };

            $statusClass = match ($payment->payment_status) {
            'succeeded' => 'badge-success',
                'pending' => 'badge-warning',
                default => 'badge-danger',
            };

            return '<span class="badge ' . $statusClass . '">' . $status . '</span>';
        })
            ->rawColumns(['payment_status']) // Разрешить HTML
            ->make(true);
    }

    //    Формирование платежа абонплаты (T‑Bank СБП или ЮKassa)
    public function createPaymentTinkoffSbp(CreatePartnerServicePaymentRequest $request, TinkoffAcquiringPaymentsService $acquiring)
    {
        $data = $request->validated();

        $partner = $this->currentUserPartnerOrFail();
        $this->guardPartnerAccess((int) $data['partner_id']);

        if (($data['payment_method'] ?? PlatformPaymentMethods::METHOD_TBANK_SBP) === PlatformPaymentMethods::METHOD_YOOKASSA) {
            return $this->createServicePaymentYookassa($data, $partner);
        }

        return $this->createServicePaymentTinkoffSbpInner($data, $partner, $acquiring);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createServicePaymentTinkoffSbpInner(array $data, Partner $partner, TinkoffAcquiringPaymentsService $acquiring)
    {
        if (! TbankAcquiringTerminalConfig::isActive()) {
            throw ValidationException::withMessages([
                'message' => 'Оплата T‑Bank СБП не подключена на платформе.',
            ]);
        }

        $partnerEmail = trim((string) ($partner->email ?? ''));
        if ($partnerEmail === '') {
            throw ValidationException::withMessages([
                'message' => 'У школы не указан email. Он нужен для чека.',
            ]);
        }

        $amount = (float) $data['amount'];
        $partnerId = (int) $partner->id;
        $curUser = auth()->user();

        if (!$curUser) {
            return back()->withErrors(['message' => 'Пользователь не аутентифицирован.']);
        }
        $curUserId = $curUser->id;

        try {
            $partnerPayment = $this->createPendingServicePayment($data, $partner, PlatformPaymentMethods::METHOD_TBANK_SBP);

            $tinkoffPayment = $acquiring->initSbp(
                $partnerId,
                Money::toCentsOrFail($amount),
                [
                    'scope' => TinkoffAcquiringPaymentsService::SCOPE_SERVICE_PAYMENT,
                    'partner_payment_id' => (string) $partnerPayment->id,
                    'partner_id' => (string) $partnerId,
                    'user_id' => (string) $curUserId,
                ],
                url('/partner-payment/success'),
            );

            if (empty($tinkoffPayment->tinkoff_payment_id)) {
                throw new \RuntimeException('Не удалось инициализировать оплату T‑Bank (СБП)');
            }

            $partnerPayment->payment_id = (string) $tinkoffPayment->tinkoff_payment_id;
            $partnerPayment->save();

            return redirect()->route('tinkoff.qr', $tinkoffPayment->tinkoff_payment_id);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Ошибка при создании платежа абонплаты T‑Bank: '.$e->getMessage());

            return back()->withErrors(['message' => 'Ошибка: '.$e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createServicePaymentYookassa(array $data, Partner $partner)
    {
        $amount = (float) $data['amount'];
        $partnerId = (int) $partner->id;
        $description = (string) $data['description'];

        $client = new Client();
        $client->setAuth(config('yookassa.shop_id'), config('yookassa.secret_key'));

        $user = auth()->user();
        if (!$user) {
            return back()->withErrors(['message' => 'Пользователь не аутентифицирован.']);
        }
        $email = $user->email ?: 'test@test.ru';

        try {
            $partnerPayment = $this->createPendingServicePayment($data, $partner, PlatformPaymentMethods::METHOD_YOOKASSA);

            $payment = $client->createPayment([
                'amount' => [
                    'value'    => number_format($amount, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type'       => 'redirect',
                    'return_url' => url('/partner-payment/success'),
                ],
                'capture'     => true,
                'description' => $description,
                'metadata'    => [
                    'partner_payment_id' => $partnerPayment->id,
                    'partner_id'         => $partnerId,
                    'user_id'            => $user->id,
                    'scope'              => 'partner_service_payment',
                ],
                'receipt' => [
                    'customer' => ['email' => $email],
                    'items' => [[
                        'description'     => $description,
                        'quantity'        => 1,
                        'amount'          => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'RUB'],
                        'vat_code'        => 1,
                        'payment_mode'    => 'full_prepayment',
                        'payment_subject' => 'service',
                    ]],
                ],
            ], uniqid('', true));

            $confirmationUrl = $payment->getConfirmation()->getConfirmationUrl();
            if (!$confirmationUrl) {
                throw new \RuntimeException('Не удалось получить confirmation_url');
            }

            $partnerPayment->payment_id = (string) $payment->id;
            $partnerPayment->save();

            return redirect()->away($confirmationUrl);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Ошибка при создании платежа абонплаты ЮKassa: '.$e->getMessage());

            return back()->withErrors(['message' => 'Ошибка: '.$e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createPendingServicePayment(array $data, Partner $partner, string $paymentMethod): PartnerPayment
    {
        $amount = (float) $data['amount'];
        $days = (int) $data['days'];
        $partnerId = (int) $partner->id;
        $description = $data['description'];
        $curUser = auth()->user();
        if (!$curUser) {
            throw ValidationException::withMessages([
                'message' => 'Пользователь не аутентифицирован.',
            ]);
        }
        $curUserId = $curUser->id;

        return DB::transaction(function () use ($partnerId, $curUserId, $amount, $days, $description, $paymentMethod) {
            $latestEndDate = $this->latestActiveAccessEndDateForPartner($partnerId);

            if ($latestEndDate) {
                $activityStartDate = Carbon::parse($latestEndDate)->addDays(1);
            } else {
                $activityStartDate = Partner::where('id', $partnerId)->value('activity_start_date');
            }

            if ($activityStartDate) {
                $activityStartDateParse = Carbon::parse($activityStartDate);
                $endDate = $activityStartDateParse->addDays($days);
            } else {
                throw new \Exception('Не удалось получить дату начала активности партнера.');
            }

            $partnerPayment = PartnerPayment::create([
                'partner_id' => $partnerId,
                'user_id' => $curUserId,
                'payment_id' => 'pending-'.uniqid('', true),
                'amount_cents' => Money::toCentsOrFail($amount),
                'payment_status' => 'pending',
                'payment_date' => Carbon::now(),
                'payment_method' => $paymentMethod,
                'description' => $description,
            ]);

            PartnerAccess::create([
                'partner_payment_id' => $partnerPayment->id,
                'start_date' => $activityStartDate,
                'end_date' => $endDate,
                'is_active' => 0,
            ]);

            return $partnerPayment;
        });
    }


    // ---------- НОВЫЕ МЕТОДЫ ДЛЯ КОШЕЛЬКА ПАРТНЁРА (ДОБАВЛЕНО) ----------

    // Страница кошелька (пополнение + история)
    public function showWallet()
    {
        return view('payment.partnerWallet', array_merge([
            'activeTab' => 'wallet_recharge',
            'partner'   => $this->currentUserPartnerOrFail(),
        ], PlatformPaymentMethods::viewState(auth()->user())));
    }

    // Создать платёж на пополнение кошелька (ЮKassa или T‑Bank СБП)
    public function createWalletTopup(CreatePartnerWalletTopupRequest $request, TinkoffAcquiringPaymentsService $acquiring)
    {
        $data = $request->validated();

        $partner = $this->currentUserPartnerOrFail();
        $this->guardPartnerAccess((int) $data['partner_id']);

        if (($data['payment_method'] ?? PlatformPaymentMethods::METHOD_TBANK_SBP) === PlatformPaymentMethods::METHOD_TBANK_SBP) {
            return $this->createWalletTopupTinkoffSbp($data, $partner, $acquiring);
        }

        return $this->createWalletTopupYookassaInner($data, $partner);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createWalletTopupTinkoffSbp(array $data, Partner $partner, TinkoffAcquiringPaymentsService $acquiring)
    {
        if (! TbankAcquiringTerminalConfig::isActive()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Оплата T‑Bank СБП не подключена на платформе.',
            ]);
        }

        $partnerEmail = trim((string) ($partner->email ?? ''));
        if ($partnerEmail === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'У школы не указан email. Он нужен для чека.',
            ]);
        }

        $partnerId = (int) $partner->id;
        $amount    = (float) $data['amount'];
        $desc      = (isset($data['description']) && is_string($data['description']) && $data['description'] !== '')
            ? $data['description']
            : 'Пополнение баланса KidsCRM';

        $user = auth()->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Не авторизован'], 401);
        }

        $tx = DB::transaction(function () use ($partnerId, $user, $amount, $desc) {
            return PartnerWalletTransaction::create([
                'partner_id' => $partnerId,
                'user_id'    => $user->id,
                'type'       => 'credit',
                'amount_cents' => Money::toCentsOrFail($amount),
                'currency'   => 'RUB',
                'provider'   => 'tinkoff',
                'status'     => 'pending',
                'description'=> $desc,
                'meta'       => null,
            ]);
        });

        try {
            $tinkoffPayment = $acquiring->initSbp(
                $partnerId,
                Money::toCentsOrFail($amount),
                [
                    'scope' => TinkoffAcquiringPaymentsService::SCOPE_WALLET_TOPUP,
                    'wallet_transaction_id' => (string) $tx->id,
                    'partner_id' => (string) $partnerId,
                    'user_id' => (string) $user->id,
                ],
                url('/partner-wallet/success'),
            );

            if (empty($tinkoffPayment->tinkoff_payment_id)) {
                throw new \RuntimeException('Не удалось инициализировать оплату T‑Bank (СБП)');
            }

            $tx->payment_id = (string) $tinkoffPayment->tinkoff_payment_id;
            $tx->save();

            return response()->json([
                'ok' => true,
                'redirect' => route('tinkoff.qr', $tinkoffPayment->tinkoff_payment_id),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Wallet topup T‑Bank createPayment error: '.$e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'Ошибка создания платежа: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createWalletTopupYookassaInner(array $data, Partner $partner)
    {
        $partnerId = (int) $partner->id;
        $amount    = (float) $data['amount'];
        $desc      = (isset($data['description']) && is_string($data['description']) && $data['description'] !== '')
            ? $data['description']
            : 'Пополнение баланса партнёра';

        $client = new Client();
        $client->setAuth(config('yookassa.shop_id'), config('yookassa.secret_key'));

        $user = auth()->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Не авторизован'], 401);
        }
        $email = $user->email ?: 'test@test.ru';

        $tx = DB::transaction(function () use ($partnerId, $user, $amount, $desc) {
            return PartnerWalletTransaction::create([
                'partner_id' => $partnerId,
                'user_id'    => $user->id,
                'type'       => 'credit',
                'amount_cents' => Money::toCentsOrFail($amount),
                'currency'   => 'RUB',
                'provider'   => 'yookassa',
                'status'     => 'pending',
                'description'=> $desc,
                'meta'       => null,
            ]);
        });

        try {
            $payment = $client->createPayment([
                'amount' => [
                    'value'    => number_format($amount, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type'       => 'redirect',
                    'return_url' => url('/partner-wallet/success'),
                ],
                'capture'     => true,
                'description' => $desc,
                'metadata'    => [
                    'wallet_transaction_id' => $tx->id,
                    'partner_id'            => $partnerId,
                    'user_id'               => $user->id,
                    'scope'                 => 'partner_wallet_topup',
                ],
                'receipt' => [
                    'customer' => ['email' => $email],
                    'items' => [[
                        'description'     => $desc,
                        'quantity'        => 1,
                        'amount'          => ['value' => number_format($amount,2,'.',''), 'currency' => 'RUB'],
                        'vat_code'        => 1,
                        'payment_mode'    => 'full_prepayment',
                        'payment_subject' => 'service',
                    ]],
                ],
            ], uniqid('', true));

            $confirmationUrl = $payment->getConfirmation()->getConfirmationUrl();
            if (!$confirmationUrl) {
                throw new \RuntimeException('Не удалось получить confirmation_url');
            }

            DB::transaction(function () use ($tx, $payment) {
                $tx->payment_id = $payment->id;
                $tx->save();
            });

            return response()->json([
                'ok' => true,
                'redirect' => $confirmationUrl,
            ]);

        } catch (\Throwable $e) {
            Log::error('Wallet topup createPayment error: '.$e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'Ошибка создания платежа: '.$e->getMessage(),
            ], 500);
        }
    }

    public function partnerPaymentSuccess()
    {
        return view('payment.partnerWalletSuccess', [
            'message' => 'Платёж обрабатывается. Статус обновится в истории в течение минуты.',
            'backUrl' => url('/partner-payment/history'),
            'backLabel' => 'К истории оплаты сервиса',
        ]);
    }

    // Вебхук от YooKassa — подтверждаем платеж и зачисляем баланс
// Вебхук от YooKassa — подтверждаем платеж и зачисляем баланс
    public function ykWalletWebhook(Request $request)
    {
        // --- 1) Фильтр по IP, как у твоего рабочего вебхука ---
        $clientIp = $request->ip();
        if (!$this->isAllowedIp($clientIp)) {
            Log::warning('YooKassa wallet webhook: unauthorized IP', ['ip' => $clientIp]);
            return response()->json(['error' => 'Unauthorized IP address.'], 403);
        }

        // --- 2) Безопасный разбор payload + логирование для диагностики ---
        // ЙоКасса присылает JSON; Laravel обычно парсит сам, но подстрахуемся
        $payload = $request->json()->all() ?: $request->all();
        Log::info('YooKassa wallet webhook received', ['payload' => $payload, 'ip' => $clientIp]);

        // --- 3) Валидация базовых полей ---
        try {
            $request->validate([
                'event'         => 'required|string',
                'object.id'     => 'required|string',
                'object.amount.value' => 'required',
            ]);
        } catch (\Throwable $e) {
            Log::warning('YooKassa wallet webhook: validation failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Bad payload'], 400);
        }

        try {
            $event     = $payload['event'];
            $object    = $payload['object'];
            $paymentId = $object['id'] ?? null;
            $amountVal = isset($object['amount']['value']) ? (float)$object['amount']['value'] : null;
            $metadata  = $object['metadata'] ?? [];

            $walletTxId = $metadata['wallet_transaction_id'] ?? null;

            if (!$paymentId || !$walletTxId) {
                Log::warning('YooKassa wallet webhook: missing ids', [
                    'payment_id' => $paymentId,
                    'wallet_transaction_id' => $walletTxId
                ]);
                return response()->json(['ok' => false, 'message' => 'No payment_id or wallet_transaction_id'], 400);
            }

            /** @var \App\Models\PartnerWalletTransaction $tx */
            $tx = \App\Models\PartnerWalletTransaction::where('id', $walletTxId)
                ->where('provider', 'yookassa')
                ->first();

            if (!$tx) {
                Log::error('YooKassa wallet webhook: transaction not found', ['wallet_transaction_id' => $walletTxId]);
                return response()->json(['ok' => false, 'message' => 'Transaction not found'], 404);
            }

            // Идемпотентность: если уже финальный статус — просто ок
            if (in_array($tx->status, ['succeeded', 'canceled', 'failed'], true)) {
                Log::info('YooKassa wallet webhook: already finalized', [
                    'wallet_transaction_id' => $tx->id,
                    'status' => $tx->status
                ]);
                return response()->json(['ok' => true]);
            }

            // Необязательная, но полезная проверка соответствия суммы
            // (чтобы не зачислить случайно неправильную)
            $amountValCents = $amountVal !== null ? Money::toCents($amountVal) : null;
            if ($amountValCents !== null && abs((int)$tx->amount_cents - $amountValCents) > 0) {
                Log::warning('YooKassa wallet webhook: amount mismatch', [
                    'wallet_transaction_id' => $tx->id,
                    'tx_amount'  => (int)$tx->amount_cents,
                    'hook_amount'=> $amountValCents,
                ]);
                // Можно вернуть 422, чтобы не зачислять спорную сумму
                return response()->json(['ok' => false, 'message' => 'Amount mismatch'], 422);
            }

            if ($event === 'payment.succeeded') {
                DB::transaction(function () use ($tx, $payload, $paymentId) {
                    // Захватываем партнера для атомарного инкремента
                    $partner = \App\Models\Partner::where('id', $tx->partner_id)->lockForUpdate()->firstOrFail();

                    // Сохраним полезную диагностическую инфу
                    $meta = (array)$tx->meta;
                    $meta['last_webhook'] = $payload;
                    $tx->meta = $meta;

                    // На всякий случай проставим payment_id, если не сохранили ранее
                    if (empty($tx->payment_id)) {
                        $tx->payment_id = $paymentId;
                    }

                    $tx->status = 'succeeded';
                    $tx->save();

                    // Реальное зачисление средств
                    $partner->wallet_balance_cents = (int)$partner->wallet_balance_cents + (int)$tx->amount_cents;
                    $partner->save();
                });

                Log::info('YooKassa wallet webhook: credited', [
                    'wallet_transaction_id' => $tx->id,
                    'partner_id' => $tx->partner_id,
                    'amount' => ((int)$tx->amount_cents) / 100,
                ]);

                return response()->json(['ok' => true]);
            }

            if ($event === 'payment.canceled') {
                DB::transaction(function () use ($tx, $payload, $paymentId) {
                    $meta = (array)$tx->meta;
                    $meta['last_webhook'] = $payload;
                    $tx->meta = $meta;

                    if (empty($tx->payment_id)) {
                        $tx->payment_id = $paymentId;
                    }

                    $tx->status = 'canceled';
                    $tx->save();
                });

                Log::info('YooKassa wallet webhook: canceled', [
                    'wallet_transaction_id' => $tx->id,
                    'payment_id' => $paymentId
                ]);

                return response()->json(['ok' => true]);
            }

            // Прочие события — подтверждаем получение, но без движения денег
            Log::info('YooKassa wallet webhook: event acknowledged', ['event' => $event, 'payment_id' => $paymentId]);
            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            Log::error('YooKassa wallet webhook error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ok' => false, 'message' => 'Server error'], 500);
        }
    }

    // Страница успеха после редиректа (final URL)
    public function ykWalletSuccess(Request $request)
    {
        // Здесь мы НЕ знаем статус — он приходит вебхуком.
        // Просто показываем, что платёж обрабатывается, и подтягиваем баланс с сервера.
        return view('payment.partnerWalletSuccess', [
            'message' => 'Платёж обрабатывается. Статус обновится в истории транзакций в течение минуты.',
        ]);
    }

    // История транзакций кошелька (для DataTables)
    public function getWalletTransactionsData(Request $request)
    {
        $partner = $this->currentUserPartnerOrFail();

        $query = PartnerWalletTransaction::with(['partner','user'])
            ->where('partner_id', $partner->id)
            ->select('partner_wallet_transactions.*');

        return DataTables::of($query)
            ->addColumn('partner_name', fn($t) => optional($t->partner)->title ?? '—')
            ->addColumn('user_name', fn($t) => optional($t->user)->name ?? '—')
            ->editColumn('amount', fn($t) => round(((int) $t->amount_cents) / 100, 2))
            ->editColumn('type', fn($t) => $t->type === 'credit' ? 'Пополнение' : 'Списание')
            ->editColumn('status', function ($t) {
        $label = match ($t->status) {
        'succeeded' => 'Успешно',
                    'pending'   => 'В ожидании',
                    'canceled'  => 'Отменено',
                    'failed'    => 'Ошибка',
                    default     => $t->status,
                };
                $cls = match ($t->status) {
                'succeeded' => 'badge-success',
                    'pending'   => 'badge-warning',
                    default     => 'badge-danger',
                };
                return '<span class="badge '.$cls.'">'.$label.'</span>';
            })
        ->editColumn('created_at', fn($t) => $t->created_at ? $t->created_at->format('d.m.y H:i') : '—')
            ->rawColumns(['status'])
        ->make(true);
    }

    // ----- Служебные методы -----

    private function guardPartnerAccess(int $partnerId): void
    {
        $currentPartnerId = $this->partnerContext->partnerId();
        abort_if(
            $currentPartnerId === null || (int) $partnerId !== (int) $currentPartnerId,
            403,
            'Нет доступа к кошельку этой школы.'
        );
    }

    private function currentUserPartnerOrFail(): Partner
    {
        $partner = $this->partnerContext->partner();
        abort_if($partner === null, 404, 'Партнёр не найден');

        return $partner;
    }

    private function latestActiveAccessEndDateForPartner(int $partnerId): ?string
    {
        $value = PartnerAccess::query()
            ->join('partner_payments', 'partner_accesses.partner_payment_id', '=', 'partner_payments.id')
            ->where('partner_payments.partner_id', $partnerId)
            ->where('partner_accesses.is_active', 1)
            ->whereNull('partner_accesses.deleted_at')
            ->whereNull('partner_payments.deleted_at')
            ->max('partner_accesses.end_date');

        return $value !== null ? (string) $value : null;
    }

    // --- Разрешённые IP YooKassa (как в твоём рабочем примере) ---
private array $allowedIps = [
'185.71.76.0/27',
'185.71.77.0/27',
'77.75.153.0/25',
'77.75.156.11',
'77.75.156.35',
'77.75.154.128/25',
'2a02:5180::/32', // IPv6
];

    private function isAllowedIp(string $ip): bool
    {
        foreach ($this->allowedIps as $allowedIp) {
            if ($this->ipInRange($ip, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (ip2long($ip) & ~((1 << (32 - $bits)) - 1)) === ip2long($subnet);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, (int)$bits);
        }

        return false;
    }

    private function ipv6InRange(string $ip, string $subnet, int $bits): bool
    {
        $ipBin     = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        $mask = str_repeat('f', $bits >> 2);
        switch ($bits % 4) {
            case 1: $mask .= '8'; break;
            case 2: $mask .= 'c'; break;
            case 3: $mask .= 'e'; break;
        }
        $mask = str_pad($mask, 32, '0');
        $maskBin = pack('H*', $mask);

        return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
    }


}
