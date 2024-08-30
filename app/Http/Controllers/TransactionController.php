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
//    public function __construct()
//    {
//        $this->middleware('auth');
//    }

    //Станица выбора оплат
    public function index()
    {
        $paymentDate = $_POST['paymentDate'];
        $outSum = $_POST['outSum'];

        // Дополнительная логика, если необходимо
        return view('payment', compact('paymentDate', 'outSum'));
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

    public function result(Request $request)
    {
        \Log::info('Request data:', $request->all());
//      \Log::info('Generated signature:', ['signature' => $signature, 'received' => $request->input('SignatureValue')]);

        // Проверка подписи и обработка данных от Robokassa
        $password2 = config('robokassa.password2');
        $Shp_paymentDate = strtoupper($request->query("Shp_paymentDate"));
        $Shp_userId = strtoupper($request->query("Shp_userId"));


        $outSum = $request->query("OutSum");
//        UserPrice::updateOrCreate(['id' => 2,], ['month' => $outSum]);
        $invId = $request->query('InvId');
//        UserPrice::updateOrCreate(['id' => 3,], ['month' => $invId]);
        $signature = strtoupper($request->query("SignatureValue"));
//        UserPrice::updateOrCreate(['id' => 4,], ['month' => $signature]);

        $receipt = $request->input("Receipt");
        $paymentDate = $request->input("Shp_paymentDate");
        $userId = $request->input("Shp_userId");

        $mySignature = strtoupper(md5("$outSum:$invId:$password2:Shp_paymentDate=$paymentDate:Shp_userId=$userId"));
//        $mySignature = strtoupper(md5("$outSum:$invId:$password2"));
        UserPrice::updateOrCreate(['id' => 5,], ['month' => $mySignature]);

        // проверка корректности подписи
// check signature
        if ($signature != $mySignature) {
            echo "bad sign\n";
            exit();
        }

// success
        echo "OK$invId\n";
        UserPrice::updateOrCreate([
            'user_id' => $Shp_userId,
            'month' => $paymentDate],
            [
                'is_paid' => 1
            ]);
        $user =  User::where('id', $Shp_userId)->first();
        $teamName = Team::where('id', $user->team_id)->first()->title;
        $currentDateTime = date('Y-m-d H:i:s');

        Payment::create([
            'user_id' => $Shp_userId,
            'user_name' => $user->name,
            'team_title' => $teamName,
            'operation_date' => $currentDateTime,
            'payment_month' => $paymentDate,
            'summ' => $outSum,
            'payment_number' => $invId,
        ]);
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













