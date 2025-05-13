<?php

namespace App\Http\Controllers;

//use App\Models\ClientPayment;
use App\Models\Partner;
use App\Models\PartnerPayment;

use App\Models\Payment;
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
    public function index()
    {
        if ($_POST['paymentDate']) {
            $paymentDate = $_POST['paymentDate'];
            $formatedPaymentDate = $this->formatedDate($paymentDate);

        }
        if ($_POST['outSum']) {
            $outSum = $_POST['outSum'];
        }

        // Дополнительная логика, если необходимо
        return view('payment.paymentUser', compact('paymentDate', 'outSum', 'formatedPaymentDate'));
    }

    //Переход со страницы выбора оплат. Формирование ссылки робокасса (Юзер)
    public function pay2(Request $request)
    {
        $userId = $request->userId;
        $userName = $request->userName;
        $outSum = $request->outSum;

//        if ($request->formatedPaymentDate) {
//            $paymentDate = $request->formatedPaymentDate;
//        } else {
//            $paymentDate = "Клубный взнос";
//        }

        if ($request->has('formatedPaymentDate')) {
            $paymentDate = $request->formatedPaymentDate;
        } else {
            \Log::warning('formatedPaymentDate отсутствует в запросе');
            $paymentDate = "Клубный взнос";
        }

        $invId = "";
        $isTest = 1;
        $receipt = rawurlencode("{\"items\":[{\"name\":\"оплата услуги по занятию футболом\",\"quantity\":1,\"sum\":$outSum,\"tax\":\"none\"}]}");
        if ($paymentDate == "Клубный взнос") {
            $description = "Оплата клубного взноса";
        } else {
            $description = "Пользователь: $userName. Период оплаты: $paymentDate.";
        }
        $mrhLogin = config('robokassa.merchant_login');
        $mrhPass1 = config('robokassa.password1');
        $signature = md5("$mrhLogin:$outSum:$invId:$receipt:$mrhPass1:Shp_paymentDate=$paymentDate:Shp_userId=$userId");
        $receipt = rawurlencode($receipt);
        $paymentUrl = "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin={$mrhLogin}&OutSum={$outSum}&InvId={$invId}&Description={$description}&Shp_paymentDate={$paymentDate}&Shp_userId={$userId}&SignatureValue={$signature}&Receipt=$receipt";

        return redirect()->to($paymentUrl); // Перенаправление пользователя на Robokassa
    }


    public function pay(Request $request)
    {
        $userId = $request->userId;
        $userName = $request->userName;
        $outSum = $request->outSum;

        $paymentDate = $request->has('formatedPaymentDate')
            ? $request->formatedPaymentDate
            : "Клубный взнос";

        if (!$request->has('formatedPaymentDate')) {
            \Log::warning('formatedPaymentDate отсутствует в запросе');
        }

        // Получаем настройки Робокассы из БД
        $paymentSystem = PaymentSystem::where('name', 'robokassa')->first();

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

        $invId = "";
        $isTest = $settings['test_mode'] ?? true; // можно использовать при генерации URL, если нужно
        $receiptJson = [
            'items' => [
                [
                    'name' => 'оплата услуги по занятию футболом',
                    'quantity' => 1,
                    'sum' => $outSum,
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




//    Успешная оплата (для юзеров и партнеров)
    public function success(Request $request)
    {
        \Log::info('Переход на страницу успешной оплаты', $request->all());

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









