<?php

return [
    'env' => env('MOBILESENTRIX_ENV', 'staging'),
    'base_url' => rtrim((string) env('MOBILESENTRIX_BASE_URL'), '/'),
    'consumer_name' => env('MOBILESENTRIX_CONSUMER_NAME'),
    'consumer_key' => env('MOBILESENTRIX_CONSUMER_KEY'),
    'consumer_secret' => env('MOBILESENTRIX_CONSUMER_SECRET'),
    'access_token' => env('MOBILESENTRIX_ACCESS_TOKEN'),
    'access_token_secret' => env('MOBILESENTRIX_ACCESS_TOKEN_SECRET'),
    'callback_url' => env('MOBILESENTRIX_CALLBACK_URL'),
    'allow_browser_secret_redirect' => env('MOBILESENTRIX_ALLOW_BROWSER_SECRET_REDIRECT', false),
    'auth_transport' => env('MOBILESENTRIX_AUTH_TRANSPORT', 'oauth_header'),
    'sync_enabled' => env('MOBILESENTRIX_SYNC_ENABLED', false),
    'timeout' => (int) env('MOBILESENTRIX_TIMEOUT', 30),
    'default_markup_type' => env('MOBILESENTRIX_DEFAULT_MARKUP_TYPE', 'none'),
    'default_markup_value' => (float) env('MOBILESENTRIX_DEFAULT_MARKUP_VALUE', 0),
];
