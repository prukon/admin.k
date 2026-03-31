<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\PartnerPayment;
use App\Models\PartnerWalletTransaction;


use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
//use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables;

use YooKassa\Client;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class PartnerPaymentController extends Controller
{

//    Страница Пополнить счет
    public function showRecharge()
    {
        return view('payment.paymentPartner', ['activeTab' => 'recharge']);
    }

    //    Страница История платежей
    public function showHistory()
    {
        return view('payment.paymentPartner', ['activeTab' => 'history']);
    }

//    Формирование таблицы для Истории платежей
    public function getPaymentsData(Request $request)
    {
        $query = PartnerPayment::with(['partner', 'user'])
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
//                return number_format($payment->amount, 2, ',', ' ') . ' ₽';
                return (float) $payment->amount;

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

    //    Формирование платежа Yookassa
    public function createPaymentYookassa(Request $request)
    {
        // Валидация входных данных
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'days' => 'required|numeric|min:1',
            'partner_id' => 'required|exists:partners,id',
            'description' => 'required',
        ]);

        $client = new Client();
        $client->setAuth(config('yookassa.shop_id'), config('yookassa.secret_key'));

        $amount = $request->input('amount');
        $days = $request->input('days');
        $partnerId = $request->input('partner_id');
        $description = $request->input('description');
        $curUser = auth()->user();

        if (!$curUser) {
            return back()->withErrors(['message' => 'Пользователь не аутентифицирован.']);
        }
        $curUserId = $curUser->id;


        if ($curUser->email){
            $curUserEmail = $curUser->email;
        } else {
            $curUserEmail = "test@test.ru";
        }

        try {
            $payment = $client->createPayment([
                'amount' => [
                    'value' => $amount,
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => config('yookassa.success_url'),
//                    'return_url' => $returnUrl,
                ],
                'capture' => true,
                'description' => $description,
                'receipt' => [
                    'customer' => [
                        'email' => $curUserEmail,
                    ],
                    'items' => [
                        [
                            'description' => $description,
                            'quantity' => 1,
                            'amount' => [
                                'value' => $amount,
                                'currency' => 'RUB',
                            ],
                            'vat_code' => 1,
                            'payment_mode' => 'full_prepayment',
                            'payment_subject' => 'commodity',
                        ],
                    ],
                ],
            ], uniqid('', true));

            $confirmationUrl = $payment->getConfirmation()->getConfirmationUrl();

            if (!$confirmationUrl) {
                return back()->withErrors(['message' => 'Не удалось получить URL подтверждения платежа.']);
            }

            // Используем транзакцию
            \DB::transaction(function () use ($payment, $partnerId, $curUserId, $amount) {
                // Получаем дату начала активности
                $latestEndDate = PartnerAccess::where('is_active', 1)->max('end_date');

                if ($latestEndDate) {
                    $activityStartDate = Carbon::parse($latestEndDate)->addDays(1);
                } else {
                    $activityStartDate = Partner::where('id', $partnerId)->value('activity_start_date');
                }

                // Формируем конечную дату
                if ($activityStartDate) {
                    $activityStartDateParse = Carbon::parse($activityStartDate);
                    $endDate = $activityStartDateParse->addDays(29); // Добавить 30 дней
                } else {
                    throw new \Exception('Не удалось получить дату начала активности партнера.');
                }

                // Создаем запись платежа
                $partnerPayment = PartnerPayment::create([
                    'partner_id' => $partnerId,
                    'user_id' => $curUserId,
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'payment_status' => 'pending',
                    'payment_date' => Carbon::now(),
                    'payment_method' => 'yookassa',
                ]);

                // Создаем доступ с привязкой к записи платежа
                PartnerAccess::create([
                    'partner_payment_id' => $partnerPayment->id, // Используем ID записи платежа
                    'start_date' => $activityStartDate,
                    'end_date' => $endDate,
                    'is_active' => 0,
                ]);
            });

            // Перенаправляем пользователя на страницу подтверждения
            return redirect($confirmationUrl);

        } catch (\Exception $e) {
            \Log::error('Ошибка при создании платежа или записи в базу: ' . $e->getMessage());


            // Попытка отменить платеж через Yookassa, если это возможно
            try {
                if (isset($payment) && $payment->id) {
                    $client->cancelPayment($payment->id);
                }
            } catch (\Exception $cancelException) {
                \Log::error('Ошибка при отмене платежа: ' . $cancelException->getMessage());
            }


            return back()->withErrors(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }


    // ---------- НОВЫЕ МЕТОДЫ ДЛЯ КОШЕЛЬКА ПАРТНЁРА (ДОБАВЛЕНО) ----------

    // Страница кошелька (пополнение + история)
    public function showWallet()
    {
        // Вью можно сделать отдельным (пример ниже в конце)
        return view('payment.partnerWallet', [
            'activeTab' => 'wallet_recharge',
            'partner'   => $this->currentUserPartnerOrFail(),
        ]);
    }

    // Создать платёж YooKassa на пополнение кошелька
    public function createWalletTopupYookassa(Request $request)
    {
        $request->validate([
            'amount'     => 'required|numeric|min:1',
            'partner_id' => 'required|exists:partners,id',
            'description'=> 'nullable|string|max:255',
        ]);

        $partnerId = (int) $request->input('partner_id');
        $amount    = (float) $request->input('amount');
        $desc      = $request->input('description') ?: 'Пополнение баланса партнёра';

        // (опционально) проверка, что пользователь имеет доступ к этому партнёру
        $this->guardPartnerAccess($partnerId);

        $client = new Client();
        $client->setAuth(config('yookassa.shop_id'), config('yookassa.secret_key'));

        $user = auth()->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Не авторизован'], 401);
        }
        $email = $user->email ?: 'test@test.ru';

        // 1) Создаём локальную транзакцию в статусе pending
        $tx = DB::transaction(function () use ($partnerId, $user, $amount, $desc) {
            return PartnerWalletTransaction::create([
                'partner_id' => $partnerId,
                'user_id'    => $user->id,
                'type'       => 'credit',
                'amount'     => $amount,
                'currency'   => 'RUB',
                'provider'   => 'yookassa',
                'status'     => 'pending',
                'description'=> $desc,
                'meta'       => null,
            ]);
        });

        try {
            // 2) Создаём платёж в YooKassa, прокидываем ID транзакции в metadata
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

            // 3) Сохраняем payment_id в локальную транзакцию
            DB::transaction(function () use ($tx, $payment) {
                $tx->payment_id = $payment->id;
                $tx->save();
            });

            // Возвращаем JSON для редиректа (Ajax)
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
            if ($amountVal !== null && abs((float)$tx->amount - (float)$amountVal) > 0.009) {
                Log::warning('YooKassa wallet webhook: amount mismatch', [
                    'wallet_transaction_id' => $tx->id,
                    'tx_amount'  => (float)$tx->amount,
                    'hook_amount'=> (float)$amountVal,
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
                    $partner->wallet_balance = (float)$partner->wallet_balance + (float)$tx->amount;
                    $partner->save();
                });

                Log::info('YooKassa wallet webhook: credited', [
                    'wallet_transaction_id' => $tx->id,
                    'partner_id' => $tx->partner_id,
                    'amount' => (float)$tx->amount,
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
            ->select('*');

        return DataTables::of($query)
            ->addColumn('partner_name', fn($t) => optional($t->partner)->title ?? '—')
            ->addColumn('user_name', fn($t) => optional($t->user)->name ?? '—')
            ->editColumn('amount', fn($t) => (float) $t->amount)
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
        // Если у вас есть логика принадлежности пользователя партнёру — проверьте здесь.
        // Пока пропускаем (или добавьте свою проверку).
    }

    private function currentUserPartnerOrFail(): Partner
    {
        // Верните партнёра, с которым сейчас работает пользователь
        // Ниже — заглушка: либо через профиль пользователя, либо через выбранного партнёра в сессии.
        // Замените на вашу реальную реализацию:
        $partner = Partner::first();
        abort_if(!$partner, 404, 'Партнёр не найден');
        return $partner;
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
