<?php
namespace App\Http\Controllers;

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
        return view('payment', compact( 'paymentDate', 'outSum'));
    }

    //Генерация подписи
//    public function generateSignature($mrhLogin, $outSum, $invId, $mrhPass1)
//    {
//        return $crc = md5("$mrhLogin:$outSum:$invId:$mrhPass1");
//    }

      //Формирование ссылки
    public function pay(Request $request)
    {
        $userId = $request->userId;
        $userName = $request->userName;
        $outSum = $request->outSum;
        $paymentDate = $request->paymentDate;
        $invId = 1;
        $isTest = 1;
        $receipt = rawurlencode("{\"items\":[{\"name\":\"оплата услуги по занятию футболом\",\"quantity\":1,\"sum\":$outSum,\"tax\":\"none\"}]}");

        $description = "Пользователь: $userName. Период оплаты: $paymentDate.";
        $mrhLogin = config('robokassa.merchant_login');
        $mrhPass1 = config('robokassa.password1');

//        $signature = md5("$mrhLogin:$outSum:$invId:$receipt:$mrhPass1:Shp_paymentDate=$paymentDate:Shp_userId=$userId");
        $signature = md5("$mrhLogin:$outSum:$invId:$mrhPass1");

        $receipt = rawurlencode($receipt);
//        $paymentUrl =  "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin={$mrhLogin}&OutSum={$outSum}&InvoiceID={$invId}&Description={$description}&Shp_paymentDate={$paymentDate}&Shp_userId={$userId}&SignatureValue={$signature}&Receipt=$receipt&IsTest={$isTest}";
        $paymentUrl =  "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin={$mrhLogin}&OutSum={$outSum}&InvoiceID={$invId}&Description={$description}&SignatureValue={$signature}&IsTest={$isTest}";

        return redirect()->to($paymentUrl); // Перенаправление пользователя на Robokassa
    }

    public function result(Request $request)
    {
        // Проверка подписи и обработка данных от Robokassa
        // Не забудьте реализовать логику проверки подписи!
        $password2 = config('robokassa.password2');


        $mrhLogin = $request->input("MerchantLogin");
        $outSum = $request->input("OutSum");
        $invId = $request->input("InvoiceID");
        $receipt = $request->input("Description");
        $paymentDate = $request->input("Shp_paymentDate");
        $userId = $request->input("Shp_userId");
        $signature = strtoupper($request->input("SignatureValue"));

//        $mySignature = md5("$outSum:$invId:$password2:Shp_paymentDate=$paymentDate:Shp_userId=$userId");
        $mySignature = strtoupper(md5("$outSum:$invId:$password2"));


        // проверка корректности подписи
// check signature
//        if ($signature != $mySignature)
//        {
//            echo "bad sign\n";
//            exit();
//        }

// признак успешно проведенной операции
// success
//        echo "OK$invId\n";
        UserPrice::updateOrCreate(
            [
                'user_id' => 2,
//                'month' => 'Сентябрь 2024',
            ],
            [
                'is_paid' => 1
            ]
        );
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













