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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'request_url' => env('ZARINPAL_REQUEST_URL', 'https://payment.zarinpal.com/pg/v4/payment/request.json'),
        'verify_url' => env('ZARINPAL_VERIFY_URL', 'https://payment.zarinpal.com/pg/v4/payment/verify.json'),
        'gateway_url' => env('ZARINPAL_GATEWAY_URL', 'https://payment.zarinpal.com/pg/StartPay'),
        'mock' => env('ZARINPAL_MOCK', false),
    ],

    'card_to_card' => [
        'card_number' => env('CARD_TO_CARD_NUMBER', '6037997199529528'),
        'holder' => env('CARD_TO_CARD_HOLDER', 'سید محمد یوسف سادات فخر'),
    ],

];
