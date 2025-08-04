<?php
//
//namespace App\Http\Controllers;
//
//use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Http;
//use Illuminate\Support\Str;
//use App\Models\Payment;
//
//class TinkoffPaymentController extends Controller
//{
//    public function init(Request $request)
//    {
//        $terminalKey = 'TerminalKeyTest123';
//        $password = 'SecretPassword123';
//
//        $orderId = 'pay_' . Str::uuid();
//        $amount = (int) round($request->input('outSum') * 100);
//        $partnerId = (string) $request->input('partnerId');
//        $dealId = 'deal_' . $orderId;
//
//        // Формируем payload
//        $payload = [
//            'TerminalKey'   => $terminalKey,
//            'OrderId'       => $orderId,
//            'Amount'        => $amount,
//            'PartnerId'     => $partnerId,
//            'DealId'        => $dealId,
//            'FinalPayout'   => true,
//            'DATA' => [
//                'Email' => auth()->user()->email ?? null,
//                'Phone' => null,
//                'UserName' => auth()->user()->name,
//            ],
//            'SuccessURL' => route('home'), // заменишь на свою страницу
//            'FailURL'    => route('home'),
//        ];
//
//        $payload['Token'] = $this->generateToken($payload, $password);
//
//        // Запрос к Тинькофф
//        $response = Http::withHeaders([
//            'Content-Type' => 'application/json',
//        ])->post('https://securepay.tinkoff.ru/e2c/v2/Init/', $payload);
//
//        $data = $response->json();
//
//        // Создаём запись платежа
//        Payment::create([
//            'user_id'        => $request->input('userId'),
//            'user_name'      => $request->input('userName'),
//            'payment_month'  => $request->input('paymentDate'),
//            'summ'           => $request->input('outSum'),
//            'partner_id'     => $partnerId,
//            'deal_id'        => $dealId,
//            'payment_id'     => $data['PaymentId'] ?? null,
//            'payment_status' => $data['Status'] ?? 'UNKNOWN',
//            'payment_number' => $orderId,
//        ]);
//
//        if (!empty($data['Success']) && $data['Success'] === true && !empty($data['PaymentURL'])) {
//            return redirect()->away($data['PaymentURL']);
//        }
//
//        return back()->with('error', 'Не удалось запустить оплату: ' . ($data['Message'] ?? ''));
//    }
//
//    protected function generateToken(array $params, string $password): string
//    {
//        ksort($params);
//        $params['Password'] = $password;
//
//        $tokenStr = '';
//        foreach ($params as $key => $value) {
//            if (is_array($value)) continue; // DATA не включаем
//            $tokenStr .= $value;
//        }
//
//        return hash('sha256', $tokenStr);
//    }
//
//    public function callback(Request $request)
//    {
//        // Заглушка. Тинькофф может присылать сюда уведомления об оплате
//        return response('OK');
//    }
//}
