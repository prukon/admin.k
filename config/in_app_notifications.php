<?php

declare(strict_types=1);

return [
    'categories' => [
        'update' => 'Обновление',
        'important' => 'Важное',
        'normal' => 'Обычное',
    ],

    'ttl_presets' => [
        '1d' => '1 сутки',
        '7d' => '7 суток',
        '30d' => '30 суток',
        'custom' => 'Своя дата',
        'until_read' => 'Пока не прочитают',
    ],

    'system_role_names' => ['user', 'admin', 'trainer'],

    'events' => [
        'cabinet_team_attached' => [
            'title' => 'Ученик добавил группу',
            'role_names' => ['admin', 'trainer'],
            'category' => 'normal',
            'ttl_preset' => '30d',
        ],
    ],

    'dropdown_limit' => 3,

    'dropdown_preview_limit' => 60,

    'index_per_page' => 20,
];
