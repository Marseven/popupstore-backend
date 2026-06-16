<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS sending toggle
    |--------------------------------------------------------------------------
    | When disabled, the SmsService logs the message instead of calling a
    | gateway (safe default for local/staging and for hosts without an SMS plan).
    */

    'enabled' => env('SMS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Gateway driver
    |--------------------------------------------------------------------------
    | 'log'  — write the message to the log channel (no external call).
    | 'http' — POST to a generic HTTP gateway (Gabon-compatible provider).
    */

    'gateway' => env('SMS_GATEWAY', 'log'),

    'sender' => env('SMS_SENDER', 'PopupStore'),

    'http' => [
        'url' => env('SMS_HTTP_URL'),
        'token' => env('SMS_HTTP_TOKEN'),
        // Field names the provider expects; override per gateway via env.
        'to_field' => env('SMS_HTTP_TO_FIELD', 'to'),
        'message_field' => env('SMS_HTTP_MESSAGE_FIELD', 'message'),
        'from_field' => env('SMS_HTTP_FROM_FIELD', 'from'),
        'timeout' => env('SMS_HTTP_TIMEOUT', 15),
    ],

];
