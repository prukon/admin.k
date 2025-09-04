<?php

return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [ 
            // где СЛУШАЕТСЯ сам Reverb (локально, без TLS)
            'host' => env('REVERB_SERVER_HOST', '127.0.0.1'),
            'port' => env('REVERB_SERVER_PORT', 6008),
            'path' => env('REVERB_SERVER_PATH', ''),

            // для заголовков/идентификации (можно не трогать)
            'hostname' => env('REVERB_HOST'),

            'options' => [
                'tls' => [], // у нас TLS на nginx, тут пусто
            ],

            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),

            // <<< ЭТОТ БЛОК обязателен в 1.5.x
            'scaling' => [
    'enabled' => env('REVERB_SCALING_ENABLED', false),
    'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
    'server' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => env('REDIS_PORT', '6379'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_DB', '0'),
        'timeout'  => env('REDIS_TIMEOUT', 60),
    ],
],

            'pulse_ingest_interval'     => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],
    ],

    'apps' => [
    'provider' => 'config',

    // список приложений (ID/KEY/SECRET) + как к ним будет коннектиться КЛИЕНТ
    'apps' => [
        [
            'key'    => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),

            // КУДА коннектится браузер (публичный домен через nginx)
            'options' => [
                'host'   => env('REVERB_CLIENT_HOST', 'test.xn--f1ahbpis.online'),
                'port'   => env('REVERB_CLIENT_PORT', 443),
                'scheme' => env('REVERB_CLIENT_SCHEME', 'https'),
                'useTLS' => env('REVERB_CLIENT_SCHEME', 'https') === 'https',
            ],

            'allowed_origins'  => ['*'],
            'ping_interval'    => env('REVERB_APP_PING_INTERVAL', 60),
            'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
            'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],
    ],
];
