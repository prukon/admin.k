<?php
// app/Servises/SmsRuService.php  <-- как у вас сейчас (лучше исправить на Services)

namespace App\Servises;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsRuService
{
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
            return "API error: {$text}";
        }

        // Если вернулась детализация по номеру
        if (isset($json['sms']) && is_array($json['sms'])) {
            $perNumber = $json['sms'][$phone] ?? null;
            if (is_array($perNumber)) {
                $numStatus = $perNumber['status'] ?? null; // 'OK' / 'ERROR'
                $numText   = $perNumber['status_text'] ?? '';
                // Можно сохранить sms_id: $perNumber['sms_id'] ?? null;
                if ($numStatus !== 'OK') {
                    return "SMS error: {$numText}";
                }
            }
        }

        return true;
    }

    private function maskPhone(?string $phone): string
    {
        if (!$phone) return 'null';
        $d = preg_replace('/\D+/', '', $phone);
        $tail = mb_substr($d, -4);
        return '***'.$tail;
    }
}
