<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hybrid POS Deployment Mode (75% Offline Outlet / 25% Cloud AI Hub)
    |--------------------------------------------------------------------------
    | 'outlet'    = Local hotel LAN server (offline-first POS + nightly sync)
    | 'cloud_hub' = Live cloud analytics server receiving multi-outlet snapshots
    */
    'mode' => env('POS_MODE', 'outlet'),

    'outlet_code' => env('POS_OUTLET_CODE', 'ROYAL-YTL-01'),

    'cloud_sync' => [
        'enabled' => filter_var(env('POS_CLOUD_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'url' => env('POS_CLOUD_SYNC_URL', ''),
        'token' => env('POS_CLOUD_SYNC_TOKEN', ''),
        'nightly_time' => env('POS_NIGHTLY_SYNC_TIME', '01:00'),
        'timeout_seconds' => (int) env('POS_CLOUD_SYNC_TIMEOUT', 15),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-4-hydrogen'),
        'endpoint' => env('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1/interactions'),
        'timeout_seconds' => (int) env('GEMINI_TIMEOUT', 25),
    ],
];
