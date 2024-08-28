<?php
namespace App\Http\Controllers;

use App\Models\User;
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
//        $curUser = auth()->user();
//        $userId = User::where('user_id', $curUser->id);
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

        $signature = md5("$mrhLogin:$outSum:$invId:$receipt:$mrhPass1:Shp_paymentDate=$paymentDate:Shp_userId=$userId");
        $receipt = rawurlencode($receipt);
        $paymentUrl =  "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin={$mrhLogin}&OutSum={$outSum}&InvoiceID={$invId}&Description={$description}&Shp_paymentDate={$paymentDate}&Shp_userId={$userId}&SignatureValue={$signature}&Receipt=$receipt&IsTest={$isTest}";

        return redirect()->to($paymentUrl); // Перенаправление пользователя на Robokassa
    }












    public function result(Request $request)
    {
        // Проверка подписи и обработка данных от Robokassa
        // Не забудьте реализовать логику проверки подписи!
        $password2 = config('robokassa.password2');
        $signature = $this->generateSignature($request->input('OutSum'), $request->input('InvId'), $password2);

        if (strtoupper($request->input('SignatureValue')) === $signature) {
            // Оплата подтверждена
            // Обновите статус заказа в базе данных
        } else {
            // Ошибка валидации подписи
            abort(403, 'Invalid signature');
        }
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













