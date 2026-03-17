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
        // Token-based protection for incoming webhooks (recommended).
        // If not set, it falls back to PODPISLON_WEBHOOK_SECRET for backwards compatibility.
        'webhook_token'  => env('PODPISLON_WEBHOOK_TOKEN', env('PODPISLON_WEBHOOK_SECRET')),
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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORG_ID'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],




    'cloudkassir' => [
        'base_url' => env('CLOUDKASSIR_BASE_URL', 'https://api.cloudpayments.ru'),
        'public_id' => env('CLOUDKASSIR_PUBLIC_ID'),
        'api_secret' => env('CLOUDKASSIR_API_SECRET'),
        'timeout' => (int) env('CLOUDKASSIR_TIMEOUT', 30),

        'default_vat' => env('CLOUDKASSIR_DEFAULT_VAT'),
        'default_method' => (int) env('CLOUDKASSIR_DEFAULT_METHOD', 4),
        'default_object' => (int) env('CLOUDKASSIR_DEFAULT_OBJECT', 4),
        'russia_time_zone' => (int) env('CLOUDKASSIR_RUSSIA_TIME_ZONE', 2),

        'agent' => [
            'enabled' => filter_var(env('CLOUDKASSIR_AGENT_ENABLED', true), FILTER_VALIDATE_BOOL),
            'agent_sign' => env('CLOUDKASSIR_AGENT_SIGN', 6),

            'use_purveyor_data' => filter_var(env('CLOUDKASSIR_USE_PURVEYOR_DATA', true), FILTER_VALIDATE_BOOL),
            'use_agent_data' => filter_var(env('CLOUDKASSIR_USE_AGENT_DATA', true), FILTER_VALIDATE_BOOL),

            'operation_name' => env('CLOUDKASSIR_AGENT_OPERATION_NAME'),
            'payment_agent_phone' => env('CLOUDKASSIR_PAYMENT_AGENT_PHONE'),
            'payment_receiver_operator_phone' => env('CLOUDKASSIR_PAYMENT_RECEIVER_OPERATOR_PHONE'),
            'transfer_operator_phone' => env('CLOUDKASSIR_TRANSFER_OPERATOR_PHONE'),
            'transfer_operator_name' => env('CLOUDKASSIR_TRANSFER_OPERATOR_NAME'),
            'transfer_operator_address' => env('CLOUDKASSIR_TRANSFER_OPERATOR_ADDRESS'),
            'transfer_operator_inn' => env('CLOUDKASSIR_TRANSFER_OPERATOR_INN'),
        ],
    ],




];
