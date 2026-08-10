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

    'square' => [
        'access_token' => env('SQUARE_ACCESS_TOKEN'),
        'location_id' => env('SQUARE_LOCATION_ID'),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Pinned so Stripe API responses stay stable when the account default version moves.
        // Webhook payload shape is controlled by the endpoint version in the Stripe dashboard.
        'api_version' => env('STRIPE_API_VERSION', '2024-06-20'),
        'timeout' => env('STRIPE_TIMEOUT', 20),
        'connect_timeout' => env('STRIPE_CONNECT_TIMEOUT', 10),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_CLIENT_SECRET', env('PAYPAL_SECRET')),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'mobilesentrix' => [
        'api_key' => env('MOBILESENTRIX_API_KEY'),
        'api_url' => env('MOBILESENTRIX_API_URL'),
    ],

];
