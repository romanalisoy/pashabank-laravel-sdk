<?php

declare(strict_types=1);
use Romanalisoy\PashaBank\Models\PashaRecurring;
use Romanalisoy\PashaBank\Models\PashaTransaction;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Merchant
    |--------------------------------------------------------------------------
    |
    | The merchant key used when calling PashaBank::sms(), PashaBank::dms(),
    | etc. without an explicit merchant selection. Switch at runtime with
    | PashaBank::merchant('other_key').
    |
    */
    'default' => env('PASHABANK_MERCHANT', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Controls which endpoint set is used below. Supported: "production",
    | "testing". For local development point PASHABANK_ENV=testing and fill in
    | PASHABANK_MERCHANT_URL / PASHABANK_CLIENT_URL with the bank-provided
    | sandbox URLs.
    |
    */
    'environment' => env('PASHABANK_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'production' => [
            'merchant_handler' => 'https://ecomm.pashabank.az:18443/ecomm2/MerchantHandler',
            'client_handler' => 'https://ecomm.pashabank.az:8463/ecomm2/ClientHandler',
        ],
        'testing' => [
            'merchant_handler' => env('PASHABANK_MERCHANT_URL'),
            'client_handler' => env('PASHABANK_CLIENT_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchants
    |--------------------------------------------------------------------------
    |
    | One or more merchants. Most apps need only the "main" entry. Multi-tenant
    | setups can add further keys and switch with PashaBank::merchant('key').
    |
    | certificate.type: "pkcs12" (single .p12 file) or "pem" (separate cert +
    | private key files). The bank issues a .p12 by default; convert to PEM
    | when using curl without PKCS#12 support (see readme).
    |
    */
    'merchants' => [
        'main' => [
            'merchant_id' => env('PASHABANK_MERCHANT_ID'),
            'terminal_id' => env('PASHABANK_TERMINAL_ID'),

            'certificate' => [
                'type' => env('PASHABANK_CERT_TYPE', 'pkcs12'),
                'path' => env('PASHABANK_CERT_PATH'),
                'password' => env('PASHABANK_CERT_PASSWORD'),
                'key_path' => env('PASHABANK_KEY_PATH'),
                'ca_path' => env('PASHABANK_CA_PATH'),
            ],

            'language' => env('PASHABANK_LANGUAGE', 'az'),
            'currency' => env('PASHABANK_CURRENCY', 'AZN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => (int) env('PASHABANK_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('PASHABANK_HTTP_CONNECT_TIMEOUT', 10),
        'verify_ssl' => env('PASHABANK_VERIFY_SSL', true),
        'tls_version' => 'TLSv1.2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback
    |--------------------------------------------------------------------------
    |
    | The bank redirects the client's browser back to this app via HTTP POST
    | after 3DS authentication. The built-in controller calls the bank once
    | more (command=c) to finalize the payment, updates the persisted
    | transaction, dispatches events, and redirects or returns JSON.
    |
    | Disable the route with 'enabled' => false and call
    | PashaBank::completion($transId)->get() yourself.
    |
    */
    'callback' => [
        'enabled' => true,
        'route' => env('PASHABANK_CALLBACK_ROUTE', '/pashabank/callback'),
        'name' => 'pashabank.callback',
        'middleware' => ['web'],
        'response' => env('PASHABANK_CALLBACK_RESPONSE', 'redirect'),
        'success_url' => env('PASHABANK_SUCCESS_URL', '/payment/success'),
        'failure_url' => env('PASHABANK_FAILURE_URL', '/payment/failure'),
        'ip_allowlist' => array_filter(explode(',', (string) env('PASHABANK_CALLBACK_IPS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission / Acquirer Surcharge
    |--------------------------------------------------------------------------
    |
    | When enabled, the bank shows a commission approval page to the client
    | before redirecting to the card entry form. Requires the merchant to be
    | configured for surcharge on the bank side.
    |
    */
    'commission' => [
        'enabled' => env('PASHABANK_COMMISSION_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    |
    | Transaction history is optional but recurring registrations are required
    | for PashaBank::recurring()->execute() to work. Table and model names are
    | overridable; set models to a subclass if the defaults don't match your
    | naming conventions.
    |
    | Amounts are stored as BIGINT minor units (1.99 AZN = 199, 19.80 = 1980).
    |
    */
    'persistence' => [
        'enabled' => env('PASHABANK_PERSISTENCE', true),

        'tables' => [
            'transactions' => env('PASHABANK_TABLE_TRANSACTIONS', 'pashabank_transactions'),
            'recurring' => env('PASHABANK_TABLE_RECURRING', 'pashabank_recurring'),
        ],

        'models' => [
            'transaction' => PashaTransaction::class,
            'recurring' => PashaRecurring::class,
        ],

        'auto_record_transactions' => env('PASHABANK_RECORD_TRANSACTIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('PASHABANK_LOG_ENABLED', true),
        'channel' => env('PASHABANK_LOG_CHANNEL', 'stack'),
        'mask_card_numbers' => true,
    ],
];
