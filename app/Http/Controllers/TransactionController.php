<?php

namespace App\Http\Controllers;

use App\Models\ClientPayment;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use function Illuminate\Http\Client\dump;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use function Termwind\dd;
use YooKassa\Client;



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
        return view('payment.payment', compact('paymentDate', 'outSum', 'formatedPaymentDate'));
//        return view('payment');
    }

    //Переход со страницы выбора оплат. Формирование ссылки (Юзер)
    public function pay(Request $request)
    {
        $userId = $request->userId;
        $userName = $request->userName;
        $outSum = $request->outSum;

        if($request->formatedPaymentDate) {
            $paymentDate = $request->formatedPaymentDate;
        } else {
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

//    Успешная оплата
    public function success(Request $request)
    {
        \Log::info('Robokassa payment successful', $request->all());

        return view('payment.success'); // Предполагается, что у вас есть такой вид
    }

//    Неудачная оплата
    public function fail(Request $request)
    {
        \Log::error('Robokassa payment failed', $request->all());

        return view('payment.fail'); // Предполагается, что у вас есть такой вид
    }

    public function clubFee()
    {
        return view('payment.clubFee');
    }

//    Страница выбора оплат админ
//    public function service(Request $request)
//    {
//        return view('payment.service');
//    }

    public function createPaymentYookassa(Request $request)
    {
        $client = new Client();
        $client->setAuth(config('yookassa.shop_id'), config('yookassa.secret_key'));

        if ($_POST['amount']) {
            $amount = $_POST['amount'];
        }

        if ($_POST['client_id']) {
            $clientId = $_POST['client_id'];
        }



        try {
            // Создаем платеж
            $payment = $client->createPayment([
                'amount' => [
                    'value' => $amount, // Сумма в рублях
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => config('yookassa.success_url'),
                ],
                'capture' => true,
                'description' => 'Оплата заказа №123',
                'receipt' => [
                    'customer' => [
                        'email' => 'test@example.com', // Email покупателя
                    ],
                    'items' => [
                        [
                            'description' => 'Тестовый товар',
                            'quantity' => 1,
                            'amount' => [
                                'value' => $amount, // Сумма
                                'currency' => 'RUB',
                            ],
                            'vat_code' => 1,
                            'payment_mode' => 'full_prepayment',
                            'payment_subject' => 'commodity',
                        ],
                    ],
                ],
            ], uniqid('', true));

            \Log::info('Client ID: ' . $request->input('client_id'));
            \Log::info('User ID: ' . auth()->id());
            \Log::info('Payment ID: ' . $payment->id);
            \Log::info('Amount: ' . $amount);

            // Сохраняем данные платежа в БД
            try {
                ClientPayment::create([
                    'client_id' => $clientId,
                    'user_id' => auth()->id(),
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'payment_status' => 'pending',
                    'payment_date' => Carbon::now(), // Указываем текущую дату и время
                    'payment_method' => 'yookassa', // Укажите метод оплаты, например, 'yookassa'


                ]);
            } catch (\Exception $e) {
                \Log::error('Ошибка при создании записи в ClientPayment: ' . $e->getMessage());
                return back()->withErrors(['message' => 'Ошибка сохранения данных в БД.']);
            }


            // Перенаправляем пользователя на страницу подтверждения оплаты
            return redirect($payment->getConfirmation()->getConfirmationUrl());
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }



}













