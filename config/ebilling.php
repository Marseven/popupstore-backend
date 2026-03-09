<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E-Billing API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the E-Billing payment gateway API.
    |
    */

    'api_url' => env('EBILLING_API_URL', 'https://api.ebilling.ga/v1'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your E-Billing API key for authentication.
    |
    */

    'api_key' => env('EBILLING_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Merchant ID
    |--------------------------------------------------------------------------
    |
    | Your E-Billing merchant identifier.
    |
    */

    'merchant_id' => env('EBILLING_MERCHANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    |
    | The URL E-Billing will call to notify payment status updates.
    |
    */

    'callback_url' => env('EBILLING_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Supported Providers
    |--------------------------------------------------------------------------
    |
    | The mobile money providers supported for payments.
    |
    */

    'supported_providers' => ['airtel', 'moov'],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for transactions.
    |
    */

    'currency' => 'XAF',

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | The request timeout in seconds for API calls.
    |
    */

    'timeout' => 30,

];
