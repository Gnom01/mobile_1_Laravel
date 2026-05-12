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
        'portal_base_url' => env('CRM_PORTAL_BASE_URL', env('CRM_BASE_URL')),
        'portal_orders_endpoint' => env('CRM_PORTAL_ORDERS_ENDPOINT', '/Orders/CalculateProductForUser'),
        'payment_token_url_template' => env('CRM_PAYMENT_TOKEN_URL_TEMPLATE', 'https://secure.przelewy24.pl/trnRequest/{token}'),
        'mobile_checkout_return_url' => env('CRM_MOBILE_CHECKOUT_RETURN_URL', env('APP_URL')),
        'verify_tls' => filter_var(
            env('CRM_VERIFY_TLS', env('APP_ENV', 'production') !== 'local'),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? (env('APP_ENV', 'production') !== 'local'),
    ],

    'serwersms' => [
        'token'  => env('SERWERSMS_TOKEN'),
        'sender' => env('SERWERSMS_SENDER', null),
    ],

    'sms' => [
        'app_hash' => env('SMS_APP_HASH', ''),
        'test_mode' => filter_var(
            env('SMS_TEST_MODE', env('APP_ENV', 'production') !== 'production'),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? (env('APP_ENV', 'production') !== 'production'),
    ],

];
