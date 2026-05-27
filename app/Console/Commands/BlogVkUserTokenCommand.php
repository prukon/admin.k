<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BlogVkUserTokenCommand extends Command
{
    protected $signature = 'blog:vk-user-token
                            {--client-id= : ID приложения VK ID (client_id)}
                            {--redirect-uri=https://oauth.vk.com/blank.html : Доверенный redirect URL из настроек приложения}';

    protected $description = 'Помогает получить VK_USER_TOKEN для загрузки фото (OAuth VK ID + PKCE)';

    public function handle(): int
    {
        $clientId = (string) ($this->option('client-id') ?: config('services.vk.oauth_client_id', ''));
        if ($clientId === '') {
            $clientId = (string) $this->ask('ID приложения VK ID (client_id)');
        }

        if ($clientId === '') {
            $this->error('Укажите --client-id= или VK_OAUTH_CLIENT_ID в .env');

            return self::FAILURE;
        }

        $redirectUri = (string) $this->option('redirect-uri');
        $state = Str::random(32);
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        $authUrl = 'https://id.vk.com/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'photos wall groups offline',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        $this->newLine();
        $this->line('1) Создайте приложение Web в кабинете VK ID: https://id.vk.com/about/business/go');
        $this->line('   При создании укажите «Доверенный redirect URL»: ' . $redirectUri);
        $this->line('   Базовый домен: oauth.vk.com');
        $this->newLine();
        $this->line('2) Откройте ссылку в браузере (под аккаунтом редактора группы kidscrm):');
        $this->newLine();
        $this->line($authUrl);
        $this->newLine();
        $this->line('3) После «Разрешить» скопируйте весь URL из адресной строки и вставьте сюда.');
        $this->warn('   Не копируйте URL на чужие сайты — только для своего .env.');

        $redirectUrl = trim((string) $this->ask('URL после редиректа'));
        if ($redirectUrl === '') {
            $this->error('URL не введён.');

            return self::FAILURE;
        }

        $parsed = parse_url($redirectUrl);
        parse_str($parsed['query'] ?? '', $query);

        $code = (string) ($query['code'] ?? '');
        $deviceId = (string) ($query['device_id'] ?? '');
        $returnedState = (string) ($query['state'] ?? '');

        if ($code === '') {
            $this->error('В URL нет параметра code. Проверьте, что скопировали полный адрес после редиректа.');

            return self::FAILURE;
        }

        if ($returnedState !== '' && $returnedState !== $state) {
            $this->error('state не совпадает — возможно, скопирован чужой URL.');

            return self::FAILURE;
        }

        $this->line('Обмен code на access_token...');

        $response = Http::asForm()->timeout(30)->post('https://id.vk.com/oauth2/auth', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'device_id' => $deviceId,
        ]);

        if (!$response->successful()) {
            $this->error('HTTP ' . $response->status() . ': ' . $response->body());

            return self::FAILURE;
        }

        $body = $response->json();
        if (!is_array($body)) {
            $this->error('Некорректный ответ VK ID.');

            return self::FAILURE;
        }

        if (isset($body['error'])) {
            $this->error('VK ID: ' . ($body['error_description'] ?? $body['error'] ?? 'unknown'));

            return self::FAILURE;
        }

        $accessToken = (string) ($body['access_token'] ?? '');
        if ($accessToken === '') {
            $this->error('access_token не получен: ' . json_encode($body, JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Добавьте в .env на сервере:');
        $this->line('VK_USER_TOKEN=' . $accessToken);

        if (!empty($body['refresh_token'])) {
            $this->newLine();
            $this->comment('Refresh token (сохраните отдельно на будущее, если access истечёт):');
            $this->line((string) $body['refresh_token']);
        }

        $this->newLine();
        $this->line('Затем: php artisan config:clear');

        return self::SUCCESS;
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
