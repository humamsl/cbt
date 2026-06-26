<?php

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        'array' => [
            'transport' => 'array',
        ],
        'failover' => [
            'transport' => 'failover',
            'mailers' => ['log'],
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@sekolah.test'),
        'name' => env('MAIL_FROM_NAME', 'CBT Sekolah'),
    ],
];
