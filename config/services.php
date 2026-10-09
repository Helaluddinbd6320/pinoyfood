<?php

return [

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        // Switch off at launch while phone numbers are unverified (step 9 risk).
        'enabled' => env('GOOGLE_LOGIN_ENABLED', true),
    ],

    'whatsapp' => [
        'business_number' => env('WHATSAPP_BUSINESS_NUMBER'),
    ],

    'contact' => [
        'email' => env('CONTACT_EMAIL', 'hello@pinoyfoodksa.com'),
    ],

    'backup' => [
        // Outside public/. Change to a folder above the web root if you like.
        'path' => env('BACKUP_PATH', storage_path('backups')),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'mysqldump' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    ],

];