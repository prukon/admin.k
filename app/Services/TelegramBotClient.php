<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramBotClient
{
    public function sendMessage(string $chatId, string $text): bool
    {
        $token = config('services.telegram.bot_token');
        if (!$token || $chatId === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text'    => $text,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
