<?php

return [

    // Google AdSense publisher ID (ca-pub-…). Ads render only when set. docs/SEO.md §5.
    // Free captcha for guest comments; off until both keys are set (App\Support\Turnstile).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET'),
        // Our own hostnames; a token from anywhere else is refused (D60). Default: the APP_URL host.
        'hostnames' => env('TURNSTILE_HOSTNAMES', parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: ''),
    ],

    // D119: Google Analytics 4 tag (public pages of the live site) and read-only reports for the AI team.
    'google' => [
        'measurement_id' => env('GA_MEASUREMENT_ID'),            // G-XXXXXXXXXX (the tag)
        'ga_property' => env('GA_PROPERTY_ID'),                  // numeric property id (the reports)
        'search_console_site' => env('SEARCH_CONSOLE_SITE'),     // sc-domain:hatpakha.com
        'service_account' => env('GOOGLE_SERVICE_ACCOUNT_JSON'), // the service account's JSON key, base64
    ],

    // D116: the magazine's own social pages (footer link + Organization sameAs) and Meta's domain verification code.
    'social' => [
        'facebook' => env('FACEBOOK_PAGE_URL', 'https://www.facebook.com/Hatpakhaonline'),
        'instagram' => env('INSTAGRAM_URL', 'https://www.instagram.com/hatpakha/'),
        'facebook_domain_verification' => env('FACEBOOK_DOMAIN_VERIFICATION'),
        'facebook_app_id' => env('FACEBOOK_APP_ID'),   // optional: only silences the Sharing Debugger's fb:app_id note
    ],

    // D111: one slot id per ad spot, created in AdSense; an empty one simply doesn't show.
    'adsense' => [
        'client' => env('ADSENSE_CLIENT', 'ca-pub-4627594058272769'),
        'force' => (bool) env('ADSENSE_FORCE', false),   // show ads off the live site (testing only)
        'slots' => [
            'home' => env('ADSENSE_SLOT_HOME'),
            'home_2' => env('ADSENSE_SLOT_HOME_2'),
            'article' => env('ADSENSE_SLOT_ARTICLE'),
            'article_2' => env('ADSENSE_SLOT_ARTICLE_2'),
            'sidebar' => env('ADSENSE_SLOT_SIDEBAR'),
            'list' => env('ADSENSE_SLOT_LIST'),
        ],
    ],

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

    // D127: phone notifications (Web Push). Keys from `php artisan hatpakha:vapid`; empty = notifications off.
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'https://hatpakha.com/contact-us/'),
        'daily' => (bool) env('PUSH_DAILY', true),   // D128: the evening note of the day's new pieces (PUSH_DAILY=false stops it)
    ],

];
