<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\DB;


class TinkoffPaymentController extends Controller
{
private string $terminalKey = '1734739915640DEMO';
private string $secretKey = '5ufq506vqzpvhgb4';
//
//private string $terminalKey = 'TinkoffBankTest';
//    private string $secretKey = '1234567890';


    public function index()
    {
        return view("tinkoff.form"
        );
    }



    public function init(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'order_id' => 'required|string',
        ]);

        $amountRub = $request->input('amount');
        $amount = (int)($amountRub * 100); // сумма в копейках
        $orderId = $request->input('order_id');
        $description = 'Оплата заказа №' . $orderId;

        // Дополнительные данные (опционально)
        $extraData = [
            'user_id' => $request->input('user_id'),
            'user_name' => $request->input('user_name'),
            'team_title' => $request->input('team_title'),
            'payment_month' => $request->input('payment_month'),
        ];

        // Сбор данных для токена
        $tokenData = [
            'Amount' => $amount,
            'OrderId' => $orderId,
            'TerminalKey' => $this->terminalKey,
            'Description' => $description,
            'Password' => $this->secretKey,
        ];

        $token = $this->generateToken($tokenData);

        $requestArray = [
            'TerminalKey' => $this->terminalKey,
            'Amount' => $amount,
            'OrderId' => $orderId,
            'Description' => $description,
            'SuccessURL' => route('payment.success'),
            'FailURL' => route('payment.fail'),
            'DATA' => $extraData,
            'IsTest' => true,
            'Token' => $token,
        ];

        // Логирование запроса
        \Log::channel('daily')->info('Tinkoff INIT REQUEST', [
//                'url' => 'https://rest-api-test.tinkoff.ru/v2/Init',
                'url' => 'https://securepay.tinkoff.ru/v2/Init',
            'request' => $requestArray,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Отправка запроса
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://rest-api-test.tinkoff.ru/v2/Init', $requestArray);

//        dd($response->json()); // Добавим вот это

        $body = $response->json();

        // Логирование ответа
        \Log::channel('daily')->info('Tinkoff INIT RESPONSE', [
            'status' => $response->status(),
            'body' => $body,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if (!empty($body['PaymentURL'])) {
            TinkoffPayment::create([
                'payment_id' => $body['PaymentId'],
                'order_id' => $orderId,
                'amount' => $amount,
                'response' => $body,
                'status' => 'init',
            ]);

            return redirect()->away($body['PaymentURL']);
        }

        return back()->with('error', 'Ошибка инициализации оплаты: ' . ($body['Message'] ?? 'Неизвестная ошибка'));
    }


    public function success()
    {
        return view('payment.success');
    }

    public function fail()
    {
        return view('payment.fail');
    }


    private function generateToken(array $params): string
    {
        unset($params['Token']); // на всякий случай

        // обязательно добавляем Password вручную
        $params['Password'] = $this->secretKey;

        ksort($params); // сортировка по ключам

        $tokenStr = '';
        foreach ($params as $value) {
            $tokenStr .= (string) $value;
        }

        return hash('sha256', $tokenStr);
    }


    public function callback(Request $request)
    {
        $data = $request->all();
        $payment = TinkoffPayment::where('payment_id', $data['PaymentId'])->first();

        if ($payment) {
            $payment->status = $data['Status'];
            $payment->response = $data;
            $payment->save();

            if ($data['Status'] === 'CONFIRMED') {
                DB::table('payments')->insert([
                    'user_id' => $data['DATA']['user_id'] ?? null,
                    'user_name' => $data['DATA']['user_name'] ?? null,
                    'team_title' => $data['DATA']['team_title'] ?? null,
                    'operation_date' => now(),
                    'payment_month' => $data['DATA']['payment_month'] ?? null,
                    'summ' => $payment->amount / 100,
                    'payment_number' => $payment->order_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response('OK');
    }
}


