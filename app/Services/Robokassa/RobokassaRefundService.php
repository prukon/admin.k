<?php

namespace App\Services\Robokassa;

use App\Models\PaymentSystem;
use Illuminate\Support\Facades\Http;

class RobokassaRefundService
{
    /**
     * OpKey нужен для Refund API. Его можно получить через OpStateExt по InvoiceID (InvId),
     * подписывая запрос Password2: MerchantLogin:InvoiceID:Password2.
     */
    public function fetchOpKey(PaymentSystem $ps, int $invoiceId): string
    {
        $settings = $ps->settings;
        $login = (string) ($settings['merchant_login'] ?? '');
        $password2 = (string) ($settings['password2'] ?? '');

        if ($login === '' || $password2 === '') {
            throw new \RuntimeException('Robokassa settings missing merchant_login/password2');
        }

        $signature = md5($login . ':' . $invoiceId . ':' . $password2);

        $url = 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/OpStateExt';
        $resp = Http::timeout(20)->get($url, [
            'MerchantLogin' => $login,
            'InvoiceID' => $invoiceId,
            'Signature' => $signature,
        ]);

        if (!$resp->ok()) {
            throw new \RuntimeException('Robokassa OpStateExt HTTP ' . $resp->status());
        }

        $xml = (string) $resp->body();
        $sx = @simplexml_load_string($xml);
        if (!$sx) {
            throw new \RuntimeException('Robokassa OpStateExt invalid XML');
        }

        $codes = $sx->xpath('//*[local-name()="Code"]');
        $code = isset($codes[0]) ? (string) $codes[0] : null;
        if ($code !== '0') {
            throw new \RuntimeException('Robokassa OpStateExt Code=' . ($code ?? 'null'));
        }

        $opKeys = $sx->xpath('//*[local-name()="OpKey"]');
        $opKey = isset($opKeys[0]) ? trim((string) $opKeys[0]) : '';
        if ($opKey === '') {
            throw new \RuntimeException('Robokassa OpStateExt OpKey not found');
        }

        return $opKey;
    }

    /**
     * Инициировать полный возврат. Возвращает распарсенный ответ.
     *
     * По документации тело запроса: JWT-строка, передаваемая в теле HTTP POST
     * и обрамлённая кавычками, т.е. JSON string.
     *
     * Подпись: HS256 (HMAC-SHA256) на Password3.
     */
    public function createFullRefund(PaymentSystem $ps, string $opKey, float $amount): array
    {
        $settings = $ps->settings;
        $password3 = (string) ($settings['password3'] ?? '');
        if ($password3 === '') {
            throw new \RuntimeException('Robokassa refund API requires password3');
        }

        $payload = [
            'OpKey' => $opKey,
            // RefundSum НЕ указываем — полный возврат
            'InvoiceItems' => [
                [
                    'Name' => 'Возврат платежа',
                    'Quantity' => 1,
                    'Cost' => (float) $amount,
                    'Tax' => 'none',
                    'PaymentMethod' => 'full_payment',
                    'PaymentObject' => 'payment',
                ],
            ],
        ];

        $jwt = $this->jwtHs256($payload, $password3);

        $url = 'https://services.robokassa.ru/RefundService/Refund/Create';
        $resp = Http::timeout(25)
            ->withBody(json_encode($jwt, JSON_UNESCAPED_UNICODE), 'application/json')
            ->post($url);

        if (!$resp->ok()) {
            return [
                'ok' => false,
                'http_status' => $resp->status(),
                'body' => $resp->body(),
            ];
        }

        $data = $resp->json();
        if (!is_array($data)) {
            return [
                'ok' => false,
                'http_status' => $resp->status(),
                'body' => $resp->body(),
            ];
        }

        return [
            'ok' => (bool) ($data['success'] ?? false),
            'message' => $data['message'] ?? null,
            'requestId' => $data['requestId'] ?? null,
            'raw' => $data,
        ];
    }

    public function getRefundState(string $requestId): array
    {
        $url = 'https://services.robokassa.ru/RefundService/Refund/GetState';
        $resp = Http::timeout(20)->get($url, ['id' => $requestId]);

        if (!$resp->ok()) {
            return [
                'ok' => false,
                'http_status' => $resp->status(),
                'body' => $resp->body(),
            ];
        }

        $data = $resp->json();
        if (!is_array($data)) {
            return [
                'ok' => false,
                'http_status' => $resp->status(),
                'body' => $resp->body(),
            ];
        }

        return [
            'ok' => true,
            'label' => $data['label'] ?? null,
            'amount' => $data['amount'] ?? null,
            'requestId' => $data['requestId'] ?? null,
            'raw' => $data,
        ];
    }

    private function jwtHs256(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $h = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $p = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));

        $sig = hash_hmac('sha256', $h . '.' . $p, $secret, true);
        $s = $this->base64UrlEncode($sig);

        return $h . '.' . $p . '.' . $s;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

