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
    |
    | Both production and testing endpoints respect env overrides. Defaults
    | match the bank's published URLs from the integration PDF; override
    | only if the bank issues you a different host (e.g. a dedicated
    | sandbox or a redirected internal gateway).
    |
    | PASHABANK_MERCHANT_URL / PASHABANK_CLIENT_URL apply to whichever
    | environment is currently active, so a single .env line is enough for
    | most cases. The *_PROD_URL / *_TEST_URL variants exist for the rare
    | case of needing to keep both URL sets in the same .env.
    |
    */
    'endpoints' => [
        'production' => [
            'merchant_handler' => env(
                'PASHABANK_MERCHANT_URL_PROD',
                env('PASHABANK_MERCHANT_URL', 'https://ecomm.pashabank.az:18443/ecomm2/MerchantHandler')
            ),
            'client_handler' => env(
                'PASHABANK_CLIENT_URL_PROD',
                env('PASHABANK_CLIENT_URL', 'https://ecomm.pashabank.az:8463/ecomm2/ClientHandler')
            ),
        ],
        'testing' => [
            'merchant_handler' => env(
                'PASHABANK_MERCHANT_URL_TEST',
                env('PASHABANK_MERCHANT_URL', 'https://testecomm.pashabank.az:18443/ecomm2/MerchantHandler')
            ),
            'client_handler' => env(
                'PASHABANK_CLIENT_URL_TEST',
                env('PASHABANK_CLIENT_URL', 'https://testecomm.pashabank.az:8463/ecomm2/ClientHandler')
            ),
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
    |
    | Defaults match the bank's recommendation. Only relax verify_peer /
    | verify_host while debugging against a self-signed sandbox — never
    | in production, because mTLS without verification is no protection.
    |
    | return_transfer is exposed for completeness; Laravel's Http facade
    | already returns the body, so leaving it true is the only sane value.
    |
    */
    'http' => [
        'timeout' => (int) env('PASHABANK_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('PASHABANK_HTTP_CONNECT_TIMEOUT', 10),
        'verify_ssl' => env('PASHABANK_VERIFY_SSL', true),
        'verify_host' => env('PASHABANK_VERIFY_HOST', true),
        'return_transfer' => env('PASHABANK_RETURN_TRANSFER', true),
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

        // -------------------------------------------------------------
        // Single-route mode (default)
        // -------------------------------------------------------------
        // The bank POSTs a single RETURN_OK_URL. Controller decides
        // success vs failure by calling command=c. Leave success_route
        // / failure_route below NULL to use this mode.
        'route' => env('PASHABANK_CALLBACK_ROUTE', '/pashabank/callback'),
        'name' => 'pashabank.callback',

        // -------------------------------------------------------------
        // Split-route mode (optional)
        // -------------------------------------------------------------
        // If your merchant is configured with two URLs at the bank, set
        // both env vars below. Each URL is registered as a route; both
        // go through the same controller (which still verifies with
        // command=c so a forged hit cannot mark a payment as paid).
        //
        //   PASHABANK_SUCCESS_ROUTE=/api/v1/callbacks/pasha-bank/payment/success
        //   PASHABANK_FAILURE_ROUTE=/api/v1/callbacks/pasha-bank/payment/failure
        'success_route' => env('PASHABANK_SUCCESS_ROUTE'),
        'failure_route' => env('PASHABANK_FAILURE_ROUTE'),

        // -------------------------------------------------------------
        // Common
        // -------------------------------------------------------------
        // For SPA / mobile flows the API endpoint is stateless — drop
        // 'web' middleware and use 'api'. For server-rendered apps that
        // need the session-cookie-based 'web' stack, keep the default.
        'middleware' => array_filter(explode(',', (string) env('PASHABANK_CALLBACK_MIDDLEWARE', 'web'))),

        // 'redirect' (default) → 302 to success_url / failure_url
        // 'json'              → JSON body, 200 / 422 status code
        'response' => env('PASHABANK_CALLBACK_RESPONSE', 'redirect'),

        // Where to send the user's BROWSER after processing. Can be a
        // relative path (server-rendered Laravel) or a full URL pointing
        // to your separate frontend (SPA / mobile webview).
        // The SDK appends ?trans_id=<id> automatically.
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
