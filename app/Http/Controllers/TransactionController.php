<?php

namespace App\Http\Controllers;

//use App\Models\ClientPayment;

use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\UserPeriodPrice;
use App\Services\Payments\PaymentIntentClientContext;
use App\Services\Payments\PaymentService;
use App\Services\Payments\UserPriceMonthlyFeePaymentResolver;
use App\Support\Payments\PaymentOutSumNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
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
            $month = $months[$parts[0]].' '.$parts[1]; // Замена русского месяца на английский
        } else {
            return null; // Если формат не соответствует "Месяц Год", возвращаем null
        }

        // Преобразуем строку в объект DateTime.
        // Формат !F Y: «!» сбрасывает поля, не заданные в строке; иначе PHP подставляет
        // текущий день месяца и для «Февраль» в конце марта получается переполнение в март.
        try {
            $date = \DateTime::createFromFormat('!F Y', $month);
            if ($date) {
                return $date->format('Y-m-01');
            }

            return null; // Возвращаем null, если не удалось преобразовать
        } catch (\Exception $e) {
            Log::error('Ошибка преобразования даты: '.$e->getMessage());

            return null;
        }
    }

    //Станица выбора оплат (Юзер)
    public function index(Request $request, PaymentService $paymentService)
    {
        $this->authorize('paying.classes');

        $paymentDate = (string) $request->input('paymentDate', '');
        $outSum = (string) $request->input('outSum', '');
        $paymentKind = (string) $request->input('payment_kind', '');
        $userPeriodPriceId = $request->filled('abonement_id') ? (int) $request->input('abonement_id') : null;

        $formatedPaymentDate = null;
        $rawFmt = $request->input('formatedPaymentDate');
        if ($request->filled('formatedPaymentDate') && is_string($rawFmt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFmt)) {
            $formatedPaymentDate = $rawFmt;
        } elseif ($paymentDate !== '') {
            $formatedPaymentDate = $this->formatedDate($paymentDate);
        }

        $curPartner = app('current_partner');
        $partnerId = (int) $curPartner->id;

        $user = $request->user();

        // Периодный абонемент (user_period_prices): сумма и связка ТОЛЬКО из БД.
        if ($paymentKind === 'abonement') {
            $upp = null;
            if ($userPeriodPriceId !== null && $userPeriodPriceId > 0) {
                $upp = UserPeriodPrice::query()
                    ->whereKey($userPeriodPriceId)
                    ->where('partner_id', $partnerId)
                    ->where('user_id', (int) $user->id)
                    ->first();
            }
            if (!$upp) {
                abort(404, 'Абонемент не найден');
            }
            if ((bool) $upp->effective_is_paid) {
                abort(422, 'Абонемент уже оплачен');
            }

            $outSum = number_format((float) $upp->amount, 2, '.', '');
            $paymentDate = $paymentDate !== '' ? $paymentDate : 'Абонемент';
            // Важно: не считаем это monthly_fee, чтобы не попасть в ветку users_prices.
            $formatedPaymentDate = null;
        }

        // Месячный абонемент: сумма на экране и в скрытых полях — из users_prices (как при Init оплаты).
        if ($formatedPaymentDate !== null) {
            $resolved = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
                (int) $user->id,
                $partnerId,
                $formatedPaymentDate
            );
            $outSum = $resolved['out_sum'];
            $formatedPaymentDate = $resolved['month_first_day'];
        }

        // Доступность платёжных систем (настройки партнёра + право на способ оплаты)
        $robokassaAvailable = $paymentService->isRobokassaAvailable($curPartner)
            && $user->can('payment.method.robokassa');
        $tbankAvailable = $paymentService->isTbankAvailable($curPartner)
            && $user->can('payment.method.tbankCard');
        // На странице клубного взноса сумма вводится пользователем позже,
        // поэтому не скрываем СБП по amountCents на этапе рендера.
        $tbankSbpAvailable = $paymentService->isTbankAvailable($curPartner)
            && $user->can('payment.method.tbankSBP');

        return view('payment.paymentUser', compact(
            'paymentDate',
            'outSum',
            'formatedPaymentDate',
            'partnerId',
            'robokassaAvailable',
            'tbankAvailable',
            'tbankSbpAvailable',
            'paymentKind',
            'userPeriodPriceId',
        ));
    }

    //Переход со страницы выбора оплат. Формирование ссылки робокасса (Юзер)
    public function pay(Request $request)
    {
        $this->authorize('payment.method.robokassa');

        $user = $request->user();
        $userId = (int) $user->id;
        $userName = (string) ($user->name ?? '');

        $partnerId = (int) app('current_partner')->id;

        $paymentKind = (string) $request->input('payment_kind', '');
        $userPeriodPriceId = $request->filled('abonement_id') ? (int) $request->input('abonement_id') : null;

        $rawFmt = $request->input('formatedPaymentDate');
        $hasMonthly = $request->filled('formatedPaymentDate')
            && is_string($rawFmt)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFmt);

        if ($paymentKind === 'abonement') {
            $upp = null;
            if ($userPeriodPriceId !== null && $userPeriodPriceId > 0) {
                $upp = UserPeriodPrice::query()
                    ->whereKey($userPeriodPriceId)
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->first();
            }
            if (!$upp) {
                abort(404, 'Абонемент не найден');
            }
            if ((bool) $upp->effective_is_paid) {
                abort(422, 'Абонемент уже оплачен');
            }

            $outSum = number_format((float) $upp->amount, 2, '.', '');
            $paymentDate = (string) $request->input('paymentDate', 'Абонемент');
            $hasMonthly = false;
        } elseif ($hasMonthly) {
            $resolved = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
                $userId,
                $partnerId,
                $rawFmt
            );
            $outSum = $resolved['out_sum'];
            $paymentDate = $resolved['month_first_day'];
        } else {
            $outSumRaw = (string) $request->input('outSum', '');
            $outSum = PaymentOutSumNormalizer::normalize($outSumRaw);
            if ($outSum === null) {
                Log::warning('Robokassa pay: invalid OutSum', ['outSum' => $outSumRaw, 'user_id' => $userId]);
                abort(422, 'Некорректная сумма');
            }
            $paymentDate = 'Клубный взнос';
            if (! $request->has('formatedPaymentDate')) {
                Log::warning('formatedPaymentDate отсутствует в запросе');
            }
        }

        // Получаем настройки Робокассы из БД
        $paymentSystem = PaymentSystem::where('partner_id', $partnerId)
            ->where('name', 'robokassa')
            ->first();

        if (! $paymentSystem || ! $paymentSystem->is_connected) {
            Log::error('Попытка оплаты, но Робокасса не подключена или не настроена');
            abort(500, 'Платёжная система не подключена');
        }

        $settings = $paymentSystem->settings;
        $mrhLogin = $settings['merchant_login'] ?? null;
        $mrhPass1 = $settings['password1'] ?? null;

        if (! $mrhLogin || ! $mrhPass1) {
            Log::error('Отсутствуют обязательные параметры для Робокассы');
            abort(500, 'Ошибка конфигурации платёжной системы');
        }

        // Создаём Payable (доменная "покупка")
        $type = $paymentKind === 'abonement'
            ? 'abonement_fee_period'
            : ($hasMonthly ? 'monthly_fee' : 'club_fee');
        $month = null;
        $payableMeta = [];
        if ($type === 'monthly_fee') {
            // paymentDate уже в формате YYYY-MM-01
            $month = $paymentDate;
            $payableMeta['month'] = $paymentDate;
        } elseif ($type === 'abonement_fee_period') {
            $payableMeta['user_period_price_id'] = $userPeriodPriceId;
        }

        $payable = Payable::create([
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'type' => $type,
            'amount' => $outSum,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $month,
            'meta' => $payableMeta,
        ]);

        // Создаём intent на стороне нашей системы и используем его для сопоставления вебхуков.
        $intent = PaymentIntent::create(array_merge([
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'provider' => 'robokassa',
            'status' => 'pending',
            'out_sum' => $outSum,
            'payment_date' => $paymentDate,
            'meta' => json_encode([
                'user_name' => $userName,
            ], JSON_UNESCAPED_UNICODE),
        ], PaymentIntentClientContext::fromRequest($request)));

        // На всякий случай (защита от future-regressions с fillable)
        if (empty($intent->payable_id)) {
            $intent->payable_id = $payable->id;
            $intent->save();
        }

        // Внешний InvId для Robokassa: используем большой оффсет, чтобы избежать конфликтов с историческими InvId в кабинете Robokassa.
        // Пример: intent#1 -> InvId=1000000001
        $providerInvId = 1000000000 + (int) $intent->id;
        $intent->provider_inv_id = $providerInvId;
        $intent->save();

        $invId = (string) $providerInvId;
        $isTest = ! empty($settings['test_mode']); // для Robokassa IsTest
        $receiptJson = [
            'items' => [
                [
                    'name' => 'оплата услуги по занятию футболом',
                    'quantity' => 1,
                    'sum' => (float) $outSum,
                    'tax' => 'none',
                ],
            ],
        ];
        $receipt = rawurlencode(json_encode($receiptJson, JSON_UNESCAPED_UNICODE));

        $description = $paymentDate === 'Клубный взнос'
            ? 'Оплата клубного взноса'
            : "Пользователь: $userName. Период оплаты: $paymentDate.";

        // Формируем подпись
        $signature = md5("$mrhLogin:$outSum:$invId:$receipt:$mrhPass1:Shp_paymentDate=$paymentDate:Shp_userId=$userId");

        // Формируем URL оплаты
        $paymentUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx?'.http_build_query([
            'MerchantLogin' => $mrhLogin,
            'OutSum' => $outSum,
            'InvId' => $invId,
            'Description' => $description,
            'Shp_paymentDate' => $paymentDate,
            'Shp_userId' => $userId,
            'SignatureValue' => $signature,
            'Receipt' => $receipt,
            'IsTest' => $isTest ? 1 : 0,
        ]);

        return redirect()->to($paymentUrl);
    }

    //    Успешная оплата (для юзеров и партнеров)
    public function success(Request $request)
    {
        return view('payment.success'); // Предполагается, что у вас есть такой вид
    }

    //    Неудачная оплата (для юзеров и партнеров)
    public function fail(Request $request)
    {
        Log::error('Переход на страницу неудачной оплаты', $request->all());

        return view('payment.fail'); // Предполагается, что у вас есть такой вид
    }

    //Страница Клубный взнос
    public function clubFee(Request $request, PaymentService $paymentService)
    {
        $curPartner = app('current_partner');
        $partnerId = app('current_partner')->id;
        $outSum = (string) $request->input('outSum', '');

        $user = $request->user();

        $robokassaAvailable = $paymentService->isRobokassaAvailable($curPartner)
            && $user->can('payment.method.robokassa');
        $tbankAvailable = $paymentService->isTbankAvailable($curPartner)
            && $user->can('payment.method.tbankCard');
        // На странице клубного взноса сумма вводится пользователем после рендера,
        // поэтому отображаем СБП по правам и подключению метода, без проверки суммы.
        $tbankSbpAvailable = $paymentService->isTbankAvailable($curPartner)
            && $user->can('payment.method.tbankSBP');

        return view('payment.clubFee', compact(
            'outSum',
            'partnerId',
            'robokassaAvailable',
            'tbankAvailable',
            'tbankSbpAvailable'
        ));
    }
}
