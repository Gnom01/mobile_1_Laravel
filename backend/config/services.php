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

    'crm' => [
    'base_url' => env('CRM_BASE_URL'),
    'login_endpoint' => env('CRM_LOGIN_ENDPOINT', '/auth/login'),
    'refresh_endpoint' => env('CRM_REFRESH_ENDPOINT', '/auth/refresh'),
    'username' => env('CRM_USERNAME'),
    'password' => env('CRM_PASSWORD'),
],

    'serwersms' => [
        'token'  => env('SERWERSMS_TOKEN'),
        'sender' => env('SERWERSMS_SENDER', null),
    ],

    'sms' => [
        'app_hash' => env('SMS_APP_HASH', ''),
    ],

];
