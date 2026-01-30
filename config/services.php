<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |

    config/services.php
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sms_ru' => [
        'api_id' => env('SMSRU_API_KEY'),
        'from'    => env('SMSRU_FROM'),
    ],

    'podpislon' => [
        'base_url'       => env('PODPISLON_BASE_URL', 'https://podpislon.ru/integration'),
        'key'            => env('PODPISLON_API_KEY'),
        'webhook_secret' => env('PODPISLON_WEBHOOK_SECRET'),
        'http_debug'     => env('PODPISLON_HTTP_DEBUG', false),      // для Guzzle on_stats/debug
        'upload_strategy' => env('PODPISLON_UPLOAD_STRATEGY', 'auto'), // auto|multipart|json
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'   => env('TELEGRAM_CHAT_ID'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret'   => env('RECAPTCHA_SECRET_KEY'),
        'min_score' => env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

];
