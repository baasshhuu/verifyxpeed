<?php

return [
    'brand' => env('VX_BRAND', 'VerifyXpeed'),
    'support_email' => env('VX_SUPPORT_EMAIL', 'support@verifyxpeed.in'),

    // GST added on top of every price (18%)
    'gst_rate' => (float) env('VX_GST_RATE', 0.18),

    // Which Cashfree environment the web dashboard uses: sandbox | production.
    // Keep "sandbox" until production Secure ID keys are added.
    'web_env' => env('VX_WEB_ENV', 'sandbox'),

    // New customers must be approved by an admin (KYB) before running checks.
    'require_approval' => (bool) env('VX_REQUIRE_APPROVAL', true),

    // Free starting credit for new accounts, in rupees (0 = none)
    'signup_credit' => (float) env('VX_SIGNUP_CREDIT', 0),

    'bulk_max_rows' => (int) env('VX_BULK_MAX_ROWS', 5000),

    'cashfree' => [
        // Cashfree Secure ID (verification) keys. Payouts & Secure ID share keys; PG has its own.
        'secureid' => [
            'sandbox' => [
                'base_url' => env('CASHFREE_SECUREID_TEST_BASE_URL', 'https://sandbox.cashfree.com/verification'),
                'client_id' => env('CASHFREE_SECUREID_TEST_CLIENT_ID'),
                'client_secret' => env('CASHFREE_SECUREID_TEST_CLIENT_SECRET'),
                'public_key_path' => env('CASHFREE_SECUREID_TEST_PUBLIC_KEY'),   // optional, for 2FA signature
            ],
            'production' => [
                'base_url' => env('CASHFREE_SECUREID_BASE_URL', 'https://api.cashfree.com/verification'),
                'client_id' => env('CASHFREE_SECUREID_CLIENT_ID'),
                'client_secret' => env('CASHFREE_SECUREID_CLIENT_SECRET'),
                'public_key_path' => env('CASHFREE_SECUREID_PUBLIC_KEY'),
            ],
            'timeout' => (int) env('CASHFREE_SECUREID_TIMEOUT', 45),
        ],

        // Cashfree Payment Gateway, for wallet top-ups
        'pg' => [
            'env' => env('CASHFREE_PG_ENV', 'sandbox'),          // sandbox | production
            'app_id' => env('CASHFREE_PG_APP_ID'),
            'secret' => env('CASHFREE_PG_SECRET'),
            'api_version' => '2023-08-01',
            'base_url' => env('CASHFREE_PG_BASE_URL'), // leave empty; only for local testing
        ],

        // Video KYC templates (created in the Cashfree dashboard)
        'vkyc_user_template' => env('CASHFREE_VKYC_USER_TEMPLATE'),
        'vkyc_agent_template' => env('CASHFREE_VKYC_AGENT_TEMPLATE'),
        // KYC link template name (created in the Cashfree dashboard)
        'kyc_link_template' => env('CASHFREE_KYC_LINK_TEMPLATE', 'Aadhaar_verification'),
    ],

    'topup' => [
        'min' => (int) env('VX_TOPUP_MIN', 100),
        'max' => (int) env('VX_TOPUP_MAX', 500000),
        // shown for bank-transfer top-ups
        'bank_details' => env('VX_BANK_DETAILS', 'Bank details coming soon. Admin: add them in Cashfree & logs.'),
    ],
];
