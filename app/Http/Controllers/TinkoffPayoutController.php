<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Payment;

class TinkoffPayoutController extends Controller
{
    public function payout($paymentId)
    {
        // Настройки (временно — потом заменишь)
        $terminalKey = 'TerminalKeyTest123';
        $password = 'SecretPassword123';

        // Ищем запись платежа
        $payment = Payment::findOrFail($paymentId);

        // Формируем данные
        $orderId = 'payout_' . Str::uuid();
        $amount = (int) round($payment->summ * 100); // в копейках
        $dealId = 'deal_' . $payment->id;

        $payload = [
            'TerminalKey' => $terminalKey,
            'OrderId'     => $orderId,
            'Amount'      => $amount,
            'PartnerId'   => (string)$payment->partner_id,
            'DealId'      => $dealId,
            'FinalPayout' => true,
        ];

        // Генерация Token
        $token = $this->generateToken($payload, $password);
        $payload['Token'] = $token;

        // Отправка запроса
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://securepay.tinkoff.ru/e2c/v2/Init/', $payload);

        $responseData = $response->json();

        // Обновляем платеж
        $payment->update([
            'deal_id'        => $dealId,
            'payment_id'     => $responseData['PaymentId'] ?? null,
            'payment_status' => $responseData['Status'] ?? 'UNKNOWN',
        ]);

        return response()->json([
            'sent'     => $payload,
            'response' => $responseData,
        ]);
    }

    protected function generateToken(array $params, string $password): string
    {
        ksort($params);
        $params['Password'] = $password;

        $tokenStr = '';
        foreach ($params as $value) {
            $tokenStr .= $value;
        }

        return hash('sha256', $tokenStr);
    }
}
