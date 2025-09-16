<?php

return [
    'default_connection' => env('DB_CONNECTION', 'pgsql'),

    'defaults' => [
        'enable_partition_pruning' => true,
        'detach_concurrently' => false,
        'analyze_after_create' => true,
        'vacuum_after_drop' => true,
    ],

    'naming' => [
        'prefix' => '',
        'suffix' => '',
        'separator' => '_',
        'date_format' => 'Y_m',
        'day_format' => 'Y_m_d',
    ],

    'logging' => [
        'enabled' => env('PARTITION_LOGGING', true),
        'channel' => env('PARTITION_LOG_CHANNEL', 'daily'),
    ],
];