<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    /*
    |--------------------------------------------------------------------------
    | Razorpay
    |--------------------------------------------------------------------------
    |
    | The platform's own Razorpay account, used to collect subscriptions from
    | stores. Without a key the pay-online button is hidden and payments are
    | recorded by hand instead.
    |
    | The webhook secret is set on the same page in the Razorpay dashboard as
    | the webhook itself, and is what proves a callback really came from them.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | WhatsApp (Meta Cloud API)
    |--------------------------------------------------------------------------
    |
    | Sends the customer their bill, and the one-time code that authorises a
    | points redemption. Without a token nothing is sent: the bill button is
    | hidden and redemption carries on as it did before, because a code that
    | cannot be delivered must not be allowed to block the till.
    |
    | Both messages go out as approved templates — Meta does not allow free
    | text to someone who has not written to you in the last 24 hours.
    |
    */

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

        // The keys above are swapped for the serving store's own as each
        // request is identified. These are the platform's, kept aside so a
        // store with no credentials of its own falls back to them rather than
        // to whichever store was served last.
        'platform' => [
            'token' => env('WHATSAPP_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'bill_template' => env('WHATSAPP_BILL_TEMPLATE', 'bill_copy'),
            'otp_template' => env('WHATSAPP_OTP_TEMPLATE', 'redeem_otp'),
            'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
        ],
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

        'bill_template' => env('WHATSAPP_BILL_TEMPLATE', 'bill_copy'),
        'otp_template' => env('WHATSAPP_OTP_TEMPLATE', 'redeem_otp'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),

        // Dialling code used when a shop has not set its own and the number
        // was typed without one.
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '91'),

        'otp_minutes' => (int) env('WHATSAPP_OTP_MINUTES', 5),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID'),
        'secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
    ],

];
