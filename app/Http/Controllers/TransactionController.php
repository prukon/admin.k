<?php

namespace App\Http\Controllers;

//use App\Models\ClientPayment;
use App\Models\Partner;
use App\Models\PartnerPayment;

use App\Models\Payment;
use App\Models\PaymentIntent;
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

        // Создаём intent на стороне нашей системы и используем его ID как InvId для Robokassa.
        $intent = PaymentIntent::create([
            'partner_id'   => $partnerId,
            'user_id'      => $userId,
            'provider'     => 'robokassa',
            'status'       => 'pending',
            'out_sum'      => $outSum,
            'payment_date' => $paymentDate,
            'meta'         => json_encode([
                'user_name' => $userName,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $invId = (string) $intent->id;
        $isTest = $settings['test_mode'] ?? true; // можно использовать при генерации URL, если нужно
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
                // 'IsTest'        => $isTest ? 1 : 0 // если надо явно указывать
            ]);

        return redirect()->to($paymentUrl);
    }

    /**
     * Нормализуем сумму для Robokassa.
     * Разрешаем только формат: 123 или 123.4 или 123.45, возвращаем строку с 2 знаками после точки.
     */
    private function normalizeOutSum(string $value): ?string
    {
        $v = trim(str_replace(',', '.', $value));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
            return null;
        }
        // Паддинг до 2 знаков
        if (str_contains($v, '.')) {
            [$a, $b] = explode('.', $v, 2);
            $b = str_pad($b, 2, '0');
            return $a . '.' . substr($b, 0, 2);
        }
        return $v . '.00';
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









