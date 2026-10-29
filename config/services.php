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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Sign in with Google / Apple. Each is a comma-separated list of the
    | client IDs whose tokens this server accepts: for Google, the OAuth
    | client IDs of the Android, iOS and web apps; for Apple, the iOS bundle
    | ID and any Services ID used on the web. Empty turns that sign-in off.
    */

    'google' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))))),
    ],

    'apple' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('APPLE_CLIENT_IDS', ''))))),
    ],

    /*
    | Push notifications (App\Support\Fcm): the Firebase service-account key,
    | from Firebase console › Project settings › Service accounts › Generate
    | new private key. A secret — keep it out of git (storage/app is ignored).
    */

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
    ],

    /*
     * Sign-up emails (App\Support\RealEmail, App\Support\EmailVerification).
     * `dns` refuses domains that cannot receive mail. `required_for_api`
     * blocks the app's data endpoints until the email is verified: off until
     * the app build with the code screen is on most phones, since the older
     * build cannot ask for a code. The website always requires it.
     */
    'email_check' => [
        'dns'              => env('EMAIL_DNS_CHECK', true),
        'required_for_api' => env('EMAIL_VERIFICATION_REQUIRED_FOR_API', false),
    ],

];
