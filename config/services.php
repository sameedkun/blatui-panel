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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    ],

    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],

    /*
    | App Store Server Notifications V2 (POST /webhooks/appstore).
    |
    | root_certificate      Apple Root CA - G3 (DER or PEM) — required; every
    |                       notification is rejected without it. Written by
    |                       `php artisan app-store:refresh-certificate` (run once
    |                       on deploy, then monthly by the scheduler).
    | bundle_id             Your app's bundle id. Anything signed for another app
    |                       is rejected; strongly recommended in production.
    | environments          Which environments are applied to subscriptions. Others
    |                       are stored (visible in the panel) but change nothing —
    |                       keeps free Sandbox/TestFlight purchases off production.
    | verify_url_signature  Also require the signed URL printed by
    |                       `php artisan app-store:webhook-url`.
    */
    'app_store' => [
        'root_certificate' => env('APP_STORE_ROOT_CERTIFICATE', storage_path('app/private/certificates/AppleRootCA-G3.cer')),
        'bundle_id' => env('APP_STORE_BUNDLE_ID'),
        'environments' => explode(',', (string) env(
            'APP_STORE_ENVIRONMENTS',
            env('APP_ENV') === 'production' ? 'Production' : 'Production,Sandbox,Xcode,LocalTesting',
        )),
        'verify_url_signature' => (bool) env('APP_STORE_WEBHOOK_SIGNED_URL', false),
    ],

];
