<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use YooKassa\Client;

class WebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $config = config('yookassa');
        $secretKey = $config['secret_key'];

        // Получение тела запроса
        $payload = $request->getContent();

        // Получение сигнатуры из заголовка
        $signature = $request->header('HTTP_YOOKASSA_SIGNATURE');

        // Проверка сигнатуры
        if (!$this->isValidSignature($payload, $signature, $secretKey)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Расшифровка JSON-данных
        $data = json_decode($payload, true);

        if (isset($data['event']) && isset($data['object'])) {
            switch ($data['event']) {
                case 'payment.succeeded':
                    $this->handlePaymentSucceeded($data['object']);
                    break;

                case 'payment.canceled':
                    $this->handlePaymentCanceled($data['object']);
                    break;

                default:
                    return response()->json(['error' => 'Unhandled event'], 400);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function isValidSignature($payload, $signature, $secretKey)
    {
        $hash = hash_hmac('sha256', $payload, $secretKey);
        return hash_equals($hash, $signature);
    }

    private function handlePaymentSucceeded($payment)
    {
        // Обработка успешного платежа
        // Например, обновление статуса заказа в БД
        $paymentId = $payment['id'];
        $amount = $payment['amount']['value'];

        \Log::info("Payment succeeded: ID {$paymentId}, Amount {$amount}");

        // Ваш код для обработки успешного платежа
    }

    private function handlePaymentCanceled($payment)
    {
        // Обработка отмененного платежа
        $paymentId = $payment['id'];

        \Log::info("Payment canceled: ID {$paymentId}");

        // Ваш код для обработки отмененного платежа
    }
}
