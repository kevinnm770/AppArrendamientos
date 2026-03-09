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



    'crlibre' => [
        'base_url' => env('CRLIBRE_BASE_URL', 'https://api.crlibre.com'),
        'username' => env('CRLIBRE_USERNAME'),
        'api_key' => env('CRLIBRE_API_KEY'),
        'password' => env('CRLIBRE_PASSWORD'),
        'environment' => env('CRLIBRE_ENVIRONMENT', 'stag'),
        'timeout' => env('CRLIBRE_TIMEOUT', 20),
        'connect_timeout' => env('CRLIBRE_CONNECT_TIMEOUT', 10),
    ],


    'cr_einvoice' => [
        'branch' => env('CR_EINVOICE_BRANCH', '001'),
        'terminal' => env('CR_EINVOICE_TERMINAL', '00001'),
        'document_type' => env('CR_EINVOICE_DOCUMENT_TYPE', '01'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
