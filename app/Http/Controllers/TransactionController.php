<?php

namespace App\Http\Controllers;

//use App\Models\ClientPayment;
use App\Models\Partner;
use App\Models\PartnerPayment;

use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Payable;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use function Illuminate\Http\Client\dump;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use function Termwind\dd;

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
            \Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }

    //Станица выбора оплат (Юзер)
    public function index(Request $request)
    {
        $paymentDate = (string) $request->input('paymentDate', '');
        $outSum = (string) $request->input('outSum', '');
        $formatedPaymentDate = $paymentDate !== '' ? $this->formatedDate($paymentDate) : null;

        $partnerId = app('current_partner')->id;

        // Дополнительная логика, если необходимо
        return view('payment.paymentUser', compact(
            'paymentDate',
            'outSum',
            'formatedPaymentDate',
            'partnerId'
        ));
    }

    //Переход со страницы выбора оплат. Формирование ссылки робокасса (Юзер)
    public function pay(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $userName = (string) ($user->name ?? '');

        $outSumRaw = (string) $request->input('outSum', '');
        $outSum = $this->normalizeOutSum($outSumRaw);
        if ($outSum === null) {
            \Log::warning('Robokassa pay: invalid OutSum', ['outSum' => $outSumRaw, 'user_id' => $userId]);
            abort(422, 'Некорректная сумма');
        }

        $paymentDate = $request->filled('formatedPaymentDate')
            ? (string) $request->input('formatedPaymentDate')
            : 'Клубный взнос';

        if (!$request->has('formatedPaymentDate')) {
            \Log::warning('formatedPaymentDate отсутствует в запросе');
        }

        $partnerId = app('current_partner')->id;

        // Получаем настройки Робокассы из БД
        $paymentSystem = PaymentSystem::where('partner_id', $partnerId)
            ->where('name', 'robokassa')
            ->first();

        if (!$paymentSystem || !$paymentSystem->is_connected) {
            \Log::error('Попытка оплаты, но Робокасса не подключена или не настроена');
            abort(500, 'Платёжная система не подключена');
        }

        $settings = $paymentSystem->settings;
        $mrhLogin = $settings['merchant_login'] ?? null;
        $mrhPass1 = $settings['password1'] ?? null;

        if (!$mrhLogin || !$mrhPass1) {
            \Log::error('Отсутствуют обязательные параметры для Робокассы');
            abort(500, 'Ошибка конфигурации платёжной системы');
        }

        // Создаём Payable (доменная "покупка")
        $type = $request->filled('formatedPaymentDate') ? 'monthly_fee' : 'club_fee';
        $month = null;
        $payableMeta = [];
        if ($type === 'monthly_fee') {
            // paymentDate уже в формате YYYY-MM-01
            $month = $paymentDate;
            $payableMeta['month'] = $paymentDate;
        }

        $payable = Payable::create([
            'partner_id' => $partnerId,
            'user_id'    => $userId,
            'type'       => $type,
            'amount'     => $outSum,
            'currency'   => 'RUB',
            'status'     => 'pending',
            'month'      => $month,
            'meta'       => $payableMeta,
        ]);

        // Создаём intent на стороне нашей системы и используем его для сопоставления вебхуков.
        $intent = PaymentIntent::create([
            'partner_id'   => $partnerId,
            'user_id'      => $userId,
            'payable_id'   => $payable->id,
            'provider'     => 'robokassa',
            'status'       => 'pending',
            'out_sum'      => $outSum,
            'payment_date' => $paymentDate,
            'meta'         => json_encode([
                'user_name' => $userName,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        // Внешний InvId для Robokassa: используем большой оффсет, чтобы избежать конфликтов с историческими InvId в кабинете Robokassa.
        // Пример: intent#1 -> InvId=1000000001
        $providerInvId = 1000000000 + (int) $intent->id;
        $intent->provider_inv_id = $providerInvId;
        $intent->save();

        $invId = (string) $providerInvId;
        $isTest = !empty($settings['test_mode']); // для Robokassa IsTest
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

        $description = $paymentDate === "Клубный взнос"
            ? "Оплата клубного взноса"
            : "Пользователь: $userName. Период оплаты: $paymentDate.";

        // Формируем подпись
        $signature = md5("$mrhLogin:$outSum:$invId:$receipt:$mrhPass1:Shp_paymentDate=$paymentDate:Shp_userId=$userId");

        // Формируем URL оплаты
        $paymentUrl = "https://auth.robokassa.ru/Merchant/Index.aspx?" . http_build_query([
                'MerchantLogin'    => $mrhLogin,
                'OutSum'           => $outSum,
                'InvId'            => $invId,
                'Description'      => $description,
                'Shp_paymentDate'  => $paymentDate,
                'Shp_userId'       => $userId,
                'SignatureValue'   => $signature,
                'Receipt'          => $receipt,
                'IsTest'           => $isTest ? 1 : 0,
            ]);

        return redirect()->to($paymentUrl);
    }

    /**
     * Нормализуем сумму для Robokassa.
     * Принимаем до 6 знаков после точки и округляем до 2.
     */
    private function normalizeOutSum(string $value): ?string
    {
        $v = trim(str_replace(',', '.', $value));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d+(\.\d{1,6})?$/', $v)) {
            return null;
        }

        $a = $v;
        $b = '';
        if (str_contains($v, '.')) {
            [$a, $b] = explode('.', $v, 2);
        }

        $a = ltrim($a, '0');
        if ($a === '') {
            $a = '0';
        }

        $b = str_pad($b, 6, '0');
        $cents = (int) substr($b, 0, 2);
        $third = (int) substr($b, 2, 1);

        if ($third >= 5) {
            $cents++;
            if ($cents >= 100) {
                $cents = 0;
                $a = (string) ((int) $a + 1);
            }
        }

        return $a . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }


//    Успешная оплата (для юзеров и партнеров)
    public function success(Request $request)
    {
        return view('payment.success'); // Предполагается, что у вас есть такой вид
    }

//    Неудачная оплата (для юзеров и партнеров)
    public function fail(Request $request)
    {
        \Log::error('Переход на страницу неудачной оплаты', $request->all());
        return view('payment.fail'); // Предполагается, что у вас есть такой вид
    }

    //Страница Клубный взнос
    public function clubFee()
    {
        return view('payment.clubFee');
    }

}









