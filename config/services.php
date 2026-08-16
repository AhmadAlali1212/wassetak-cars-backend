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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],



    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'test_mode_only' => env('STRIPE_TEST_MODE_ONLY', true),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'usd'),
        // رصيد داخلي مقابل كل 1 USD. هذه ليست عملة Stripe ولا سعراً تلقائياً.
        'wallet_credit_per_usd' => env('WALLET_CREDIT_PER_USD', 15000),
    ],

    'auction' => [
        'deposit_rate' => env('AUCTION_DEPOSIT_RATE', 0.05),
        'settlement_days' => env('AUCTION_SETTLEMENT_DAYS', 7),
    ],

    'firebase' => [
        // مسار ملف اعتماد حساب خدمة Firebase (JSON) اللازم لإرسال FCM
        // عبر HTTP v1 API. حمّله من Firebase Console > Project Settings >
        // Service Accounts > Generate new private key، وضعه خارج public/.
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase/firebase-credentials.json')),
    ],

];
