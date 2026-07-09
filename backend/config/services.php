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
        'push_api_token' => env('CRM_PUSH_API_TOKEN'),
        // Shared secret sent on every /CrmToMobileSync/* call so a random
        // authenticated app user (valid JWT, no secret) cannot pull the data.
        'mobile_sync_token' => env('CRM_MOBILE_SYNC_TOKEN'),
        'login_endpoint' => env('CRM_LOGIN_ENDPOINT', '/auth/login'),
        'refresh_endpoint' => env('CRM_REFRESH_ENDPOINT', '/auth/refresh'),
        'username' => env('CRM_USERNAME'),
        'password' => env('CRM_PASSWORD'),
        'portal_base_url' => env('CRM_PORTAL_BASE_URL', env('CRM_BASE_URL')),
        'portal_orders_endpoint'         => env('CRM_PORTAL_ORDERS_ENDPOINT', '/Orders/createOrder'),
        'portal_order_snapshot_endpoint' => env('CRM_PORTAL_ORDER_SNAPSHOT_ENDPOINT', '/Orders/GetOrderSnapshot'),
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

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'allow_simulated' => filter_var(
            env('FIREBASE_ALLOW_SIMULATED_PUSH', env('APP_ENV', 'production') === 'testing'),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? false,
    ],

    // Program wsparcia Fundacji Świat Tańca (moduł SUP z planu Etapu I).
    // Liczby „realnego wpływu" są aktualizowane ręcznie do czasu integracji CRM.
    'support_program' => [
        // Wyłącznik części subskrypcyjnej: false = zakładka wyłącznie
        // informacyjna (bez zapisów i płatności). Ponowne włączenie:
        // SUPPORT_PROGRAM_ENABLED=true w .env — kod zostaje nietknięty.
        'enabled' => filter_var(
            env('SUPPORT_PROGRAM_ENABLED', false),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? false,
        'monthly_amount' => (float) env('SUPPORT_PROGRAM_MONTHLY_AMOUNT', 5.00),
        'impact' => [
            ['value' => (int) env('SUPPORT_PROGRAM_IMPACT_SCHOLARSHIPS', 12), 'label' => 'stypendiów', 'sublabel' => 'w tym miesiącu'],
            ['value' => (int) env('SUPPORT_PROGRAM_IMPACT_DANCERS', 48), 'label' => 'tancerzy', 'sublabel' => 'wspartych w rozwoju'],
            ['value' => (int) env('SUPPORT_PROGRAM_IMPACT_PROJECTS', 5), 'label' => 'projektów', 'sublabel' => 'finansowanych przez Fundację Świat Tańca'],
        ],
        'benefits' => [
            ['title' => 'Priorytet zapisów', 'subtitle' => 'w warsztatach, obozach i eventach'],
            ['title' => '5% zniżki', 'subtitle' => 'na wydarzenia i w EDS Store'],
            ['title' => 'Specjalne oferty', 'subtitle' => 'dostępne tylko dla wspierających'],
            ['title' => 'Status Wspierającego', 'subtitle' => 'wyróżnienie na Twoim koncie'],
        ],
    ],

];
