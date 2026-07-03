<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Aplikasi Data Center (provider data induk sekolah) — CBT adalah klien.
    'datacenter' => [
        'api_url' => env('DATACENTER_API_URL', 'http://127.0.0.1:8001/api'),
        'token' => env('DATACENTER_API_TOKEN'),
        'app_url' => env('DATACENTER_APP_URL', 'http://127.0.0.1:8001'),
    ],

    // Landing page publik sekolah (project terpisah — lihat "landing-page")
    'landing' => [
        'app_url' => env('LANDING_APP_URL', 'http://127.0.0.1:8003'),
    ],
];
