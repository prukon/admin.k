<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;


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


    //Станица выбора оплат
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

    //Переход со страницы выбора оплат. Формирование ссылки.
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
    public function service()
    {
        return view('payment.service');
    }
}













