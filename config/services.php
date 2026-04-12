<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Services e-Sup'M
    |--------------------------------------------------------------------------
    */

    // CinetPay - Paiement Afrique
    'cinetpay' => [
        'api_key'  => env('CINETPAY_API_KEY'),
        'site_id'  => env('CINETPAY_SITE_ID'),
        'base_url' => 'https://api-checkout.cinetpay.com/v2',
    ],

    // PayDunya - Mobile Money
    'paydunya' => [
        'master_key'  => env('PAYDUNYA_MASTER_KEY'),
        'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
        'token'       => env('PAYDUNYA_TOKEN'),
        'mode'        => env('PAYDUNYA_MODE', 'test'),
        'base_url'    => env('PAYDUNYA_MODE') === 'live' ? 'https://app.paydunya.com/api/v1' : 'https://app.paydunya.com/sandbox-api/v1',
    ],

    // Social Login
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI'),
    ],

    'apple' => [
        'client_id'     => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect'      => env('APPLE_REDIRECT_URI'),
    ],
];
