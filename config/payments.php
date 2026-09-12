<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway order
    |--------------------------------------------------------------------------
    | Paystack is primary; Flutterwave is the international fallback for the
    | diaspora cards Paystack declines. If the primary cannot take a currency,
    | or has no credentials, the manager falls through to the fallback.
    */

    'primary' => env('PAYMENTS_PRIMARY', 'paystack'),
    'fallback' => env('PAYMENTS_FALLBACK', 'flutterwave'),

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    | A gateway with empty credentials reports itself as unconfigured. It is
    | skipped when choosing a gateway, shown as "awaiting credentials" in the
    | admin, and throws loudly rather than silently succeeding if called
    | directly. Filling in the .env is the only step needed to switch one on.
    */

    'gateways' => [

        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        ],

        // Awaiting credentials. Written in full; inert until these are set.
        'flutterwave' => [
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            // Flutterwave calls this the "secret hash"; it is the shared secret
            // sent back on every webhook as the verif-hash header.
            'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank transfer
    |--------------------------------------------------------------------------
    | Shown to the customer at checkout when enabled. Staff confirm receipt from
    | the admin, which writes a proper payment row rather than editing a total.
    */

    'bank_transfer' => [
        'enabled' => env('PAYMENTS_BANK_TRANSFER_ENABLED', true),
        'account_name' => env('PAYMENTS_BANK_ACCOUNT_NAME'),
        'account_number' => env('PAYMENTS_BANK_ACCOUNT_NUMBER'),
        'bank_name' => env('PAYMENTS_BANK_NAME'),
    ],

    'http_timeout' => (int) env('PAYMENTS_HTTP_TIMEOUT', 20),
];
