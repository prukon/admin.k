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

    //Станица выбора оплат
    public function index()
    {
        $paymentDate = $_POST['paymentDate'];
        $outSum = $_POST['outSum'];

        // Дополнительная логика, если необходимо
        return view('payment', compact('paymentDate', 'outSum'));
    }

    //Формирование ссылки
    public function pay(Request $request)
    {
        $userId = $request->userId;
        $userName = $request->userName;
        $outSum = $request->outSum;
        $paymentDate = $request->paymentDate;
        $invId = "";
        $isTest = 1;
        $receipt = rawurlencode("{\"items\":[{\"name\":\"оплата услуги по занятию футболом\",\"quantity\":1,\"sum\":$outSum,\"tax\":\"none\"}]}");

        $description = "Пользователь: $userName. Период оплаты: $paymentDate.";
        $mrhLogin = config('robokassa.merchant_login');
        $mrhPass1 = config('robokassa.password1');

        $signature = md5("$mrhLogin:$outSum:$invId:$receipt:$mrhPass1:Shp_paymentDate=$paymentDate:Shp_userId=$userId");
//        $signature = md5("$mrhLogin:$outSum:$invId:$mrhPass1");

        $receipt = rawurlencode($receipt);
        $paymentUrl = "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin={$mrhLogin}&OutSum={$outSum}&InvId={$invId}&Description={$description}&Shp_paymentDate={$paymentDate}&Shp_userId={$userId}&SignatureValue={$signature}&Receipt=$receipt&IsTest={$isTest}";
//        $paymentUrl = "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin={$mrhLogin}&OutSum={$outSum}&InvId={$invId}&Description={$description}&SignatureValue={$signature}&IsTest={$isTest}";

        return redirect()->to($paymentUrl); // Перенаправление пользователя на Robokassa
    }

    public function success(Request $request)
    {
        \Log::info('Robokassa payment successful', $request->all());

        return view('payment.success'); // Предполагается, что у вас есть такой вид
    }

    public function fail(Request $request)
    {
        \Log::error('Robokassa payment failed', $request->all());

        return view('payment.fail'); // Предполагается, что у вас есть такой вид
    }

}













