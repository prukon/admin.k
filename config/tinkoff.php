<?php

return [
    'env' => env('APP_ENV', 'production'),

    'payment' => [
        'terminal_key' => env('TINKOFF_TERMINAL_KEY'),
        'password'     => env('TINKOFF_TOKEN_PASSWORD'),
        'success_url'  => env('TINKOFF_PAYMENT_SUCCESS_URL'),
        'fail_url'     => env('TINKOFF_PAYMENT_FAIL_URL'),
        'notify_url'   => env('TINKOFF_PAYMENT_NOTIFICATION_URL'),
        'base_url'     => env('TINKOFF_FORCE_BASE') === 'prod'
            ? 'https://securepay.tinkoff.ru'
            : 'https://rest-api-test.tinkoff.ru',
    ],

    'e2c' => [
        'terminal_key' => env('TINKOFF_E2C_TERMINAL_KEY'),
        'password'     => env('TINKOFF_E2C_TOKEN_PASSWORD'),
        'base_url'     => env('TINKOFF_FORCE_BASE') === 'prod'
            ? 'https://securepay.tinkoff.ru'
            : 'https://rest-api-test.tinkoff.ru',
    ],

    'sm_register' => [
        'base_url'   => env('TINKOFF_FORCE_BASE') === 'prod'
            ? 'https://acqapi.tinkoff.ru'
            : 'https://acqapi-test.tinkoff.ru',
        'oauth_path' => '/oauth/token',
        'mtls' => [
            'cert' => env('TINKOFF_MTLS_CERT'),
            'key'  => env('TINKOFF_MTLS_KEY'),
            'ca'   => env('TINKOFF_MTLS_CA'),
        ],
        'login'    => env('TINKOFF_OAUTH_LOGIN'),
        'password' => env('TINKOFF_OAUTH_PASSWORD'),
    ],


    // Тарифы для UI (можно править в .env или вынести в БД позже)
    'tariffs' => [
        'acquiring' => ['percent' => 2.49, 'min_fixed' => 3.49], // банк за прием платежа
        'payouts' => [
            'jur' => ['percent' => 0.10, 'min_fixed' => 0.00], // ЮЛ/ИП
            'fl'  => ['percent' => 1.00, 'min_fixed' => 30.00], // ФЛ
            'sbp' => ['percent' => 0.70, 'min_fixed' => 0.00], // СБП
        ],
    ],
];
