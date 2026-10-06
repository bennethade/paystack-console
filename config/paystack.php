<?php

/*
|--------------------------------------------------------------------------
| Paystack API Explorer configuration
|--------------------------------------------------------------------------
|
| This file is the single place where supported markets, currencies and
| credentials are declared. Country/currency rows are seeded into the
| database from here (so admins can toggle them at runtime), but
| credentials are ONLY ever read from this file / the environment. They are
| never written to the database and never handed to a view or to JavaScript.
|
| Every country, currency and channel below was checked against the official
| documentation (https://paystack.com/docs/api/#supported-currency and
| https://paystack.com/docs/payments/payment-channels/). Do not add a market
| here unless Paystack documents it.
|
*/

$key = static fn (string $name, ?string $fallback = null) => env($name) ?: ($fallback !== null ? env($fallback) : null);

return [

    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),

    'timeout' => (int) env('PAYSTACK_TIMEOUT', 30),

    'connect_timeout' => (int) env('PAYSTACK_CONNECT_TIMEOUT', 10),

    /*
    | Environments. "live" is disabled unless PAYSTACK_LIVE_ENABLED=true AND
    | the admin feature flag allows it AND the user has the live permission
    | AND the user confirms each live request.
    */
    'environments' => [
        'test' => ['label' => 'Test', 'secret_prefix' => 'sk_test_', 'public_prefix' => 'pk_test_'],
        'live' => ['label' => 'Live', 'secret_prefix' => 'sk_live_', 'public_prefix' => 'pk_live_'],
    ],

    'default_environment' => 'test',

    'live_enabled' => (bool) env('PAYSTACK_LIVE_ENABLED', false),

    /*
    | Mock mode returns clearly labelled placeholder responses instead of
    | calling Paystack. Intended only for UI development / demos.
    */
    'mock_mode' => (bool) env('PAYSTACK_MOCK_MODE', false),

    'default_country' => env('PAYSTACK_DEFAULT_COUNTRY', 'NG'),

    /*
    | Credentials, keyed by country code then environment.
    |
    | A Paystack integration belongs to a business registered in one country,
    | so each market can carry its own keys. When a market-specific key is not
    | set, the generic PAYSTACK_SECRET_KEY / PAYSTACK_PUBLIC_KEY (test) and
    | PAYSTACK_LIVE_SECRET_KEY / PAYSTACK_LIVE_PUBLIC_KEY (live) are used.
    */
    'credentials' => [
        'NG' => [
            'test' => ['secret' => $key('PAYSTACK_NG_TEST_SECRET_KEY', 'PAYSTACK_SECRET_KEY'), 'public' => $key('PAYSTACK_NG_TEST_PUBLIC_KEY', 'PAYSTACK_PUBLIC_KEY')],
            'live' => ['secret' => $key('PAYSTACK_NG_LIVE_SECRET_KEY', 'PAYSTACK_LIVE_SECRET_KEY'), 'public' => $key('PAYSTACK_NG_LIVE_PUBLIC_KEY', 'PAYSTACK_LIVE_PUBLIC_KEY')],
        ],
        'GH' => [
            'test' => ['secret' => $key('PAYSTACK_GH_TEST_SECRET_KEY', 'PAYSTACK_SECRET_KEY'), 'public' => $key('PAYSTACK_GH_TEST_PUBLIC_KEY', 'PAYSTACK_PUBLIC_KEY')],
            'live' => ['secret' => $key('PAYSTACK_GH_LIVE_SECRET_KEY', 'PAYSTACK_LIVE_SECRET_KEY'), 'public' => $key('PAYSTACK_GH_LIVE_PUBLIC_KEY', 'PAYSTACK_LIVE_PUBLIC_KEY')],
        ],
        'KE' => [
            'test' => ['secret' => $key('PAYSTACK_KE_TEST_SECRET_KEY', 'PAYSTACK_SECRET_KEY'), 'public' => $key('PAYSTACK_KE_TEST_PUBLIC_KEY', 'PAYSTACK_PUBLIC_KEY')],
            'live' => ['secret' => $key('PAYSTACK_KE_LIVE_SECRET_KEY', 'PAYSTACK_LIVE_SECRET_KEY'), 'public' => $key('PAYSTACK_KE_LIVE_PUBLIC_KEY', 'PAYSTACK_LIVE_PUBLIC_KEY')],
        ],
        'ZA' => [
            'test' => ['secret' => $key('PAYSTACK_ZA_TEST_SECRET_KEY', 'PAYSTACK_SECRET_KEY'), 'public' => $key('PAYSTACK_ZA_TEST_PUBLIC_KEY', 'PAYSTACK_PUBLIC_KEY')],
            'live' => ['secret' => $key('PAYSTACK_ZA_LIVE_SECRET_KEY', 'PAYSTACK_LIVE_SECRET_KEY'), 'public' => $key('PAYSTACK_ZA_LIVE_PUBLIC_KEY', 'PAYSTACK_LIVE_PUBLIC_KEY')],
        ],
        'CI' => [
            'test' => ['secret' => $key('PAYSTACK_CI_TEST_SECRET_KEY', 'PAYSTACK_SECRET_KEY'), 'public' => $key('PAYSTACK_CI_TEST_PUBLIC_KEY', 'PAYSTACK_PUBLIC_KEY')],
            'live' => ['secret' => $key('PAYSTACK_CI_LIVE_SECRET_KEY', 'PAYSTACK_LIVE_SECRET_KEY'), 'public' => $key('PAYSTACK_CI_LIVE_PUBLIC_KEY', 'PAYSTACK_LIVE_PUBLIC_KEY')],
        ],
    ],

    /*
    | Currencies (source: https://paystack.com/docs/api/#supported-currency).
    | minimum_amount is in the subunit (e.g. 5000 kobo = ₦50.00).
    | XOF has no subunit but Paystack still requires amount × 100.
    */
    'currencies' => [
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'subunit' => 'kobo', 'subunit_factor' => 100, 'minimum_amount' => 5000],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'subunit' => 'cent', 'subunit_factor' => 100, 'minimum_amount' => 200],
        'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => '₵', 'subunit' => 'pesewa', 'subunit_factor' => 100, 'minimum_amount' => 10],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R', 'subunit' => 'cent', 'subunit_factor' => 100, 'minimum_amount' => 100],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'Ksh', 'subunit' => 'cent', 'subunit_factor' => 100, 'minimum_amount' => 300],
        'XOF' => ['name' => 'West African CFA Franc', 'symbol' => 'XOF', 'subunit' => null, 'subunit_factor' => 100, 'minimum_amount' => 100],
    ],

    /*
    | Payment channels (https://paystack.com/docs/payments/payment-channels/).
    | countries: markets where Paystack documents the channel as available.
    | null means Paystack documents no market restriction; the note explains
    | what availability depends on instead.
    */
    'channels' => [
        'card' => ['label' => 'Card', 'countries' => null, 'note' => 'Available in all markets.'],
        'bank' => ['label' => 'Bank (direct debit of bank account)', 'countries' => ['NG']],
        'bank_transfer' => ['label' => 'Pay with Transfer', 'countries' => ['NG', 'GH']],
        'ussd' => ['label' => 'USSD', 'countries' => ['NG']],
        'mobile_money' => ['label' => 'Mobile money', 'countries' => ['GH', 'KE', 'CI']],
        'eft' => ['label' => 'EFT', 'countries' => ['ZA']],
        'qr' => ['label' => 'QR code', 'countries' => ['ZA']],
        'capitec_pay' => ['label' => 'Capitec Pay', 'countries' => ['ZA']],
        'apple_pay' => ['label' => 'Apple Pay', 'countries' => null, 'note' => 'Requires international payments to be enabled on your integration.'],
        'payattitude' => ['label' => 'PayAttitude', 'countries' => null, 'note' => 'No market restriction is documented; availability depends on your integration.'],
    ],

    /*
    | Countries.
    |
    | bank_country: the value Paystack accepts for GET /bank?country=
    |   (documented values: ghana, kenya, nigeria, south africa). Null means
    |   the bank list endpoint does not accept this market.
    | features: market-specific product availability, referenced by the
    |   endpoint overlays (config/paystack-endpoints.php).
    | recipient_types: transfer recipient types by market
    |   (https://paystack.com/docs/transfers/creating-transfer-recipients/).
    */
    'countries' => [
        'NG' => [
            'name' => 'Nigeria',
            'flag' => '🇳🇬',
            'bank_country' => 'nigeria',
            'default_currency' => 'NGN',
            'currencies' => ['NGN', 'USD'],
            'recipient_types' => ['nuban', 'authorization'],
            'features' => ['bank_list', 'resolve_account', 'dedicated_virtual_account', 'direct_debit', 'transfers'],
        ],
        'GH' => [
            'name' => 'Ghana',
            'flag' => '🇬🇭',
            'bank_country' => 'ghana',
            'default_currency' => 'GHS',
            'currencies' => ['GHS'],
            'recipient_types' => ['ghipss', 'mobile_money', 'authorization'],
            'features' => ['bank_list', 'resolve_account', 'dedicated_virtual_account', 'mobile_money_recipient', 'transfers'],
        ],
        'KE' => [
            'name' => 'Kenya',
            'flag' => '🇰🇪',
            'bank_country' => 'kenya',
            'default_currency' => 'KES',
            'currencies' => ['KES', 'USD'],
            'recipient_types' => ['kepss', 'mobile_money', 'authorization'],
            'features' => ['bank_list', 'mobile_money_recipient', 'transfers'],
        ],
        'ZA' => [
            'name' => 'South Africa',
            'flag' => '🇿🇦',
            'bank_country' => 'south africa',
            'default_currency' => 'ZAR',
            'currencies' => ['ZAR'],
            'recipient_types' => ['basa', 'authorization'],
            'features' => ['bank_list', 'validate_account', 'preauthorization', 'capitec_pay', 'transfers'],
        ],
        'CI' => [
            'name' => "Côte d'Ivoire",
            'flag' => '🇨🇮',
            'bank_country' => null,
            'default_currency' => 'XOF',
            'currencies' => ['XOF'],
            'recipient_types' => ['authorization'],
            'features' => [],
        ],
    ],

    /*
    | Human-readable descriptions of the feature flags used above, shown in
    | the UI so users understand why an endpoint is unavailable.
    */
    'feature_labels' => [
        'bank_list' => 'List Banks (GET /bank country filter)',
        'resolve_account' => 'Resolve Account Number (Nigeria, Ghana)',
        'validate_account' => 'Account Validation (South Africa)',
        'dedicated_virtual_account' => 'Dedicated Virtual Accounts (Nigeria, Ghana)',
        'direct_debit' => 'Direct Debit (Nigeria)',
        'mobile_money_recipient' => 'Mobile money transfer recipients (Ghana, Kenya)',
        'preauthorization' => 'Preauthorization (ZAR only)',
        'capitec_pay' => 'Capitec Pay (South Africa)',
        'transfers' => 'Transfers (Nigeria, Ghana, South Africa, Kenya)',
    ],

    /*
    | Webhooks: Paystack signs the raw body with HMAC-SHA512 using the secret
    | key and sends it in x-paystack-signature. Source IPs are documented at
    | https://paystack.com/docs/payments/webhooks/#ip-whitelisting
    */
    'webhooks' => [
        'enabled' => (bool) env('PAYSTACK_WEBHOOKS_ENABLED', true),
        'enforce_ip_allowlist' => (bool) env('PAYSTACK_WEBHOOK_ENFORCE_IPS', false),
        'allowed_ips' => ['52.31.139.75', '52.49.173.169', '52.214.14.220'],
        'max_payload_kb' => 512,
    ],

    'rate_limits' => [
        'execute_per_minute' => (int) env('PAYSTACK_EXECUTE_PER_MINUTE', 30),
        'execute_per_ip_per_minute' => (int) env('PAYSTACK_EXECUTE_PER_IP_PER_MINUTE', 60),
        'login_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'webhooks_per_minute' => (int) env('PAYSTACK_WEBHOOKS_PER_MINUTE', 120),
    ],

    'history' => [
        'store_responses' => (bool) env('PAYSTACK_HISTORY_STORE_RESPONSES', true),
        'max_response_kb' => (int) env('PAYSTACK_HISTORY_MAX_RESPONSE_KB', 256),
        'retention_days' => (int) env('PAYSTACK_HISTORY_RETENTION_DAYS', 30),
    ],

    'uploads' => [
        'max_kb' => (int) env('PAYSTACK_UPLOAD_MAX_KB', 5120),
    ],

    /*
    | Keys whose values are masked before anything is logged, stored or shown
    | in the request inspector. Matching is case-insensitive on the key name.
    | The Authorization *header* is always masked separately; "authorization"
    | is not listed here because Paystack bodies use it for authorization
    | codes (e.g. POST /bulkcharge), which users need to see and replay.
    */
    'sensitive_keys' => [
        'password', 'password_confirmation', 'secret', 'secret_key',
        'cvv', 'pin', 'otp', 'card_number', 'expiry_month', 'expiry_year', 'x-paystack-signature',
        'cookie', 'set-cookie', 'token',
    ],

    'openapi' => [
        'spec_path' => resource_path('openapi/paystack.yaml'),
        'docs_base' => 'https://paystack.com/docs/api',
    ],
];
