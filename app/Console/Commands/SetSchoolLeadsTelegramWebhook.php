<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetSchoolLeadsTelegramWebhook extends Command
{
    protected $signature = 'school-leads:telegram-webhook {--url=}';

    protected $description = 'Зарегистрировать webhook Telegram для привязки Chat ID партнёров';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN не задан.');

            return self::FAILURE;
        }

        $url = $this->option('url') ?: route('webhooks.telegram.school-leads', [], true);
        $secret = (string) config('services.telegram.webhook_secret');

        $payload = ['url' => $url];
        if ($secret !== '') {
            $payload['secret_token'] = $secret;
        }

        $response = Http::asForm()->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        if (!$response->successful() || !($response->json('ok') ?? false)) {
            $this->error('setWebhook failed: ' . $response->body());

            return self::FAILURE;
        }

        $this->info('Webhook установлен: ' . $url);
        if ($secret !== '') {
            $this->info('Secret token: задан (TELEGRAM_WEBHOOK_SECRET)');
        }

        return self::SUCCESS;
    }
}
