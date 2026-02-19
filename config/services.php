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
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'main_store' => [
        'base_url' => env('MAIN_STORE_BASE_URL', ''),
        'token' => env('MAIN_STORE_TOKEN', ''),
        'timeout' => env('MAIN_STORE_TIMEOUT', 10),
        'stale_threshold_minutes' => env('MAIN_STORE_STALE_THRESHOLD_MINUTES', 60),
        'failure_alert_threshold' => env('MAIN_STORE_FAILURE_ALERT_THRESHOLD', 3),
    ],

];
