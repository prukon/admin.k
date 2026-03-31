<?php

namespace App\Http\Controllers;

use App\Models\PartnerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
//use App\Models\ClientPayment;
use App\Models\PartnerPayment;


class WebhookController extends Controller
{
//Разрешенные IP адреса
    private $allowedIps = [
        '185.71.76.0/27',
        '185.71.77.0/27',
        '77.75.153.0/25',
        '77.75.156.11',
        '77.75.156.35',
        '77.75.154.128/25',
        '2a02:5180::/32', // IPv6
    ];

//      Обработка вебхуков
    public function handleWebhook(Request $request)
    {
        $clientIp = $request->ip();

        // Проверяем IP-адрес клиента
        if (!$this->isAllowedIp($clientIp)) {
            Log::warning('Webhook request from unauthorized IP:', ['ip' => $clientIp]);
            return response()->json(['error' => 'Unauthorized IP address.'], 403);
        }

        // Логируем входящие данные
        Log::info('Webhook received:', ['payload' => $request->all()]);

        // Проверка входящих данных
        $request->validate([
            'event' => 'required|string',
            'object.id' => 'required|string',
        ]);

        if ($request->input('event') === 'payment.succeeded') {
            $payment = $request->input('object');
            $paymentId = $payment['id'];

            try {
                \DB::transaction(function () use ($paymentId) {
                    $partnerPayment = PartnerPayment::where('payment_id', $paymentId)->first();
                    if (!$partnerPayment) {
                        throw new \Exception("Платёж с ID {$paymentId} не найден в базе.");
                    }

                    $partnerPayment->update(['payment_status' => 'succeeded']);
                    Log::info("Платёж успешно завершён: Payment ID {$paymentId}");

                    $partnerAccesses = PartnerAccess::where('partner_payment_id', $partnerPayment->id)->first();
                    if (!$partnerAccesses) {
                        throw new \Exception("Период с partnerPaymentId {$partnerPayment->id} не найден в базе.");
                    }

                    $partnerAccesses->update(['is_active' => 1]);
                    Log::info("Период успешно активирован: partnerPaymentId: {$partnerPayment->id}");
                });
            } catch (\Exception $e) {
                Log::error("Ошибка при обработке вебхука: " . $e->getMessage());
                return response()->json(['error' => 'Internal server error.'], 500);
            }
        }

        return response()->json(['message' => 'Webhook processed successfully.'], 200);
    }

//   Проверяет, разрешён ли IP-адрес клиента.
    private function isAllowedIp($ip)
    {
        foreach ($this->allowedIps as $allowedIp) {
            if ($this->ipInRange($ip, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

//     Проверяет, входит ли IP в диапазон.
    private function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            // Если это одиночный IP
            return $ip === $range;
        }

        // Если это диапазон CIDR
        [$subnet, $bits] = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (ip2long($ip) & ~((1 << (32 - $bits)) - 1)) === ip2long($subnet);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, $bits);
        }

        return false;
    }

//    Проверяет, входит ли IPv6-адрес в диапазон.
    private function ipv6InRange($ip, $subnet, $bits)
    {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        $mask = str_repeat('f', $bits >> 2);
        switch ($bits % 4) {
            case 1:
                $mask .= '8';
                break;
            case 2:
                $mask .= 'c';
                break;
            case 3:
                $mask .= 'e';
                break;
        }
        $mask = str_pad($mask, 32, '0');
        $maskBin = pack('H*', $mask);

        return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
    }
}
