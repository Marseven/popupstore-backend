<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode (lab / prod)
    |--------------------------------------------------------------------------
    */

    'mode' => env('EBILLING_MODE', 'lab'),

    /*
    |--------------------------------------------------------------------------
    | URLs per environment
    |--------------------------------------------------------------------------
    */

    // Explicit overrides — take precedence over the mode-based defaults below.
    // Set these in .env when the gateway URLs differ from the defaults.
    'api_url' => env('EBILLING_API_URL'),
    'portal_url' => env('EBILLING_PORTAL_URL'),

    'urls' => [
        'lab' => [
            'api' => 'https://lab.billing-easy.net',
            'portal' => 'https://test.billing-easy.net',
        ],
        'prod' => [
            'api' => 'https://stg.billing-easy.com',
            'portal' => 'https://staging.billing-easy.net',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials (Basic Auth)
    |--------------------------------------------------------------------------
    */

    'username' => env('EBILLING_USERNAME'),
    'shared_key' => env('EBILLING_SHARED_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    */

    'callback_url' => env('EBILLING_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Callback shared secret
    |--------------------------------------------------------------------------
    | When set, the payment callback must present this token (X-Callback-Token
    | header or callback_token field) or it is rejected (403). Unset = allow
    | (back-compat) but log a warning.
    */

    'callback_secret' => env('EBILLING_CALLBACK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Redirect URL (frontend — where the client returns after payment)
    |--------------------------------------------------------------------------
    */

    'redirect_url' => env('EBILLING_REDIRECT_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Bill expiry period (minutes)
    |--------------------------------------------------------------------------
    */

    'expiry_period' => 60,

    /*
    |--------------------------------------------------------------------------
    | Provider mapping (internal → Ebilling system names)
    |--------------------------------------------------------------------------
    */

    'provider_map' => [
        'airtel' => 'airtelmoney',
        'moov' => 'moovmoney4',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    */

    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency' => 'XAF',

];
