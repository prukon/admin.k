<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;


class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
    }

    /**
     * Генерирует подпись для запроса на оплату в Robokassa.
     *
     * @param float $outSum Сумма заказа
     * @param int $invId Идентификатор заказа
     * @param string $password Пароль #1 или #2 в зависимости от типа запроса
     * @param bool $isTest Тестовый режим
     * @return string
     */


//Генерация подписи
    public function generateSignature($outSum, $invId, $password, $isTest = false)
    {
        $signature = md5("{$outSum}:{$invId}:{$password}" . ($isTest ? ":1" : ""));
        return $signature;
    }

//Формирование запроса на оплату
    function getPaymentUrl($outSum, $invId, $description, $isTest = false)
    {
        $login = config('robokassa.merchant_login');
        $password = config('robokassa.password1');
        $signature = generateSignature($outSum, $invId, $password, $isTest);
        $test = $isTest ? '1' : '0';
        $culture = 'ru'; // Язык интерфейса: 'ru' или 'en'

        return "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin={$login}&OutSum={$outSum}&InvoiceID={$invId}&Description={$description}&SignatureValue={$signature}&IsTest={$test}&Culture={$culture}";
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

    public function success()
    {
        // Логика для обработки успешной оплаты
        return view('payment.success'); // Предполагается, что у вас есть такой вид
    }

    public function fail()
    {
        // Логика для обработки неудачной оплаты
        return view('payment.fail'); // Предполагается, что у вас есть такой вид
    }

}













