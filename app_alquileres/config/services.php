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
        'base_url' => env('CRLIBRE_BASE_URL', 'https://api-demo.crlibre.org/api.php'),
        'username' => env('CRLIBRE_USERNAME'),
        'api_key' => env('CRLIBRE_API_KEY'),
        'password' => env('CRLIBRE_PASSWORD'),
        'environment' => env('CRLIBRE_ENVIRONMENT', 'stag'),
        'client_id_stage' => env('CRLIBRE_CLIENT_ID_STAGE', 'api-stag'),
        'client_id_prod' => env('CRLIBRE_CLIENT_ID_PROD', 'api-prod'),
        'timeout' => env('CRLIBRE_TIMEOUT', 20),
        'connect_timeout' => env('CRLIBRE_CONNECT_TIMEOUT', 10),
    ],

    'cr_einvoice' => [
        'branch' => env('CR_EINVOICE_BRANCH', '001'),
        'terminal' => env('CR_EINVOICE_TERMINAL', '00001'),
        'document_type' => env('CR_EINVOICE_DOCUMENT_TYPE', '01'),
    ],

    // Integración directa con Hacienda (ATV / Comprobantes Electrónicos v4.4).
    'hacienda' => [
        'environment' => env('HACIENDA_ENVIRONMENT', 'stag'), // 'stag' (sandbox) | 'prod'

        'idp_url_stag' => env('HACIENDA_IDP_URL_STAG', 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut-stag/protocol/openid-connect/token'),
        'idp_url_prod' => env('HACIENDA_IDP_URL_PROD', 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/protocol/openid-connect/token'),

        'api_url_stag' => env('HACIENDA_API_URL_STAG', 'https://api-sandbox.comprobanteselectronicos.go.cr/recepcion/v1'),
        'api_url_prod' => env('HACIENDA_API_URL_PROD', 'https://api.comprobanteselectronicos.go.cr/recepcion/v1'),

        // Client IDs públicos y conocidos del IDP de Hacienda (no son secretos; el usuario/contraseña sí lo son).
        'client_id_stag' => env('HACIENDA_CLIENT_ID_STAG', 'api-stag'),
        'client_id_prod' => env('HACIENDA_CLIENT_ID_PROD', 'api-prod'),

        'timeout' => env('HACIENDA_TIMEOUT', 25),
        'connect_timeout' => env('HACIENDA_CONNECT_TIMEOUT', 10),

        'xsd_version' => '4.4',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
