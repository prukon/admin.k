<?php
// app/Services/SmsRuService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsRuService
{
    public const USER_ERROR_GENERIC = 'Не удалось отправить SMS. Попробуйте позже.';

    public const USER_ERROR_OPERATOR_NOT_CONNECTED = 'Не удалось отправить SMS: оператор этого номера не подключён к имени отправителя. Подайте заявку в кабинете sms.ru в разделе «Отправители».';

    public const USER_ERROR_GATEWAY_UNAVAILABLE = 'Не удалось отправить SMS: шлюз sms.ru недоступен. Попробуйте позже.';

    /**
     * Текст для оператора CRM по сырому ответу send() (не true).
     */
    public static function userFacingErrorMessage(string $gatewayResult): string
    {
        $raw = trim($gatewayResult);
        if ($raw === '' || $raw === 'Empty api_id' || $raw === 'Empty phone') {
            return self::USER_ERROR_GENERIC;
        }

        if (str_starts_with($raw, 'HTTP exception') || str_starts_with($raw, 'HTTP error')) {
            return self::USER_ERROR_GATEWAY_UNAVAILABLE;
        }

        if (preg_match('/\[204\]/', $raw) === 1
            || str_contains($raw, 'не подключили данного оператора')) {
            return self::USER_ERROR_OPERATOR_NOT_CONNECTED;
        }

        $text = preg_replace('/^(SMS error|API error):\s*/u', '', $raw) ?? $raw;
        $text = preg_replace('/\s*\[\d+\]\s*$/', '', $text) ?? $text;
        $text = trim(str_replace('*', '', $text));

        if ($text === '' || $text === 'Unknown error' || $text === 'API error' || $text === 'SMS error') {
            return self::USER_ERROR_GENERIC;
        }

        if (str_starts_with($text, 'Не удалось отправить SMS')) {
            return $text;
        }

        return 'Не удалось отправить SMS: '.$text;
    }

    /**
     * Отправка SMS через sms.ru
     * Возвращает true при успехе, либо строку-описание ошибки.
     */
    public function send(string $phone, string $message)
    {
        $apiId = config('services.sms_ru.api_id');
        $from  = config('services.sms_ru.from');

        // Предварительные проверки и логи
        if (empty($apiId)) {
            Log::error('SmsRuService: пустой api_id (проверьте .env SMSRU_API_KEY и кеш конфига).');
            return 'Empty api_id';
        }
        if (empty($phone)) {
            Log::error('SmsRuService: пустой телефон.');
            return 'Empty phone';
        }

        // Подготовка запроса
        $payload = [
            'api_id' => $apiId,
            'to'     => $phone,
            'msg'    => $message,
            'json'   => 1,
        ];
        if (!empty($from)) {
            $payload['from'] = $from;
        }

        // Логи запроса (без ключа)
        Log::info('SmsRuService: отправка SMS', [
            'to' => $this->maskPhone($phone),
            'msg_len' => mb_strlen($message),
            'from' => $from ?? null,
            'endpoint' => 'https://sms.ru/sms/send',
        ]);

        try {
            $response = Http::timeout(15)->asForm()->post('https://sms.ru/sms/send', $payload);
        } catch (\Throwable $e) {
            Log::error('SmsRuService: HTTP-исключение при отправке SMS', [
                'to' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);
            return 'HTTP exception: '.$e->getMessage();
        }

        $status  = $response->status();
        $body    = $response->body();

        Log::info('SmsRuService: ответ sms.ru', [
            'http_status' => $status,
            'body'        => $body,
        ]);

        if (!$response->ok()) {
            return "HTTP error: {$status}";
        }

        $json = $response->json();

        // Разбор ответа sms.ru:
        // Обычно есть общий статус 'OK'/'ERROR', а также детальный статус по каждому номеру: $json['sms'][$phone]['status']
        $global = $json['status'] ?? null;
        if ($global !== 'OK') {
            $text = $json['status_text'] ?? 'Unknown error';
            $code = $json['status_code'] ?? null;

            return $this->encodeFailure('API error', (string) $text, is_numeric($code) ? (int) $code : null);
        }

        // Если вернулась детализация по номеру
        if (isset($json['sms']) && is_array($json['sms'])) {
            $perNumber = $json['sms'][$phone] ?? null;
            if (is_array($perNumber)) {
                $numStatus = $perNumber['status'] ?? null; // 'OK' / 'ERROR'
                $numText   = $perNumber['status_text'] ?? '';
                $numCode   = $perNumber['status_code'] ?? null;
                // Можно сохранить sms_id: $perNumber['sms_id'] ?? null;
                if ($numStatus !== 'OK') {
                    return $this->encodeFailure(
                        'SMS error',
                        (string) $numText,
                        is_numeric($numCode) ? (int) $numCode : null
                    );
                }
            }
        }

        return true;
    }

    private function encodeFailure(string $kind, string $text, ?int $code): string
    {
        $suffix = $code !== null ? " [{$code}]" : '';

        return $kind.': '.$text.$suffix;
    }

    private function maskPhone(?string $phone): string
    {
        if (!$phone) return 'null';
        $d = preg_replace('/\D+/', '', $phone);
        $tail = mb_substr($d, -4);
        return '***'.$tail;
    }
}
