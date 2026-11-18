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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign-exchange providers
    |--------------------------------------------------------------------------
    |
    | `fx.default` selects which FxRateProvider implementation the container
    | resolves. Frankfurter (ECB daily rates, free, no key) is the default.
    | To swap in a different provider, bind it to App\Services\Fx\Contracts\FxRateProvider
    | in AppServiceProvider — no other code needs to change.
    */
    'fx' => [
        'default' => env('FX_PROVIDER', 'frankfurter'),
        'frankfurter' => [
            'base_url' => env('FX_FRANKFURTER_URL', 'https://api.frankfurter.dev/v2'),
            'timeout' => (int) env('FX_FRANKFURTER_TIMEOUT', 8),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'ca_bundle' => env('WHATSAPP_CA_BUNDLE'),
    ],

];
